<?php
/**
 * AJAX endpoint for AI-assisted report text generation.
 * Uses OpenAI GPT-4o to generate report sections in the coach's writing style.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_cpurc/lib.php');

require_login();
require_sesskey();

// Only site admins can use AI generation.
if (!is_siteadmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo amministratori']);
    die();
}

header('Content-Type: application/json; charset=utf-8');

try {
    $field = required_param('field', PARAM_ALPHANUMEXT);
    $studentid = required_param('studentid', PARAM_INT);
    $cvbd_text_raw = optional_param('cvbd_text', '', PARAM_RAW);
    $cvbd_text = !empty($cvbd_text_raw) ? mb_substr(trim($cvbd_text_raw), 0, 6000) : '';
    $coach_notes = optional_param('coach_notes', '', PARAM_RAW);
    $report_text_raw = optional_param('report_text', '', PARAM_RAW);
    // Truncate report text to max ~8000 chars to stay within token limits.
    $report_text = '';
    if (!empty($report_text_raw)) {
        $report_text = mb_substr(trim($report_text_raw), 0, 8000);
        if (mb_strlen($report_text_raw) > 8000) {
            $report_text .= "\n[...testo troncato per limiti API...]";
        }
    }
    $ratings_json = optional_param('ratings', '{}', PARAM_RAW);

    // Get OpenAI API key from JobAIDA settings.
    $apikey = get_config('local_jobaida', 'openai_apikey');
    if (empty($apikey)) {
        throw new Exception('Chiave API OpenAI non configurata. Vai in Amministrazione > Plugin > JobAIDA.');
    }

    $model = get_config('local_jobaida', 'openai_model') ?: 'gpt-4o';

    // Get student data.
    $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);
    $studentname = fullname($student);

    // Get CPURC data.
    $cpurc = $DB->get_record('local_ftm_cpurc_students', ['userid' => $studentid]);

    // Get competency data if available.
    $competency_summary = '';
    if ($DB->get_manager()->table_exists('local_coach_evaluations')) {
        $eval = $DB->get_record_sql(
            "SELECT * FROM {local_coach_evaluations}
             WHERE studentid = ? AND status IN ('draft','completed','signed')
             ORDER BY timemodified DESC LIMIT 1",
            [$studentid]
        );
        if ($eval) {
            $ratings_db = $DB->get_records_sql(
                "SELECT r.*, c.shortname, c.idnumber
                 FROM {local_coach_eval_ratings} r
                 JOIN {competency} c ON c.id = r.competencyid
                 WHERE r.evaluationid = ?
                 ORDER BY c.idnumber",
                [$eval->id]
            );
            if ($ratings_db) {
                $competency_summary = "Valutazione coach (scala Bloom 1-6):\n";
                foreach ($ratings_db as $r) {
                    $bloom = $r->rating == 0 ? 'N/O' : $r->rating . '/6';
                    $competency_summary .= "- {$r->shortname}: {$bloom}\n";
                }
            }
        }
    }

    // Parse ratings from form (for competency observation sections).
    $ratings = json_decode($ratings_json, true) ?: [];

    // System prompt - Cristian's writing style.
    $systemprompt = "Sei un coach formatore svizzero che redige rapporti CPURC (rapporti finali per l'ufficio regionale di collocamento). "
        . "Scrivi in italiano formale svizzero, tono istituzionale e professionale.\n\n"
        . "STILE DI SCRITTURA OBBLIGATORIO:\n"
        . "- Usa 'L'assicurato', 'Il partecipante' o 'La PCI' per riferirsi alla persona\n"
        . "- Frasi strutturate con subordinate: 'si rileva', 'emerge', 'evidenzia', 'presenta'\n"
        . "- Riferimenti specifici a dati concreti (anni, aziende, durate, settori)\n"
        . "- Valutazioni bilanciate: punti di forza + aree di miglioramento\n"
        . "- Sintesi conclusiva alla fine di ogni paragrafo\n"
        . "- MASSIMO 200 parole per ogni testo generato\n"
        . "- NON usare elenchi puntati, scrivi in forma discorsiva\n"
        . "- NON usare frasi generiche. Ogni affermazione deve essere basata sui dati forniti\n\n"
        . "Rispondi SOLO con il testo del paragrafo, senza titoli ne introduzioni.";

    // Build user prompt based on field.
    $userprompt = "Studente: {$studentname}\n";

    if ($cpurc) {
        $userprompt .= "Settore: " . ($cpurc->sector_detected ?? 'Generico') . "\n";
        $userprompt .= "Ultima professione: " . ($cpurc->last_profession ?? 'N/D') . "\n";
        if (!empty($cpurc->date_start)) {
            $userprompt .= "Periodo misura: " . date('d.m.Y', $cpurc->date_start);
            if (!empty($cpurc->date_end_planned)) {
                $userprompt .= " - " . date('d.m.Y', $cpurc->date_end_planned);
            }
            $userprompt .= "\n";
        }
    }

    switch ($field) {
        case 'initial_situation':
            if (empty($cvbd_text)) {
                throw new Exception('Incolla il CVBD dello studente nel campo apposito prima di generare.');
            }
            $userprompt .= "\n=== CVBD DELLO STUDENTE ===\n{$cvbd_text}\n\n";
            $userprompt .= "Genera la SEZIONE 1 - SITUAZIONE INIZIALE del rapporto CPURC.\n"
                . "Deve contenere: sintesi della situazione iniziale, obiettivi, storia della carriera professionale e formativa.\n"
                . "Basa tutto sul CVBD fornito. Evidenzia la continuita professionale, le competenze maturate e il percorso formativo.\n"
                . "Concludi con una valutazione del profilo rispetto al reinserimento nel settore industriale.";
            break;

        case 'initial_situation_sector':
            if (!empty($cvbd_text)) {
                $userprompt .= "\n=== CVBD ===\n{$cvbd_text}\n\n";
            }
            $userprompt .= "Indica il settore o i settori di riferimento su cui viene effettuato il rilevamento.\n"
                . "Rispondi con una frase breve (es. 'Meccanica industriale' o 'Generico industriale').";
            $systemprompt = str_replace('MASSIMO 200 parole', 'MASSIMO 20 parole', $systemprompt);
            break;

        case 'sector_competency_text':
            if (!empty($report_text)) {
                $userprompt .= "\n=== REPORT STUDENTE (dati reali da piattaforma) ===\n{$report_text}\n\n";
            }
            if (!empty($competency_summary)) {
                $userprompt .= "\n=== VALUTAZIONE COACH (Bloom 1-6) ===\n{$competency_summary}\n";
            }
            if (!empty($cvbd_text)) {
                $userprompt .= "\n=== CVBD ===\n{$cvbd_text}\n\n";
            }
            if (!empty($coach_notes)) {
                $userprompt .= "\n=== NOTE DEL COACH ===\n{$coach_notes}\n\n";
            }
            $userprompt .= "Genera la VALUTAZIONE DELLE COMPETENZE DEL SETTORE DI RIFERIMENTO.\n"
                . "Usa PRIORITARIAMENTE i dati del Report Studente (percentuali per area, risultati quiz, radar) se disponibili.\n"
                . "Descrivi quali competenze tecniche sono state rilevate, citando le aree e i livelli specifici.\n"
                . "Indica anche se sono stati fatti stage che hanno permesso di rilevare/confermare competenze pratiche.\n"
                . "NON inventare dati. Basa tutto sui documenti forniti.";
            break;

        case 'possible_sectors':
            if (!empty($report_text)) {
                $userprompt .= "\n=== REPORT STUDENTE ===\n{$report_text}\n\n";
            }
            if (!empty($cvbd_text)) {
                $userprompt .= "\n=== CVBD ===\n{$cvbd_text}\n\n";
            }
            if (!empty($coach_notes)) {
                $userprompt .= "\n=== INDICAZIONI DEL COACH ===\n{$coach_notes}\n\n";
            }
            $userprompt .= "Genera la sezione POSSIBILI SETTORI E AMBITI.\n"
                . "Indica in quali settori industriali il partecipante potrebbe reinserirsi, basandoti sul CVBD, sui risultati del report e sulle indicazioni del coach.";
            break;

        case 'final_summary':
            if (!empty($report_text)) {
                $userprompt .= "\n=== REPORT STUDENTE ===\n{$report_text}\n\n";
            }
            if (!empty($cvbd_text)) {
                $userprompt .= "\n=== CVBD ===\n{$cvbd_text}\n\n";
            }
            if (!empty($competency_summary)) {
                $userprompt .= "\n=== VALUTAZIONE COACH ===\n{$competency_summary}\n";
            }
            if (!empty($coach_notes)) {
                $userprompt .= "\n=== NOTE COACH ===\n{$coach_notes}\n\n";
            }
            $userprompt .= "Genera la SINTESI CONCLUSIVA del rapporto.\n"
                . "Riassumi il profilo, le risorse, la spendibilita e le prospettive di reinserimento. "
                . "Usa i dati del report studente se disponibili.";
            break;

        case 'obs_personal':
        case 'obs_social':
        case 'obs_methodological':
        case 'obs_tic':
            $section_names = [
                'obs_personal' => 'Competenze personali (Impegno, Iniziativa, Autonomia, Puntualita, Modo di presentarsi)',
                'obs_social' => 'Competenze sociali (Capacita di comunicazione, Capacita di comprensione)',
                'obs_methodological' => 'Competenze metodologiche (Ritmo di lavoro, Apprendimento, Risoluzione problemi, Organizzazione, Cura e precisione)',
                'obs_tic' => 'Competenze TIC (Conoscenze PC, Internet, email, scansione, invio documenti)',
            ];
            $section_name = $section_names[$field];

            if (!empty($ratings)) {
                $userprompt .= "\n=== VALUTAZIONI GRIGLIA ===\n";
                $scale = ['', 'Molto buone', 'Buone', 'Sufficienti', 'Insufficienti', 'N.V.'];
                foreach ($ratings as $name => $val) {
                    $label = isset($scale[(int)$val]) ? $scale[(int)$val] : $val;
                    $userprompt .= "- {$name}: {$label}\n";
                }
            }
            if (!empty($coach_notes)) {
                $userprompt .= "\n=== NOTE COACH ===\n{$coach_notes}\n";
            }

            $userprompt .= "\nGenera le OSSERVAZIONI per la sezione: {$section_name}\n"
                . "Scrivi un paragrafo descrittivo (max 200 parole) che commenta le valutazioni della griglia.\n"
                . "Non ripetere semplicemente i valori, ma interpreta e contestualizza le competenze osservate.";
            break;

        case 'obs_search_channels':
            if (!empty($ratings)) {
                $userprompt .= "\n=== CANALI UTILIZZATI ===\n";
                foreach ($ratings as $channel => $used) {
                    $userprompt .= "- {$channel}: " . ($used ? 'Si' : 'No') . "\n";
                }
            }
            $userprompt .= "\nGenera le OSSERVAZIONI sui canali di ricerca impiego utilizzati dal partecipante.";
            break;

        case 'obs_search_evaluation':
            if (!empty($ratings)) {
                $userprompt .= "\n=== VALUTAZIONE RICERCA ===\n";
                foreach ($ratings as $name => $val) {
                    $userprompt .= "- {$name}: {$val}\n";
                }
            }
            if (!empty($coach_notes)) {
                $userprompt .= "\n=== NOTE COACH ===\n{$coach_notes}\n";
            }
            $userprompt .= "\nGenera le OSSERVAZIONI sulla valutazione complessiva della capacita di ricerca d'impiego.";
            break;

        case 'obs_interviews':
            if (!empty($coach_notes)) {
                $userprompt .= "\n=== DATI COLLOQUI ===\n{$coach_notes}\n";
            }
            $userprompt .= "\nGenera le OSSERVAZIONI sui colloqui di lavoro sostenuti durante la misura.";
            break;

        default:
            throw new Exception('Campo non supportato: ' . $field);
    }

    // Call OpenAI.
    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemprompt],
            ['role' => 'user', 'content' => $userprompt],
        ],
        'max_tokens' => 800,
        'temperature' => 0.4,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apikey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 60,
    ]);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('Errore connessione: ' . $error);
    }
    if ($httpcode !== 200) {
        $errordata = json_decode($response, true);
        throw new Exception('OpenAI errore: ' . ($errordata['error']['message'] ?? "HTTP {$httpcode}"));
    }

    $data = json_decode($response, true);
    $text = trim($data['choices'][0]['message']['content'] ?? '');

    if (empty($text)) {
        throw new Exception('Risposta AI vuota');
    }

    echo json_encode([
        'success' => true,
        'text' => $text,
        'field' => $field,
        'tokens' => $data['usage']['total_tokens'] ?? 0,
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
