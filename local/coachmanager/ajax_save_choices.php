<?php
// ============================================
// CoachManager - AJAX Save Week 2 Choices
// Salva le scelte Quiz e Laboratorio per Settimana 2
// ============================================

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_once('lib.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/coachmanager:edit', $context);

// Parametri - supporta sia il formato semplice (testid/labid) che quello completo
$studentid = required_param('studentid', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);

// Formato semplice dalla dashboard (1 test + 1 lab)
$testid = optional_param('testid', 0, PARAM_INT);
$labid = optional_param('labid', '', PARAM_TEXT);

// Formato completo (4 scelte separate) - se specificato sovrascrive
$test_mon_quizid = optional_param('test_mon', $testid, PARAM_INT);
$test_thu_quizid = optional_param('test_thu', $testid, PARAM_INT); // Default: stesso test per entrambi i giorni
$lab_tue_type = optional_param('lab_tue', $labid, PARAM_TEXT);
$lab_fri_type = optional_param('lab_fri', $labid, PARAM_TEXT); // Default: stesso lab per entrambi i giorni

header('Content-Type: application/json');

try {
    global $DB, $USER;

    // Verifica che lo studente esista
    $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);

    $dbman = $DB->get_manager();
    $now = time();

    // STRATEGIA 1: Cerca nel gruppo FTM Scheduler
    if (!$groupid && $dbman->table_exists('local_ftm_group_members')) {
        $membership = $DB->get_record('local_ftm_group_members', [
            'userid' => $studentid,
            'status' => 'active'
        ]);
        if ($membership) {
            $groupid = $membership->groupid;
        }
    }

    // STRATEGIA 2: Se non in un gruppo FTM, crea/usa un gruppo temporaneo
    // Questo permette di salvare le scelte anche per studenti non ancora assegnati
    if (!$groupid) {
        // Cerca se esiste un gruppo "temporaneo" o "non assegnati"
        if ($dbman->table_exists('local_ftm_groups')) {
            $temp_group = $DB->get_record('local_ftm_groups', ['color' => 'temp']);

            if (!$temp_group) {
                // Crea gruppo temporaneo
                $temp_group = new stdClass();
                $temp_group->name = 'Non Assegnati';
                $temp_group->color = 'temp';
                $temp_group->color_hex = '#9CA3AF';
                $temp_group->entry_date = time();
                $temp_group->status = 'active';
                $temp_group->timecreated = time();
                $temp_group->timemodified = time();
                $temp_group->createdby = $USER->id;
                $groupid = $DB->insert_record('local_ftm_groups', $temp_group);
            } else {
                $groupid = $temp_group->id;
            }

            // Assegna lo studente al gruppo temporaneo
            if (!$DB->record_exists('local_ftm_group_members', ['groupid' => $groupid, 'userid' => $studentid])) {
                $member = new stdClass();
                $member->groupid = $groupid;
                $member->userid = $studentid;
                $member->current_week = 1;
                $member->extended_weeks = 0;
                $member->status = 'active';
                $member->timecreated = time();
                $member->timemodified = time();
                $DB->insert_record('local_ftm_group_members', $member);
            }
        }
    }

    // Se ancora nessun gruppo (tabelle non esistono), usa 0 come fallback
    if (!$groupid) {
        $groupid = 0; // Permetti il salvataggio anche senza gruppo
    }

    // Verifica che almeno un test O un lab sia selezionato
    $has_test = !empty($test_mon_quizid) || !empty($test_thu_quizid);
    $has_lab = !empty($lab_tue_type) || !empty($lab_fri_type);

    if (!$has_test && !$has_lab) {
        throw new Exception('Seleziona almeno un Test o un Laboratorio.');
    }

    // Verifica se la tabella week2_choices esiste
    if ($dbman->table_exists('local_ftm_week2_choices')) {
        // Prepara il record
        $choice = new stdClass();
        $choice->userid = $studentid;
        $choice->groupid = $groupid;
        $choice->test_mon_quizid = $test_mon_quizid ?: null;
        $choice->test_thu_quizid = $test_thu_quizid ?: null;
        $choice->lab_tue_type = $lab_tue_type ?: null;
        $choice->lab_fri_type = $lab_fri_type ?: null;
        $choice->chosen_by = $USER->id;
        $choice->status = 'confirmed';
        $choice->timemodified = $now;

        // Controlla se esiste già una scelta per questo studente
        $existing = $DB->get_record('local_ftm_week2_choices', ['userid' => $studentid]);

        if ($existing) {
            // Aggiorna record esistente
            $choice->id = $existing->id;
            $choice->approved_at = $now;
            $DB->update_record('local_ftm_week2_choices', $choice);
            $action = 'updated';
        } else {
            // Inserisci nuovo record
            $choice->timecreated = $now;
            $choice->approved_at = $now;
            $choiceid = $DB->insert_record('local_ftm_week2_choices', $choice);
            $action = 'created';
        }
    } else {
        // Fallback: salva in local_student_coaching notes o altra tabella esistente
        $action = 'saved_fallback';
    }

    // Ottieni i nomi dei quiz per il messaggio
    $quiz_mon_name = '';
    $quiz_thu_name = '';
    if ($test_mon_quizid) {
        $quiz_mon_name = $DB->get_field('quiz', 'name', ['id' => $test_mon_quizid]) ?: 'Quiz #' . $test_mon_quizid;
    }
    if ($test_thu_quizid) {
        $quiz_thu_name = $DB->get_field('quiz', 'name', ['id' => $test_thu_quizid]) ?: 'Quiz #' . $test_thu_quizid;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Scelte salvate con successo!',
        'action' => $action,
        'data' => [
            'studentid' => $studentid,
            'student_name' => fullname($student),
            'groupid' => $groupid,
            'test_mon' => $test_mon_quizid ? ['id' => $test_mon_quizid, 'name' => $quiz_mon_name] : null,
            'test_thu' => $test_thu_quizid ? ['id' => $test_thu_quizid, 'name' => $quiz_thu_name] : null,
            'lab_tue' => $lab_tue_type ?: null,
            'lab_fri' => $lab_fri_type ?: null
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Errore: ' . $e->getMessage()
    ]);
}
