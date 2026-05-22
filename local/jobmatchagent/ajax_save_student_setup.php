<?php
/**
 * AJAX — Salva settore e/o CV manuale per uno studente.
 *
 * Actions: save_sector | save_cv
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/jobmatchagent:managetargets', $context);

header('Content-Type: application/json; charset=utf-8');

global $DB;

$valid_sectors = ['AUTOMOBILE','AUTOMAZIONE','CHIMFARM','ELETTRICITA','LOGISTICA','MECCANICA','METALCOSTRUZIONE'];

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);
    $userid = required_param('userid', PARAM_INT);

    // Verifica che lo studente esista.
    if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
        throw new Exception('Studente non trovato.');
    }

    if ($action === 'save_sector') {
        $sector = required_param('sector', PARAM_ALPHANUMEXT);
        if (!in_array($sector, $valid_sectors, true)) {
            throw new Exception('Settore non valido.');
        }

        if (!$DB->get_manager()->table_exists('local_student_sectors')) {
            throw new Exception('Tabella local_student_sectors non disponibile.');
        }

        $existing = $DB->get_record('local_student_sectors', ['userid' => $userid, 'is_primary' => 1]);
        if ($existing) {
            $existing->sector       = $sector;
            $existing->timemodified = time();
            $DB->update_record('local_student_sectors', $existing);
        } else {
            $DB->insert_record('local_student_sectors', (object)[
                'userid'       => $userid,
                'sector'       => $sector,
                'is_primary'   => 1,
                'timecreated'  => time(),
                'timemodified' => time(),
            ]);
        }

        echo json_encode(['success' => true, 'message' => 'Settore salvato: ' . $sector, 'sector' => $sector]);

    } elseif ($action === 'save_cv') {
        $cv_text = required_param('cv_text', PARAM_RAW);
        $cv_text = trim($cv_text);

        if (empty($cv_text)) {
            throw new Exception('Testo CV vuoto.');
        }

        if (!$DB->get_manager()->table_exists('local_jobmatch_student_filters')) {
            throw new Exception('Tabella local_jobmatch_student_filters non disponibile. Imposta prima i filtri studente dalla dashboard.');
        }

        $filters = $DB->get_record('local_jobmatch_student_filters', ['userid' => $userid]);
        if ($filters) {
            $filters->manual_cv_text = $cv_text;
            $filters->timemodified   = time();
            $DB->update_record('local_jobmatch_student_filters', $filters);
        } else {
            $DB->insert_record('local_jobmatch_student_filters', (object)[
                'userid'         => $userid,
                'manual_cv_text' => $cv_text,
                'timecreated'    => time(),
                'timemodified'   => time(),
            ]);
        }

        $word_count = str_word_count($cv_text);
        echo json_encode([
            'success'    => true,
            'message'    => 'CV salvato (' . $word_count . ' parole).',
            'word_count' => $word_count,
        ]);

    } else {
        throw new Exception('Azione non supportata.');
    }

} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
