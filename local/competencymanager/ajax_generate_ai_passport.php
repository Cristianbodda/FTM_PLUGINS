<?php
/**
 * AJAX endpoint for AI-powered generation of passport comments and final note.
 *
 * @package    local_competencymanager
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/report_generator.php');
require_once(__DIR__ . '/area_mapping.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/competencymanager:evaluate', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $userid     = required_param('userid', PARAM_INT);
    $courseid   = required_param('courseid', PARAM_INT);
    $action     = required_param('action', PARAM_ALPHANUMEXT);
    $area_key   = optional_param('area_key', '', PARAM_ALPHANUMEXT);
    $cv_text    = optional_param('cv_text', '', PARAM_TEXT);
    $draft_text = optional_param('draft_text', '', PARAM_TEXT);

    // Validate action.
    if (!in_array($action, ['area', 'final_note', 'all', 'improve', 'rewrite'])) {
        throw new Exception('Azione non valida');
    }

    // Validate user.
    $student = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

    // Get API key: try local_competencymanager first, then jobaida fallback.
    $apikey = get_config('local_competencymanager', 'openai_apikey')
           ?: get_config('local_jobaida', 'openai_apikey')
           ?: '';
    if (empty($apikey)) {
        throw new Exception('Chiave API OpenAI non configurata. Configurarla in Amministrazione -> Plugins -> Competency Manager o JobAIDA.');
    }

    // ----------------------------------------------------------------
    // Load style examples (admin setting — few-shot style reference).
    // ----------------------------------------------------------------
    $styleExamplesRaw = get_config('local_competencymanager', 'passport_style_examples') ?: '';
    $systemStyleExtra = '';
    if (!empty(trim($styleExamplesRaw))) {
        $trimmed = mb_substr(trim($styleExamplesRaw), 0, 5000);
        $systemStyleExtra = "ESEMPI DI STILE DA IMITARE (analizza la struttura, il lessico e il tono — NON copiare i contenuti):\n"
            . "---\n{$trimmed}\n---\n"
            . "Scrivi i nuovi commenti imitando fedelmente questo stile: lunghezza delle frasi, registro linguistico, scelta dei termini tecnici.";
    }

    // ----------------------------------------------------------------
    // Load all student data.
    // ----------------------------------------------------------------
    $radardata    = \local_competencymanager\report_generator::get_radar_chart_data($userid, $courseid);
    $summary      = \local_competencymanager\report_generator::get_student_summary($userid, $courseid);
    $competencies = $radardata['competencies'];

    // Detect sector.
    $sector = null;
    $validSectors = ['AUTOMOBILE', 'AUTOVEICOLO', 'AUTOMAZIONE', 'CHIMFARM', 'ELETTRICITA', 'LOGISTICA', 'MECCANICA', 'METALCOSTRUZIONE', 'GENERICO'];
    foreach ($competencies as $comp) {
        $parts = explode('_', $comp['idnumber'] ?? '');
        $ps = strtoupper($parts[0] ?? '');
        if (count($parts) >= 2 && in_array($ps, $validSectors)) {
            $sector = $ps;
            break;
        }
    }

    // Filter competencies by sector.
    if ($sector) {
        $normalizedSector = normalize_sector_name($sector);
        $filtered = array_filter($competencies, function($c) use ($normalizedSector) {
            $parts = explode('_', $c['idnumber'] ?? '');
            return strcasecmp(normalize_sector_name($parts[0] ?? ''), $normalizedSector) === 0;
        });
        if (!empty($filtered)) {
            $competencies = array_values($filtered);
        }
    }

    // Aggregate by area.
    $areasData = [];
    foreach ($competencies as $comp) {
        $areaInfo = get_area_info($comp['idnumber'] ?? '');
        $areaKey  = $areaInfo['key'];
        if (!isset($areasData[$areaKey])) {
            $areasData[$areaKey] = [
                'key'  => $areaKey,
                'code' => $areaInfo['code'],
                'name' => $areaInfo['name'],
                'total_questions'   => 0,
                'correct_questions' => 0,
                'competencies'      => [],
            ];
        }
        $areasData[$areaKey]['total_questions']  += $comp['total_questions'];
        $areasData[$areaKey]['correct_questions'] += $comp['correct_questions'];
        $areasData[$areaKey]['competencies'][]    = $comp;
    }
    foreach ($areasData as $k => &$a) {
        $a['percentage'] = $a['total_questions'] > 0
            ? round($a['correct_questions'] / $a['total_questions'] * 100, 1) : 0;
    }
    unset($a);
    ksort($areasData);

    // Returns the Italian descriptor for a score band.
    function passport_score_label(float $pct): string {
        if ($pct <= 30) return 'insufficiente';
        if ($pct <= 50) return 'base (lacune significative)';
        if ($pct <= 65) return 'elementare';
        if ($pct <= 80) return 'discreto';
        if ($pct <= 90) return 'buono';
        return 'eccellente';
    }

    $scalaPunteggi =
        "SCALA DI RIFERIMENTO OBBLIGATORIA:\n"
        . "  0-30%  = insufficiente  → usa: 'presenta lacune rilevanti', 'non ha ancora acquisito', 'necessita formazione'\n"
        . "  31-50% = base           → usa: 'conoscenze parziali', 'competenze incomplete', 'in fase di acquisizione'\n"
        . "  51-65% = elementare     → usa: 'conoscenze elementari', 'ampi margini di miglioramento'\n"
        . "  66-80% = discreto       → usa: 'competenze discrete', 'in via di consolidamento'\n"
        . "  81-90% = buono          → usa: 'buone competenze', 'adeguato per posizioni operative'\n"
        . "  91-100% = eccellente    → usa: 'padronanza consolidata', 'competenze solide ed eccellenti'\n"
        . "REGOLA ASSOLUTA: non usare mai aggettivi di una fascia superiore al punteggio reale.\n";

    // Coach scores per area from comparative table.
    $coachScorePerArea = [];
    $dbman = $DB->get_manager();
    if ($dbman->table_exists('local_compman_final_ratings') && !empty($areasData)) {
        $finalRatings = $DB->get_records_sql(
            "SELECT * FROM {local_compman_final_ratings}
             WHERE studentid = :sid AND courseid = :cid AND method = :m",
            ['sid' => $userid, 'cid' => $courseid, 'm' => 'coach_comp']
        );
        foreach ($finalRatings as $fr) {
            $ak = strtoupper($fr->sector) . '_' . $fr->area_code;
            $coachScorePerArea[$ak] = round((float)$fr->manual_value, 1);
        }
    }

    // Autovalutazione data.
    $autoData = [];
    if ($dbman->table_exists('local_selfassessment')) {
        $autoRecords = $DB->get_records_sql(
            "SELECT c.idnumber, sa.level FROM {local_selfassessment} sa
              JOIN {competency} c ON sa.competencyid = c.id
             WHERE sa.userid = :uid",
            ['uid' => $userid]
        );
        foreach ($autoRecords as $r) {
            $autoData[$r->idnumber] = round(($r->level / 6) * 100, 1);
        }
    }

    // Garage AI profile.
    $garageConfig = null;
    if ($dbman->table_exists('local_garage_config')) {
        $garageConfig = $DB->get_record('local_garage_config', ['userid' => $userid, 'courseid' => $courseid]);
        if (!$garageConfig && $courseid != 0) {
            $garageConfig = $DB->get_record('local_garage_config', ['userid' => $userid, 'courseid' => 0]);
        }
    }
    $aiSettoreTarget  = $garageConfig->ai_settore_target ?? '';
    $aiDisponibilita  = $garageConfig->ai_disponibilita ?? '';
    $aiMobilita       = $garageConfig->ai_mobilita ?? '';
    $aiPuntiForza     = $garageConfig->ai_punti_forza ?? '';
    $aiNote           = $garageConfig->ai_note ?? '';
    $aiPctCercaLavoro = isset($garageConfig->ai_pct_cerca_lavoro) ? (int)$garageConfig->ai_pct_cerca_lavoro : 50;

    // Anonymize CV: remove student name and email.
    $cv_clean = '';
    if (!empty($cv_text)) {
        $cv_clean = $cv_text;
        $fname = $student->firstname;
        $lname = $student->lastname;
        $email = $student->email;
        if ($fname) {
            $cv_clean = str_ireplace($fname, '[CANDIDATO]', $cv_clean);
        }
        if ($lname) {
            $cv_clean = str_ireplace($lname, '[COGNOME]', $cv_clean);
        }
        if ($email) {
            $cv_clean = str_ireplace($email, '[EMAIL]', $cv_clean);
        }
        $cv_clean = mb_substr(trim($cv_clean), 0, 4000);
    }

    // ----------------------------------------------------------------
    // Build profile context string (shared by all prompts).
    // ----------------------------------------------------------------

    // Build the formal name reference (cognome only, like "Il sig. Rossi").
    // Gender is inferred from the name; when ambiguous the AI uses both forms.
    $studentLastname = trim($student->lastname);
    $studentFirstname = trim($student->firstname);
    // Simple Italian gender heuristic: names ending in 'a' are often female.
    $likelyFemale = in_array(mb_strtolower(mb_substr($studentFirstname, -1)), ['a']) &&
                    !in_array(mb_strtolower($studentFirstname), ['luca', 'andrea', 'nicola', 'mattia', 'simba', 'enea', 'bela']);
    $formalRef = $likelyFemale
        ? "La sig.ra {$studentLastname}"
        : "Il sig. {$studentLastname}";

    $profile = "RIFERIMENTO FORMALE DA USARE NEL TESTO: \"{$formalRef}\"\n"
        . "(usa esclusivamente questo riferimento — varia solo tra '{$formalRef}', 'il/la partecipante', 'il/la professionista' ma MAI 'l\\'assicurato/a' come forma principale)\n"
        . "Settore/Mansione target: " . ($aiSettoreTarget ?: 'non specificato') . "\n"
        . "Disponibilita: " . ($aiDisponibilita ?: 'non specificata') . "\n"
        . "Mobilita: " . (str_replace('_', ' ', $aiMobilita) ?: 'non specificata') . "\n"
        . "Motivazione ricerca lavoro: {$aiPctCercaLavoro}%\n";
    if ($aiPuntiForza) {
        $profile .= "Punti di forza: {$aiPuntiForza}\n";
    }
    if ($aiNote) {
        $profile .= "Note del coach: {$aiNote}\n";
    }

    // ----------------------------------------------------------------
    // Build areas summary string.
    // Coach score is PRIMARY when available — it is what appears in the passport.
    // ----------------------------------------------------------------
    $areasSummary = '';
    foreach ($areasData as $areaKey => $area) {
        $coachPct = $coachScorePerArea[$areaKey] ?? null;
        $primaryPct = $coachPct !== null ? $coachPct : $area['percentage'];
        $areasSummary .= "- {$area['code']}. {$area['name']}: ";
        if ($coachPct !== null) {
            $areasSummary .= "VALUTAZIONE COACH={$coachPct}% [" . passport_score_label((float)$coachPct) . "]"
                . ", quiz={$area['percentage']}%";
        } else {
            $areasSummary .= "quiz={$area['percentage']}% [" . passport_score_label((float)$area['percentage']) . "]";
        }
        $autoSum = 0;
        $autoCount = 0;
        foreach ($area['competencies'] as $comp) {
            if (isset($autoData[$comp['idnumber'] ?? ''])) {
                $autoSum += $autoData[$comp['idnumber']];
                $autoCount++;
            }
        }
        if ($autoCount > 0) {
            $areasSummary .= ", autovalut.=" . round($autoSum / $autoCount, 1) . "%";
        }
        $areasSummary .= "\n";
    }

    $model = 'gpt-4.1-mini';

    // ----------------------------------------------------------------
    // ACTION: single area comment
    // ----------------------------------------------------------------
    if ($action === 'area') {
        if (empty($area_key) || !isset($areasData[$area_key])) {
            throw new Exception('Area non trovata: ' . $area_key);
        }
        $area     = $areasData[$area_key];
        $coachPct = $coachScorePerArea[$area_key] ?? null;
        $autoSum  = 0;
        $autoCount = 0;
        foreach ($area['competencies'] as $comp) {
            if (isset($autoData[$comp['idnumber'] ?? ''])) {
                $autoSum += $autoData[$comp['idnumber']];
                $autoCount++;
            }
        }
        $autoPct = $autoCount > 0 ? round($autoSum / $autoCount, 1) : null;

        // PRIMARY score = coach evaluation when available; quiz score is context only.
        $primaryPct   = $coachPct !== null ? $coachPct : $area['percentage'];
        $primaryLabel = passport_score_label((float)$primaryPct);
        $primarySrc   = $coachPct !== null ? 'valutazione coach' : 'punteggio quiz';

        // Competency descriptions from the sector framework (description preferred, shortname as fallback).
        $areaCompNames = array_unique(array_filter(array_map(
            function($c) {
                $desc = trim(strip_tags($c['description'] ?? ''));
                return $desc ?: trim(strip_tags($c['name'] ?? ''));
            },
            $area['competencies']
        )));
        $areaCompList = !empty($areaCompNames)
            ? implode("\n", array_map(function($n) { return "- {$n}"; }, $areaCompNames)) . "\n"
            : "";

        // Determine opening strategy based on score range.
        if ($primaryPct >= 70) {
            $openingStrategy = "Apri DIRETTAMENTE con ciò che il candidato sa fare in modo concreto e specifico. "
                . "Cita almeno 1-2 elementi tecnici dalla lista competenze. Non iniziare con 'Il sig.' ma con la competenza stessa.";
        } elseif ($primaryPct >= 50) {
            $openingStrategy = "Apri con la competenza principale presente, poi pivota subito su ciò che resta da sviluppare. "
                . "Usa 'tuttavia', 'mentre', 'Da sviluppare' come punto di svolta. Cita elementi concreti.";
        } elseif ($primaryPct >= 30) {
            $openingStrategy = "Apri con il CONTESTO (da dove viene il candidato, esperienza pregressa) come spiegazione del gap, "
                . "poi descrivi cosa sa fare e cosa manca. Cita elementi tecnici specifici mancanti.";
        } else {
            $openingStrategy = "Apri DIRETTAMENTE con le lacune concrete — nessuna apertura positiva artificiosa per punteggi <30%. "
                . "Descrivi cosa non e' stato osservato o e' risultato insufficiente. Sii preciso, non vago.";
        }

        $prompt = "=== PROFILO CANDIDATO ===\n{$profile}\n"
            . "=== AREA DI COMPETENZA ===\n"
            . "Area: {$area['code']}. {$area['name']}\n"
            . "*** PUNTEGGIO PASSAPORTO ({$primarySrc}): {$primaryPct}% → fascia: {$primaryLabel} ***\n"
            . ($coachPct !== null ? "   Punteggio quiz (solo contesto): {$area['percentage']}%\n" : "")
            . ($autoPct !== null ? "   Autovalutazione candidato: {$autoPct}%\n" : "")
            . ($areaCompList ? "\nCOMPETENZE SPECIFICHE DELL'AREA (cita almeno 1-2 di queste nel commento):\n{$areaCompList}" : "")
            . (!empty($cv_clean) ? "\n=== CV (anonimizzato) ===\n{$cv_clean}\n" : "")
            . "\n=== SCALA VALUTAZIONE ===\n{$scalaPunteggi}\n"
            . "=== ISTRUZIONE ===\n"
            . "STRATEGIA DI APERTURA per {$primaryPct}%: {$openingStrategy}\n"
            . "OBBLIGATORIO: cita almeno 1-2 elementi CONCRETI dalla lista competenze dell'area (non formule generiche).\n"
            . "Il commento si basa SUL PUNTEGGIO PASSAPORTO ({$primaryPct}% → {$primaryLabel}), non sul quiz.\n"
            . "Scrivi 2-3 frasi. Se <50%: sii diretto sulle lacune, MAI 'eccellente/ottimo/solido'.\n"
            . "Tono: scheda tecnica URC oggettiva. Lingua: italiano formale.";

        $text = ai_passport_call_openai($apikey, $model, $prompt, 600, $systemStyleExtra);
        echo json_encode(['success' => true, 'text' => $text]);
        die();
    }

    // ----------------------------------------------------------------
    // ACTION: final note only
    // ----------------------------------------------------------------
    if ($action === 'final_note') {
        // PRIMARY average = coach scores; fallback to quiz if no coach data.
        $coachPctValues = array_values($coachScorePerArea);
        $avgPrimary = !empty($coachPctValues)
            ? round(array_sum($coachPctValues) / count($coachPctValues), 1)
            : round(array_sum(array_column($areasData, 'percentage')) / max(count($areasData), 1), 1);
        $avgPrimaryLabel = passport_score_label((float)$avgPrimary);
        $avgPrimarySrc   = !empty($coachPctValues) ? 'valutazione coach' : 'punteggio quiz';

        $prompt = "=== PROFILO CANDIDATO ===\n{$profile}\n"
            . "=== RIEPILOGO AREE (VALUTAZIONE COACH = metrica principale del passaporto) ===\n{$areasSummary}\n"
            . "*** MEDIA PASSAPORTO ({$avgPrimarySrc}): {$avgPrimary}% → fascia: {$avgPrimaryLabel} ***\n"
            . (!empty($cv_clean) ? "\n=== CV (anonimizzato) ===\n{$cv_clean}\n\n" : "")
            . "=== SCALA VALUTAZIONE ===\n{$scalaPunteggi}\n"
            . "=== ISTRUZIONE ===\n"
            . "Scrivi una nota tecnica di max 100 parole basata SULLA VALUTAZIONE COACH (non sui punteggi quiz).\n"
            . "La media passaporto e' {$avgPrimary}% (fascia: {$avgPrimaryLabel}).\n"
            . "REGOLA: se media <50% → candidato in fase formativa con lacune rilevanti — dillo chiaramente.\n"
            . "Se media 50-70% → profilo in sviluppo. Se media >70% → profilo consolidato.\n"
            . "Cita le aree con valutazione coach piu' alta e quelle piu' deboli (usa i dati reali).\n"
            . "NON citare i punteggi quiz come risultato principale. NO linguaggio promozionale.\n"
            . "Tono: scheda tecnica per operatore URC o datore di lavoro. Lingua: italiano formale.";

        $text = ai_passport_call_openai($apikey, $model, $prompt, 600, $systemStyleExtra);
        echo json_encode(['success' => true, 'text' => $text]);
        die();
    }

    // ----------------------------------------------------------------
    // ACTION: all (single API call for all areas + final note)
    // ----------------------------------------------------------------
    if ($action === 'all') {
        $areasListForPrompt = '';
        $areaKeys = [];
        foreach ($areasData as $areaKey => $area) {
            $coachPct  = $coachScorePerArea[$areaKey] ?? null;
            $autoSum   = 0;
            $autoCount = 0;
            foreach ($area['competencies'] as $comp) {
                if (isset($autoData[$comp['idnumber'] ?? ''])) {
                    $autoSum += $autoData[$comp['idnumber']];
                    $autoCount++;
                }
            }
            $autoPct = $autoCount > 0 ? round($autoSum / $autoCount, 1) : null;
            // PRIMARY score = coach when available.
            $primaryPct   = $coachPct !== null ? $coachPct : $area['percentage'];
            $primaryLabel = passport_score_label((float)$primaryPct);
            $primarySrc   = $coachPct !== null ? 'coach' : 'quiz';

            // Compact competency list (max 4, description preferred over shortname).
            $aCompNames = array_slice(array_unique(array_filter(array_map(
                function($c) {
                    $desc = trim(strip_tags($c['description'] ?? ''));
                    return $desc ?: trim(strip_tags($c['name'] ?? ''));
                },
                $area['competencies']
            ))), 0, 4);
            $aCompStr = !empty($aCompNames) ? ' [competenze: ' . implode(', ', $aCompNames) . ']' : '';

            $areasListForPrompt .= "- area_key: \"{$areaKey}\" | {$area['code']}. {$area['name']}"
                . " | PASSAPORTO({$primarySrc})={$primaryPct}% [fascia: {$primaryLabel}]"
                . ($coachPct !== null ? " | quiz={$area['percentage']}% (solo contesto)" : "")
                . ($autoPct !== null ? ", auto={$autoPct}%" : "")
                . $aCompStr . "\n";
            $areaKeys[] = $areaKey;
        }

        // Primary average = coach scores when available.
        $allCoachVals = array_values($coachScorePerArea);
        $allAvgPrimary = !empty($allCoachVals)
            ? round(array_sum($allCoachVals) / count($allCoachVals), 1)
            : round(array_sum(array_column($areasData, 'percentage')) / max(count($areasData), 1), 1);
        $allAvgSrc = !empty($allCoachVals) ? 'valutazione coach' : 'punteggio quiz';

        $prompt = "=== PROFILO CANDIDATO ===\n{$profile}\n"
            . "=== AREE DI COMPETENZA (PASSAPORTO(coach) = metrica principale) ===\n{$areasListForPrompt}\n"
            . "*** MEDIA PASSAPORTO ({$allAvgSrc}): {$allAvgPrimary}% → fascia: " . passport_score_label((float)$allAvgPrimary) . " ***\n"
            . (!empty($cv_clean) ? "\n=== CV (anonimizzato) ===\n{$cv_clean}\n\n" : "")
            . "=== SCALA VALUTAZIONE ===\n{$scalaPunteggi}\n"
            . "=== ISTRUZIONE ===\n"
            . "Per OGNI area: commento basato su PASSAPORTO(coach)=X% (NON il punteggio quiz).\n"
            . "REGOLE OBBLIGATORIE:\n"
            . "1. Cita almeno 1-2 elementi tecnici CONCRETI dalla lista competenze di ciascuna area (mai frasi vaghe).\n"
            . "2. VARIA la struttura: non usare lo stesso schema per tutte le aree.\n"
            . "   >=70%: apri con ciò che sa fare concretamente.\n"
            . "   50-69%: apri con il punto di forza, poi pivota su sviluppi.\n"
            . "   30-49%: apri con il contesto/background, poi descrivi il gap.\n"
            . "   <30%: apri direttamente con le lacune concrete, senza apertura positiva.\n"
            . "3. Non iniziare due aree con le stesse prime 3 parole.\n"
            . "4. 2-3 frasi per area. Se <50%: MAI 'eccellente/ottimo/solido'.\n"
            . "Nota finale (max 100 parole): media passaporto {$allAvgPrimary}%. Se <50%: candidato in fase formativa, dillo chiaramente. NO linguaggio promozionale.\n"
            . "Tono: scheda tecnica URC. Lingua: italiano formale.\n\n"
            . "Rispondi SOLO con JSON puro nel formato:\n"
            . "{\n"
            . "  \"areas\": {\n"
            . "    \"AREA_KEY_1\": \"commento area 1\",\n"
            . "    \"AREA_KEY_2\": \"commento area 2\"\n"
            . "  },\n"
            . "  \"final_note\": \"nota finale\"\n"
            . "}\n"
            . "Usa esattamente gli area_key forniti sopra. Non aggiungere testo prima o dopo il JSON.";

        $rawResponse = ai_passport_call_openai($apikey, $model, $prompt, 3000, $systemStyleExtra);

        // Parse JSON response (strip potential markdown code fences).
        $rawResponse = preg_replace('/^```json\s*/i', '', trim($rawResponse));
        $rawResponse = preg_replace('/\s*```$/i', '', $rawResponse);
        $parsed = json_decode(trim($rawResponse), true);

        if (!is_array($parsed) || !isset($parsed['areas'])) {
            throw new Exception('Risposta AI non valida. Riprova.');
        }

        // Validate area keys (security: only return keys that actually exist).
        $safeAreas = [];
        foreach ($parsed['areas'] as $k => $v) {
            if (in_array($k, $areaKeys) && is_string($v)) {
                $safeAreas[$k] = trim($v);
            }
        }

        echo json_encode([
            'success'    => true,
            'areas'      => $safeAreas,
            'final_note' => isset($parsed['final_note']) ? trim($parsed['final_note']) : '',
        ]);
        die();
    }

    // ----------------------------------------------------------------
    // ACTION: improve — light polish of coach's existing draft text
    // ----------------------------------------------------------------
    if ($action === 'improve') {
        if (empty($draft_text)) {
            throw new Exception('Testo mancante. Scrivi un commento prima di usare Migliora.');
        }
        $draft_clean = mb_substr(trim($draft_text), 0, 2000);

        $prompt = "Sei un revisore linguistico tecnico. Migliora il testo seguente in italiano formale:\n"
            . "- Correggi grammatica, punteggiatura e sintassi\n"
            . "- Migliora la fluidità e la chiarezza\n"
            . "- Mantieni ESATTAMENTE lo stesso significato, gli stessi fatti e la stessa lunghezza\n"
            . "- Non aggiungere, rimuovere o modificare informazioni\n"
            . "- Rispondi SOLO con il testo migliorato, senza spiegazioni\n\n"
            . "=== TESTO DA MIGLIORARE ===\n{$draft_clean}";

        $text = ai_passport_call_openai($apikey, $model, $prompt, 500, $systemStyleExtra);
        echo json_encode(['success' => true, 'text' => $text]);
        die();
    }

    // ----------------------------------------------------------------
    // ACTION: rewrite — full rewrite using data + coach draft as context
    // ----------------------------------------------------------------
    if ($action === 'rewrite') {
        if (empty($area_key) || !isset($areasData[$area_key])) {
            throw new Exception('Area non trovata: ' . $area_key);
        }
        if (empty($draft_text)) {
            throw new Exception('Testo mancante. Scrivi un commento prima di usare Riscrivi.');
        }

        $area     = $areasData[$area_key];
        $coachPct = $coachScorePerArea[$area_key] ?? null;
        $autoSum  = 0;
        $autoCount = 0;
        foreach ($area['competencies'] as $comp) {
            if (isset($autoData[$comp['idnumber'] ?? ''])) {
                $autoSum  += $autoData[$comp['idnumber']];
                $autoCount++;
            }
        }
        $autoPct = $autoCount > 0 ? round($autoSum / $autoCount, 1) : null;

        $primaryPct   = $coachPct !== null ? $coachPct : $area['percentage'];
        $primaryLabel = passport_score_label((float)$primaryPct);
        $primarySrc   = $coachPct !== null ? 'valutazione coach' : 'punteggio quiz';

        $areaCompNames = array_unique(array_filter(array_map(
            function($c) {
                $desc = trim(strip_tags($c['description'] ?? ''));
                return $desc ?: trim(strip_tags($c['name'] ?? ''));
            },
            $area['competencies']
        )));
        $areaCompList = !empty($areaCompNames)
            ? implode("\n", array_map(function($n) { return "- {$n}"; }, $areaCompNames)) . "\n"
            : "";

        $draft_clean = mb_substr(trim($draft_text), 0, 1500);

        $prompt = "=== PROFILO CANDIDATO ===\n{$profile}\n"
            . "=== AREA DI COMPETENZA ===\n"
            . "Area: {$area['code']}. {$area['name']}\n"
            . "*** PUNTEGGIO PASSAPORTO ({$primarySrc}): {$primaryPct}% → fascia: {$primaryLabel} ***\n"
            . ($coachPct !== null ? "   Punteggio quiz (solo contesto): {$area['percentage']}%\n" : "")
            . ($autoPct !== null ? "   Autovalutazione candidato: {$autoPct}%\n" : "")
            . ($areaCompList ? "\nCompetenze specifiche dell'area:\n{$areaCompList}" : "")
            . "\n=== BOZZA DEL COACH (usa come contesto aggiuntivo) ===\n{$draft_clean}\n"
            . "\n=== SCALA VALUTAZIONE ===\n{$scalaPunteggi}\n"
            . "=== ISTRUZIONE ===\n"
            . "Riscrivi il commento per questa area con struttura e stile professionale.\n"
            . "Integra le osservazioni specifiche del coach (dalla bozza) con i dati oggettivi.\n"
            . "Il commento finale si basa sul PUNTEGGIO PASSAPORTO ({$primaryPct}% → {$primaryLabel}).\n"
            . "Scrivi 2-3 frasi. Tono: scheda tecnica URC. Lingua: italiano formale.";

        $text = ai_passport_call_openai($apikey, $model, $prompt, 600, $systemStyleExtra);
        echo json_encode(['success' => true, 'text' => $text]);
        die();
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();

// ----------------------------------------------------------------
// Helper: call OpenAI chat completions.
// ----------------------------------------------------------------
/**
 * Call the OpenAI Chat Completions API.
 *
 * @param string $apikey     OpenAI API key.
 * @param string $model      Model name (e.g. gpt-4o-mini).
 * @param string $prompt     User prompt text.
 * @param int    $max_tokens Maximum tokens in the response.
 * @return string            Text content from the first choice.
 * @throws Exception         On network or API errors.
 */
function ai_passport_call_openai(string $apikey, string $model, string $prompt, int $max_tokens = 600, string $system_extra = ''): string {
    $systemRole =
        "Sei un valutatore tecnico della Fondazione Terzo Millennio (FTM) che compila schede per l'URC (Ufficio Regionale di Collocamento) in Svizzera.\n"
        . "I commenti devono riflettere fedelmente i dati numerici. Non esagerare: punteggi bassi indicano lacune reali da descrivere onestamente.\n"
        . "\n"
        . "=== REGOLE DI STILE OBBLIGATORIE ===\n"
        . "1. RIFERIMENTO: Usa il riferimento formale indicato nel profilo (es. 'Il sig. Rossi'). MAI 'l'assicurato'. Alterna con 'il/la partecipante' o 'il/la professionista'.\n"
        . "2. SPECIFICITÀ TECNICA: Cita SEMPRE almeno 1-2 strumenti, normative o competenze tecniche CONCRETE dall'area (vedi vocabolario sotto). MAI frasi vaghe come 'buone competenze nell'area'.\n"
        . "3. STRUTTURA VARIABILE: La struttura dipende dal punteggio (vedi istruzione nel prompt). Non usare sempre lo stesso schema.\n"
        . "4. LUNGHEZZA: 2-3 frasi per area. NON elenchi puntati. Per aree molto semplici: 1 frase precisa basta.\n"
        . "5. ONESTÀ: <50% → lacune reali, descritte concretamente. MAI 'ottimo/eccellente' per punteggi bassi.\n"
        . "6. CONTESTO: Se il background del candidato spiega il gap (settore limitrofo, paese d'origine), citalo.\n"
        . "7. ANTI-RIPETIZIONE: Non iniziare due commenti diversi con le stesse prime 3 parole. Varia soggetto, verbo, struttura.\n"
        . "\n"
        . "=== VOCABOLARIO TECNICO PER SETTORE ===\n"
        . "ELETTRICITA: multimetro, schemi unifilari/multifilari, SEV/NIBT, AS-BUILT, DPI, interruttori differenziali, quadri civili/industriali, posa canaline, cablaggi, derivazioni esterne, misure di isolamento/continuità, verifiche impianti\n"
        . "AUTOMAZIONE: PLC (Siemens S7/Codesys), SCADA, HMI, sensori (induttivi/capacitivi/ottici), attuatori pneumatici/idraulici, programmazione ladder/FBD/STL, troubleshooting, Profibus/EtherNet-IP, schema elettrico pneumatico\n"
        . "MECCANICA: tornitura/fresatura CNC, rettifica, micrometro/calibro/comparatore, tolleranze ISO/GD&T, disegno tecnico, montaggio cuscinetti, saldatura MIG/TIG, controllo dimensionale, manutenzione preventiva\n"
        . "AUTOMOBILE: diagnosi OBD/oscilloscopio, distribuzione motore, frizione/cambio/differenziale, impianto frenante ABS/ESP, sospensioni, climatizzazione, carrozzeria/verniciatura, lettura schemi elettrici auto\n"
        . "CHIMFARM: GMP/BPF, SOP, documentazione batch record, operazioni di produzione (mixing/granulazione/confezionamento), controllo qualità IPC, pulizia e sanificazione CIP/SIP, pesata materie prime, tracciabilità lotti\n"
        . "LOGISTICA: WMS, picking/packing, ricevimento merci, inventario/conteggio cicli, carrelli elevatori (patente), ottimizzazione percorsi, gestione DDT/CMR, cross-docking, kitting, etichettatura doganale\n"
        . "METALCOSTRUZIONE: saldatura MIG/MAG/TIG, lettura disegni costruttivi, taglio plasma/ossigas/laser, piegatura lamiera, assemblaggio strutture metalliche, controllo dimensionale, sabbiatura/verniciatura, montaggio in opera\n"
        . "GENERICO: comunicazione, lavoro in team, gestione del tempo, problem solving, uso PC/Office, ricerca attiva del lavoro, colloquio di lavoro\n"
        . "\n"
        . "=== ESEMPI DI STILE FTM (imita la VARIETÀ di struttura) ===\n"
        . "ESEMPIO A (20%, apertura con contesto):\n"
        . "  «Grazie alla propria esperienza nell'interfacciare impianti fotovoltaici con impianti civili, il sig. Busacca ha acquisito un livello appena sufficiente in quest'area. Da sviluppare la posa di canaline, le derivazioni esterne e le installazioni industriali.»\n"
        . "ESEMPIO B (40%, apertura con forza/gap diretto):\n"
        . "  «Il sig. Busacca possiede alcune competenze nel montaggio e nel cablaggio di quadri civili; nessuna competenza nel montaggio di quadri industriali né di cablaggio a bordo macchina.»\n"
        . "ESEMPIO C (60%, apertura con competenza specifica):\n"
        . "  «Padronanza nell'utilizzo del multimetro e nella verifica degli interruttori differenziali. Per quanto riguarda le misure di isolamento e di continuità il sig. Prokic ha dimostrato conoscenza teorica ma non ancora esperienza diretta sul campo.»\n"
        . "ESEMPIO D (35%, apertura con contesto normativo):\n"
        . "  «Buone basi nel dimensionamento di impianti (sezioni, protezioni, caduta di tensione). L'interpretazione di schemi multifilari e i calcoli di cortocircuito risultano appena sufficienti — verosimilmente per la differenza tra standard CH e quelli del paese d'origine.»\n"
        . "ESEMPIO E (<30%, apertura diretta sul gap):\n"
        . "  «Non sono emerse competenze significative nella programmazione PLC né nell'utilizzo di sistemi HMI/SCADA. La conoscenza si limita a nozioni teoriche di base, senza esperienza pratica verificabile.»\n"
        . "ESEMPIO NOTA FINALE (profilo medio-basso):\n"
        . "  «Il sig. Busacca ha maturato esperienza principalmente nel montaggio fotovoltaico e in attività accessorie in ambito civile. Le competenze tecniche specifiche risultano parziali e non ancora sufficienti per operare autonomamente in contesti impiantistici professionali.»\n";

    if ($system_extra !== '') {
        $systemRole .= "\n=== ESEMPI AGGIUNTIVI (priorità massima) ===\n" . $system_extra;
    }

    $payload = [
        'model'       => $model,
        'messages'    => [
            ['role' => 'system', 'content' => $systemRole],
            ['role' => 'user',   'content' => $prompt],
        ],
        'max_tokens'  => $max_tokens,
        'temperature' => 0.5,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apikey,
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('Errore connessione OpenAI: ' . $error);
    }
    if ($httpcode !== 200) {
        $data = json_decode($response, true);
        throw new Exception('OpenAI: ' . ($data['error']['message'] ?? "HTTP {$httpcode}"));
    }

    $data = json_decode($response, true);
    return trim($data['choices'][0]['message']['content'] ?? '');
}
