<?php
/**
 * AJAX endpoint for quiz unlock management.
 *
 * Actions:
 *   getquizzes - List R.comp quizzes with attempt status for a student
 *   unlock     - Create/update quiz_overrides to allow one more attempt
 *
 * @package    local_selfassessment
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();

// Coach or manager can unlock quizzes
if (!has_capability('local/selfassessment:manage', $context) &&
    !has_capability('local/coachmanager:view', $context)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permesso negato.']);
    die();
}

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);
    $studentid = required_param('studentid', PARAM_INT);

    // Verify student exists
    $student = $DB->get_record('user', ['id' => $studentid, 'deleted' => 0], '*', MUST_EXIST);

    // Find R.comp course(s)
    $rcomp_courses = $DB->get_records_select('course', $DB->sql_like('fullname', ':name'), ['name' => '%R.comp%']);
    if (empty($rcomp_courses)) {
        echo json_encode(['success' => false, 'message' => 'Nessun corso R.comp trovato.']);
        die();
    }
    $courseids = array_keys($rcomp_courses);

    // Get student's assigned sectors (used to filter quizzes).
    $student_sector_rows = $DB->get_records('local_student_sectors', ['userid' => $studentid], 'is_primary DESC', 'sector');
    $student_sectors = array_map('strtoupper', array_column($student_sector_rows, 'sector'));

    // Sector alias map — keeps quiz visibility correct when sector codes have variants.
    $sector_aliases = [
        'AUTOMOBILE'       => ['AUTOMOBILE', 'AUTOVEICOLO'],
        'AUTOMAZIONE'      => ['AUTOMAZIONE', 'AUTOM', 'AUTOMAZ'],
        'CHIMFARM'         => ['CHIMFARM', 'CHIM', 'CHIMICA', 'FARMACEUTICA'],
        'ELETTRICITA'      => ['ELETTRICITA', 'ELETTR', 'ELETT'],
        'LOGISTICA'        => ['LOGISTICA', 'LOG'],
        'MECCANICA'        => ['MECCANICA', 'MECC'],
        'METALCOSTRUZIONE' => ['METALCOSTRUZIONE', 'METAL'],
        'GENERICO'         => ['GENERICO', 'GEN'],
    ];

    // Collect all prefixes allowed for this student.
    $allowed_prefixes = [];
    foreach ($student_sectors as $sec) {
        if (isset($sector_aliases[$sec])) {
            foreach ($sector_aliases[$sec] as $alias) {
                $allowed_prefixes[] = strtoupper($alias);
            }
        } else {
            $allowed_prefixes[] = $sec;
        }
    }

    switch ($action) {
        case 'getquizzes':
            list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
            $all_quizzes = $DB->get_records_select('quiz', "course $insql", $params, 'name ASC');

            // --- Extract all sector prefixes present in R.comp quizzes ---
            // A quiz name has format "PREFIX - descrizione"; split on ' - ' and take first part uppercase.
            $all_quiz_prefixes = [];
            foreach ($all_quizzes as $q) {
                $parts = explode(' - ', $q->name, 2);
                $prefix = strtoupper(trim($parts[0]));
                if ($prefix !== '') {
                    $all_quiz_prefixes[$prefix] = true;
                }
            }

            // Filter by student sector: quiz name must start with one of the allowed prefixes.
            // If the student has no sector assigned, show all (fallback).
            if (!empty($allowed_prefixes)) {
                $quizzes = array_filter($all_quizzes, function($quiz) use ($allowed_prefixes) {
                    $upper_name = strtoupper($quiz->name);
                    foreach ($allowed_prefixes as $prefix) {
                        if (strpos($upper_name, $prefix) === 0) {
                            return true;
                        }
                    }
                    return false;
                });
            } else {
                $quizzes = $all_quizzes;
            }

            $result = [];
            foreach ($quizzes as $quiz) {
                // Count finished attempts for this student
                $finished = $DB->count_records('quiz_attempts', [
                    'quiz' => $quiz->id,
                    'userid' => $studentid,
                    'state' => 'finished',
                ]);

                // Check for existing override
                $override = $DB->get_record('quiz_overrides', [
                    'quiz' => $quiz->id,
                    'userid' => $studentid,
                ]);

                // Effective max attempts: override takes priority
                $max_attempts = $override ? (int)$override->attempts : (int)$quiz->attempts;
                // attempts=0 means unlimited
                $is_unlimited = ($max_attempts === 0);

                if ($is_unlimited) {
                    $status = 'unlimited';
                } else if ($finished >= $max_attempts) {
                    $status = 'blocked';
                } else {
                    $status = 'free';
                }

                // If there's an override with more attempts than the quiz default
                $has_override = false;
                if ($override && (int)$quiz->attempts > 0 && (int)$override->attempts > (int)$quiz->attempts) {
                    $has_override = true;
                }

                $result[] = [
                    'quizid' => (int)$quiz->id,
                    'name' => $quiz->name,
                    'finished' => (int)$finished,
                    'max_attempts' => $max_attempts,
                    'status' => $status,
                    'has_override' => $has_override,
                    'override_attempts' => $override ? (int)$override->attempts : null,
                ];
            }

            // --- Settori sbloccati via PIN per questo studente ---
            $pinned_rows = $DB->get_records_select(
                'local_student_sectors',
                "userid = :uid AND source = 'pin_unlock'",
                ['uid' => $studentid],
                'timecreated ASC'
            );
            $pinned_sectors = [];
            foreach ($pinned_rows as $row) {
                $pinned_sectors[] = [
                    'sector'      => strtoupper($row->sector),
                    'unlocked_by' => $row->unlocked_by ?? '',
                    'unlock_time' => $row->timecreated > 0 ? date('H:i', $row->timecreated) : '',
                ];
            }

            // --- Settori disponibili ma non ancora assegnati allo studente ---
            // Usa i prefissi trovati nei quiz R.comp, esclude quelli già in $allowed_prefixes.
            // Mappa inversa alias → settore canonico per confronto normalizzato.
            $available_sectors = [];
            foreach (array_keys($all_quiz_prefixes) as $qprefix) {
                // Controlla se questo prefisso è già coperto dai settori assegnati allo studente.
                $already_allowed = false;
                foreach ($allowed_prefixes as $ap) {
                    if ($ap === $qprefix) {
                        $already_allowed = true;
                        break;
                    }
                }
                if (!$already_allowed) {
                    $available_sectors[] = $qprefix;
                }
            }
            sort($available_sectors);

            echo json_encode([
                'success'           => true,
                'quizzes'           => $result,
                'pinned_sectors'    => $pinned_sectors,
                'available_sectors' => $available_sectors,
            ]);
            break;

        case 'unlock':
            $quizid = required_param('quizid', PARAM_INT);

            // Verify quiz exists and belongs to R.comp
            $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
            if (!in_array($quiz->course, $courseids)) {
                echo json_encode(['success' => false, 'message' => 'Il quiz non appartiene a R.comp.']);
                die();
            }

            // Count current finished attempts
            $finished = $DB->count_records('quiz_attempts', [
                'quiz' => $quizid,
                'userid' => $studentid,
                'state' => 'finished',
            ]);

            // New max = finished + 1 (allow exactly one more attempt)
            $new_max = $finished + 1;

            // Check for existing override
            $existing = $DB->get_record('quiz_overrides', [
                'quiz' => $quizid,
                'userid' => $studentid,
            ]);

            if ($existing) {
                $existing->attempts = $new_max;
                $existing->timemodified = time();
                $DB->update_record('quiz_overrides', $existing);
            } else {
                $override = new stdClass();
                $override->quiz = $quizid;
                $override->userid = $studentid;
                $override->attempts = $new_max;
                $override->timeopen = null;
                $override->timeclose = null;
                $override->timelimit = null;
                $override->password = null;
                $override->timemodified = time();
                $DB->insert_record('quiz_overrides', $override);
            }

            // Log
            $eventdata = [
                'context' => context_course::instance($quiz->course),
                'relateduserid' => $studentid,
                'other' => [
                    'quizid' => $quizid,
                    'quizname' => $quiz->name,
                    'new_attempts' => $new_max,
                    'unlocked_by' => $USER->id,
                ],
            ];

            echo json_encode([
                'success' => true,
                'message' => 'Quiz sbloccato! ' . fullname($student) . ' puo\' ora fare il tentativo #' . $new_max . '.',
                'new_max' => $new_max,
                'finished' => $finished,
            ]);
            break;

        case 'unlock_sector':
            $sector = strtoupper(required_param('sector', PARAM_ALPHANUMEXT));
            $pin    = strtoupper(required_param('pin', PARAM_ALPHANUMEXT));

            // Validazione PIN: ultimi 2 char = anno corrente (es. "26"), primi char = sigla coach
            $current_year_2 = substr(date('Y'), 2); // es. "26"
            $pin_year   = substr($pin, -2);
            $pin_initials = substr($pin, 0, strlen($pin) - 2);

            if ($pin_year !== $current_year_2) {
                echo json_encode(['success' => false, 'message' => 'PIN non valido: anno errato.']);
                die();
            }
            if (empty($pin_initials)) {
                echo json_encode(['success' => false, 'message' => 'PIN non valido: sigla coach mancante.']);
                die();
            }

            // Verifica che la sigla corrisponda a un coach attivo in local_ftm_coaches.
            $coach_record = $DB->get_record('local_ftm_coaches', [
                'initials' => $pin_initials,
                'active'   => 1,
            ]);
            if (!$coach_record) {
                echo json_encode(['success' => false, 'message' => 'PIN non valido: sigla coach non riconosciuta.']);
                die();
            }

            // Carica nome coach per il messaggio di ritorno.
            $coach_user = $DB->get_record('user', ['id' => $coach_record->userid], 'id, firstname, lastname');
            $coach_name = $coach_user ? fullname($coach_user) : $pin_initials;

            // Verifica che il settore non sia già assegnato allo studente (primario o secondario).
            // Confronta usando il campo sector in uppercase.
            $existing_sector = $DB->get_record_select(
                'local_student_sectors',
                'userid = :uid AND ' . $DB->sql_compare_text('sector') . ' = ' . $DB->sql_compare_text(':sec'),
                ['uid' => $studentid, 'sec' => $sector]
            );
            if ($existing_sector) {
                echo json_encode(['success' => false, 'message' => 'Il settore ' . $sector . ' e\' già assegnato allo studente.']);
                die();
            }

            // Inserisci il record pin_unlock in local_student_sectors.
            $now = time();
            $new_sector = new stdClass();
            $new_sector->userid        = $studentid;
            $new_sector->courseid      = 0;
            $new_sector->sector        = $sector;
            $new_sector->is_primary    = 0;
            $new_sector->source        = 'pin_unlock';
            $new_sector->unlocked_by   = $pin_initials;
            $new_sector->quiz_count    = 0;
            $new_sector->first_detected = $now;
            $new_sector->last_detected  = $now;
            $new_sector->timecreated   = $now;
            $new_sector->timemodified  = $now;
            $DB->insert_record('local_student_sectors', $new_sector);

            echo json_encode([
                'success'    => true,
                'message'    => 'Settore ' . $sector . ' sbloccato da ' . $coach_name . '.',
                'coach_name' => $coach_name,
                'sector'     => $sector,
            ]);
            break;

        case 'revoke_sector':
            $sector = strtoupper(required_param('sector', PARAM_ALPHANUMEXT));

            // Cancella SOLO il record pin_unlock per questo studente e settore. Mai toccare is_primary=1.
            $to_delete = $DB->get_record_select(
                'local_student_sectors',
                "userid = :uid AND source = 'pin_unlock' AND " .
                $DB->sql_compare_text('sector') . ' = ' . $DB->sql_compare_text(':sec'),
                ['uid' => $studentid, 'sec' => $sector]
            );

            if (!$to_delete) {
                echo json_encode(['success' => false, 'message' => 'Settore ' . $sector . ' non trovato tra i PIN sbloccati.']);
                die();
            }

            if ((int)$to_delete->is_primary === 1) {
                echo json_encode(['success' => false, 'message' => 'Non e\' possibile revocare un settore primario.']);
                die();
            }

            $DB->delete_records('local_student_sectors', ['id' => $to_delete->id]);

            echo json_encode([
                'success' => true,
                'message' => 'Settore ' . $sector . ' revocato.',
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Azione non valida: ' . $action]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
