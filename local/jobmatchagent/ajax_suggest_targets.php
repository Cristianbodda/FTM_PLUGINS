<?php
/**
 * AJAX — AI suggerisce aziende target per candidature spontanee.
 *
 * Legge CV + settore studente, recupera aziende dal DB, chiede a GPT
 * le 12 più compatibili con motivazione.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/matcher.php');
require_once(__DIR__ . '/classes/company_manager.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/jobmatchagent:managetargets', $context);

header('Content-Type: application/json; charset=utf-8');
set_time_limit(90);

global $DB;

try {
    $userid = required_param('userid', PARAM_INT);
    $student = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

    // Parametri opzionali dal form inline del modal.
    $sector_input  = optional_param('sector',   '', PARAM_ALPHANUMEXT);
    $cv_text_input = optional_param('cv_text',  '', PARAM_RAW);

    // Settori validi.
    $valid_sectors = ['AUTOMOBILE','AUTOMAZIONE','CHIMFARM','ELETTRICITA','LOGISTICA','MECCANICA','METALCOSTRUZIONE'];

    // --- Salva settore se fornito dal form ---
    if (!empty($sector_input) && in_array($sector_input, $valid_sectors, true)) {
        try {
            if ($DB->get_manager()->table_exists('local_student_sectors')) {
                $existing = $DB->get_record('local_student_sectors', ['userid' => $userid, 'is_primary' => 1]);
                if ($existing) {
                    $existing->sector       = $sector_input;
                    $existing->timemodified = time();
                    $DB->update_record('local_student_sectors', $existing);
                } else {
                    $DB->insert_record('local_student_sectors', (object)[
                        'userid'       => $userid,
                        'sector'       => $sector_input,
                        'is_primary'   => 1,
                        'timecreated'  => time(),
                        'timemodified' => time(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Non-critical: continua anche se il salvataggio settore fallisce.
        }
    }

    // --- Salva CV manuale se fornito dal form ---
    $cv_text  = '';
    $cv_source = 'none';
    if (!empty(trim($cv_text_input))) {
        try {
            if ($DB->get_manager()->table_exists('local_jobmatch_student_filters')) {
                $filters_rec = $DB->get_record('local_jobmatch_student_filters', ['userid' => $userid]);
                if ($filters_rec) {
                    $filters_rec->manual_cv_text = $cv_text_input;
                    $DB->update_record('local_jobmatch_student_filters', $filters_rec);
                } else {
                    $DB->insert_record('local_jobmatch_student_filters', (object)[
                        'userid'        => $userid,
                        'manual_cv_text' => $cv_text_input,
                        'timecreated'   => time(),
                        'timemodified'  => time(),
                    ]);
                }
            }
            $cv_text   = $cv_text_input;
            $cv_source = 'manual';
        } catch (\Throwable $e) {
            // Non-critical.
        }
    }

    // --- Settore studente (usa quello appena salvato o quello già in DB) ---
    $student_sector = !empty($sector_input) && in_array($sector_input, $valid_sectors, true)
        ? $sector_input
        : '';
    if (empty($student_sector)) {
        try {
            $sr = $DB->get_record_sql(
                "SELECT sector FROM {local_student_sectors} WHERE userid = :uid AND is_primary = 1",
                ['uid' => $userid], IGNORE_MISSING
            );
            if ($sr) {
                $student_sector = $sr->sector;
            }
        } catch (\Throwable $e) {
            // Non-critical.
        }
    }

    // --- CV (usa quello appena salvato o quello già in DB/JobAIDA) ---
    if (empty($cv_text)) {
        $cvres     = \local_jobmatchagent\matcher::resolve_cv($userid);
        $cv_text   = $cvres['text'] ?? '';
        $cv_source = $cvres['source'] ?? 'none';
    }

    if (empty($cv_text) && empty($student_sector)) {
        throw new Exception('Imposta almeno il settore oppure incolla il CV nella sezione apposita.');
    }

    // --- Aziende dal DB per settori rilevanti ---
    $sector_map = [
        'METALCOSTRUZIONE' => ['METALCOSTRUZIONE', 'MECCANICA'],
        'MECCANICA'        => ['MECCANICA', 'METALCOSTRUZIONE', 'AUTOMAZIONE'],
        'AUTOMOBILE'       => ['AUTOMOBILE', 'MECCANICA'],
        'AUTOMAZIONE'      => ['AUTOMAZIONE', 'MECCANICA', 'ELETTRICITA'],
        'ELETTRICITA'      => ['ELETTRICITA', 'AUTOMAZIONE'],
        'LOGISTICA'        => ['LOGISTICA'],
        'CHIMFARM'         => ['CHIMFARM'],
    ];
    $sectors_to_search = $sector_map[$student_sector] ?? ($student_sector ? [$student_sector] : array_keys($sector_map));

    $companies_by_id = [];
    foreach ($sectors_to_search as $sec) {
        $comps = \local_jobmatchagent\company_manager::get_companies(['settore_ftm' => $sec], 50);
        foreach ($comps as $c) {
            if (!isset($companies_by_id[$c->id])) {
                $companies_by_id[$c->id] = $c;
            }
        }
    }

    // Se poche aziende, aggiungi tutte le altre.
    if (count($companies_by_id) < 10) {
        $all = \local_jobmatchagent\company_manager::get_companies([], 80);
        foreach ($all as $c) {
            if (!isset($companies_by_id[$c->id])) {
                $companies_by_id[$c->id] = $c;
            }
        }
    }

    if (empty($companies_by_id)) {
        throw new Exception('Nessuna azienda nel database. Importa prima le aziende in "Aziende Ticino".');
    }

    // Costruisce lista aziende per il prompt (max 8000 chars).
    $company_lines = [];
    foreach ($companies_by_id as $c) {
        $desc = !empty($c->settore_raw) ? ' — ' . mb_substr($c->settore_raw, 0, 70) : '';
        $company_lines[] = $c->id . '. ' . $c->nome
            . ' | ' . ($c->settore_ftm ?? '')
            . ' | ' . ($c->localita ?? '')
            . ' | dim:' . ($c->dimensione ?? '?')
            . $desc;
    }
    $companies_text = implode("\n", $company_lines);
    if (mb_strlen($companies_text) > 8000) {
        $companies_text = mb_substr($companies_text, 0, 8000);
    }

    // --- CV excerpt (preserva sezione formazione come in ai_matcher) ---
    $cv_excerpt = '';
    if (!empty($cv_text)) {
        $maxchars = 4000;
        if (mb_strlen($cv_text) <= $maxchars) {
            $cv_excerpt = $cv_text;
        } else {
            $edu_pattern = '/\n[ \t]*(studi|formazione|istruzione|education|qualifiche?|titoli di studio|percorso formativo)/iu';
            if (preg_match($edu_pattern, $cv_text, $match, PREG_OFFSET_CAPTURE)) {
                $edu_start = (int)$match[0][1];
                if ($edu_start > ($maxchars - 500)) {
                    $edu_block  = mb_substr($cv_text, $edu_start, 800);
                    $main_block = mb_substr($cv_text, 0, $maxchars - mb_strlen($edu_block) - 60);
                    $cv_excerpt = $main_block . "\n\n[...]\n\n" . trim($edu_block);
                } else {
                    $cv_excerpt = mb_substr($cv_text, 0, $maxchars);
                }
            } else {
                $cv_excerpt = mb_substr($cv_text, 0, $maxchars);
            }
        }
    }

    $student_name = fullname($student);
    $profile_info = !empty($cv_excerpt)
        ? "PROFILO CANDIDATO ($student_name" . ($student_sector ? ", settore $student_sector" : '') . "):\n$cv_excerpt"
        : "Candidato: $student_name\nSettore primario: $student_sector";

    // --- Prompt GPT ---
    $prompt = "Sei un esperto career coach per il mercato del lavoro ticinese (Canton Ticino, Svizzera).

Analizza il profilo del candidato e seleziona le 12 aziende più compatibili dalla lista per una candidatura spontanea proattiva.

$profile_info

LISTA AZIENDE DISPONIBILI (formato: ID. Nome | Settore | Località | Dimensione | Attività):
$companies_text

Criteri di selezione:
- Compatibilità tra profilo del candidato e attività aziendale (fattore principale)
- Trasferibilità delle competenze specifiche (es. ferroviario → metalcostruzione, carpenteria)
- Le PMI ticinesi apprezzano profili senior con esperienza pratica anche senza AFC formale
- Vicinanza geografica (tutta la regione ticinese è accettabile)
- Score da 65 a 100 (non inserire aziende con compatibilità bassa)

Rispondi SOLO con un oggetto JSON con chiave \"suggerimenti\":
{\"suggerimenti\": [
  {\"company_id\": 42, \"motivo\": \"Motivazione in italiano, 1-2 frasi concrete sul perché il candidato è compatibile\", \"score\": 88},
  ...
]}

Seleziona 10-12 aziende. Nessun testo fuori dall'oggetto JSON.";

    // --- API Key e modello ---
    $api_key = get_config('local_ftm_jobsearch', 'openai_apikey') ?: get_config('local_jobaida', 'openai_apikey');
    if (empty($api_key)) {
        throw new Exception('API key OpenAI non configurata. Vai su Amministrazione → Plugin → JobSearch o JobAIDA → Settings.');
    }
    $model = get_config('local_jobmatchagent', 'openai_model') ?: 'gpt-4o-mini';

    $payload = json_encode([
        'model'           => $model,
        'messages'        => [
            ['role' => 'system', 'content' => 'Sei un esperto career coach ticinese. Rispondi SOLO con JSON valido, nessun markdown.'],
            ['role' => 'user',   'content' => $prompt],
        ],
        'temperature'     => 0.3,
        'max_tokens'      => 1500,
        'response_format' => ['type' => 'json_object'],
    ]);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
        ],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw       = curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 400 || $raw === false) {
        $err_decoded = $raw ? json_decode($raw, true) : null;
        $err_msg     = $err_decoded['error']['message'] ?? ('HTTP ' . $http_code);
        throw new Exception('Errore API OpenAI: ' . $err_msg);
    }

    $decoded = json_decode($raw, true);
    $content = $decoded['choices'][0]['message']['content'] ?? '';
    if (empty($content)) {
        throw new Exception('Risposta AI vuota. Riprova tra un momento.');
    }

    $ai_data = json_decode($content, true);
    if (!is_array($ai_data)) {
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $ai_data = json_decode($m[0], true);
        }
    }

    $suggestions = [];
    if (isset($ai_data['suggerimenti']) && is_array($ai_data['suggerimenti'])) {
        $suggestions = $ai_data['suggerimenti'];
    } elseif (is_array($ai_data) && !empty($ai_data) && isset($ai_data[0])) {
        $suggestions = $ai_data; // Fallback: array diretto.
    }

    if (empty($suggestions)) {
        throw new Exception('AI non ha restituito suggerimenti validi. Riprova o aggiungi più aziende al database.');
    }

    // Arricchisce con dati DB e verifica se già target.
    $results = [];
    foreach ($suggestions as $item) {
        $cid = (int)($item['company_id'] ?? 0);
        if (!$cid || !isset($companies_by_id[$cid])) {
            continue;
        }
        $c       = $companies_by_id[$cid];
        $already = $DB->record_exists('local_jobmatch_student_targets', ['userid' => $userid, 'company_id' => $cid]);
        $results[] = [
            'company_id'     => $cid,
            'nome'           => $c->nome,
            'settore_ftm'    => $c->settore_ftm ?? '',
            'localita'       => $c->localita ?? '',
            'dimensione'     => $c->dimensione ?? 'unknown',
            'website'        => $c->website ?? '',
            'telefono'       => $c->telefono ?? '',
            'motivo'         => $item['motivo'] ?? '',
            'score'          => min(100, max(0, (int)($item['score'] ?? 70))),
            'already_target' => (bool)$already,
        ];
    }

    usort($results, fn($a, $b) => $b['score'] - $a['score']);

    echo json_encode([
        'success'      => true,
        'data'         => $results,
        'cv_source'    => $cv_source,
        'n_companies'  => count($companies_by_id),
        'message'      => '',
    ]);

} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
}

die();
