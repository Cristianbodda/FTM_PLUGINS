<?php
/**
 * Generate Coach Demo - Creates 3 coaches + 9 demo students with quiz data.
 *
 * Creates:
 * - 3 coach users (Roberto Bravo, Fabio Marinoni, Graziano Margonar)
 * - 9 demo students (3 per coach, at 30%/65%/95% profiles)
 * - Enrolls everyone in all courses with quizzes
 * - Generates quiz attempts at correct percentages
 * - Generates self-assessments
 * - Creates CPURC records
 * - Assigns students to their coach
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/data_generator.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/generate_coach_demo.php'));
$PAGE->set_title('Genera Demo Coach');
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHA);

echo $OUTPUT->header();
echo '<div style="max-width:900px; margin:0 auto; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">';
echo '<h2 style="color:#1e3c72;">🎓 Genera Demo Coach + Studenti</h2>';

// ========================
// CONFIGURATION
// ========================
$coaches = [
    'RB' => [
        'firstname' => 'Roberto',
        'lastname' => 'Bravo',
        'username' => 'roberto.bravo',
        'email' => 'roberto.bravo@ftm.local',
        'password' => '123Roberto*',
    ],
    'FM' => [
        'firstname' => 'Fabio',
        'lastname' => 'Marinoni',
        'username' => 'fabio.marinoni',
        'email' => 'fabio.marinoni@ftm.local',
        'password' => '123Fabio*',
    ],
    'GM' => [
        'firstname' => 'Graziano',
        'lastname' => 'Margonar',
        'username' => 'graziano.margonar',
        'email' => 'graziano.margonar@ftm.local',
        'password' => '123Graziano*',
    ],
];

$profiles = [30, 65, 95];

// ========================
// CONFIRMATION FORM
// ========================
if ($action !== 'generate' && $action !== 'cleanup') {
    echo '<div style="background:#e3f2fd; padding:20px; border-radius:12px; margin-bottom:20px;">';
    echo '<h3>Cosa verra creato:</h3>';
    echo '<ul>';
    echo '<li><strong>3 Coach:</strong> Roberto Bravo (RB), Fabio Marinoni (FM), Graziano Margonar (GM)</li>';
    echo '<li><strong>9 Studenti Demo:</strong> 3 per ogni coach (30%, 65%, 95%)</li>';
    echo '<li><strong>Iscrizioni:</strong> Tutti iscritti ai corsi con quiz</li>';
    echo '<li><strong>Tentativi Quiz:</strong> Generati con le percentuali corrette</li>';
    echo '<li><strong>Autovalutazioni:</strong> Bloom coerente con profilo</li>';
    echo '<li><strong>Record CPURC:</strong> Per ogni studente</li>';
    echo '<li><strong>Assegnazione Coach:</strong> Ogni studente al suo coach</li>';
    echo '</ul>';

    echo '<table style="width:100%; border-collapse:collapse; margin:15px 0;">';
    echo '<tr style="background:#1e3c72; color:white;"><th style="padding:8px;">Coach</th><th style="padding:8px;">Username</th><th style="padding:8px;">Password</th><th style="padding:8px;">Studenti</th></tr>';
    foreach ($coaches as $initials => $c) {
        echo '<tr style="border-bottom:1px solid #ddd;">';
        echo '<td style="padding:8px;"><strong>' . $c['firstname'] . ' ' . $c['lastname'] . '</strong> (' . $initials . ')</td>';
        echo '<td style="padding:8px;"><code>' . $c['username'] . '</code></td>';
        echo '<td style="padding:8px;"><code>' . $c['password'] . '</code></td>';
        echo '<td style="padding:8px;">';
        foreach ($profiles as $pct) {
            echo $c['firstname'] . ' Test ' . $pct . '<br>';
        }
        echo '</td></tr>';
    }
    echo '</table>';
    echo '</div>';

    echo '<form method="post" style="display:flex; gap:10px;">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<input type="hidden" name="action" value="generate">';
    echo '<button type="submit" style="background:#28a745; color:white; border:none; padding:15px 30px; border-radius:8px; font-size:16px; font-weight:600; cursor:pointer;">✅ Genera Tutto</button>';
    echo '</form>';

    echo '<form method="post" style="margin-top:15px;">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<input type="hidden" name="action" value="cleanup">';
    echo '<button type="submit" style="background:#dc3545; color:white; border:none; padding:10px 20px; border-radius:8px; font-size:14px; cursor:pointer;" onclick="return confirm(\'Sei sicuro? Elimina tutti i coach demo e studenti demo.\')">🗑️ Pulisci Demo Precedente</button>';
    echo '</form>';

    echo '<p style="margin-top:15px;"><a href="' . (new moodle_url('/local/ftm_testsuite/index.php'))->out() . '">← Torna alla Test Suite</a></p>';
    echo '</div>';
    echo $OUTPUT->footer();
    die();
}

// ========================
// REQUIRE SESSKEY
// ========================
require_sesskey();

// ========================
// CLEANUP ACTION
// ========================
if ($action === 'cleanup') {
    echo '<h3>🗑️ Pulizia Demo...</h3>';
    $cleaned = 0;

    foreach ($coaches as $initials => $cdata) {
        foreach ($profiles as $pct) {
            $username = 'demo_' . strtolower($initials) . '_' . $pct;
            $user = $DB->get_record('user', ['username' => $username]);
            if ($user) {
                // Delete quiz attempts.
                $attempts = $DB->get_records('quiz_attempts', ['userid' => $user->id]);
                foreach ($attempts as $a) {
                    $qas = $DB->get_records('question_attempts', ['questionusageid' => $a->uniqueid]);
                    foreach ($qas as $qa) {
                        $DB->delete_records('question_attempt_step_data', ['attemptstepid' => $qa->id]);
                        $DB->delete_records('question_attempt_steps', ['questionattemptid' => $qa->id]);
                    }
                    $DB->delete_records('question_attempts', ['questionusageid' => $a->uniqueid]);
                    $DB->delete_records('question_usages', ['id' => $a->uniqueid]);
                    $DB->delete_records('quiz_attempts', ['id' => $a->id]);
                }
                // Delete selfassessments.
                $DB->delete_records('local_selfassessment', ['userid' => $user->id]);
                // Delete coaching record.
                $DB->delete_records('local_student_coaching', ['userid' => $user->id]);
                // Delete CPURC record.
                $DB->delete_records('local_ftm_cpurc_students', ['userid' => $user->id]);
                // Delete quiz grades.
                $DB->delete_records('quiz_grades', ['userid' => $user->id]);

                echo "<p>🗑️ Puliti dati per <strong>{$user->firstname} {$user->lastname}</strong></p>";
                $cleaned++;
            }
        }
    }

    echo "<p style='color:green; font-weight:bold;'>Pulizia completata: $cleaned studenti puliti.</p>";
    echo '<p><a href="generate_coach_demo.php">← Torna</a></p>';
    echo '</div>';
    echo $OUTPUT->footer();
    die();
}

// ========================
// GENERATE ACTION
// ========================
echo '<div style="background:#f8f9fa; padding:20px; border-radius:12px; font-family:monospace; font-size:13px; line-height:1.8;">';

$mnethostid = $DB->get_field('mnet_host', 'id', ['wwwroot' => $CFG->wwwroot]);
$studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
$teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
$now = time();
$date_start = $now;
$date_end = $now + (6 * 7 * 86400);

// Get all courses with quizzes.
$courses = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.fullname
    FROM {course} c
    JOIN {quiz} q ON q.course = c.id
    WHERE c.id > 1
    ORDER BY c.fullname
");

// ========================
// STEP 1: CREATE COACHES
// ========================
echo '<strong>== STEP 1: Creazione Coach ==</strong><br>';
$coach_userids = [];

foreach ($coaches as $initials => $cdata) {
    $user = $DB->get_record('user', ['username' => $cdata['username']]);
    if (!$user) {
        $user = new stdClass();
        $user->username = $cdata['username'];
        $user->firstname = $cdata['firstname'];
        $user->lastname = $cdata['lastname'];
        $user->email = $cdata['email'];
        $user->password = hash_internal_user_password($cdata['password']);
        $user->confirmed = 1;
        $user->mnethostid = $mnethostid;
        $user->timecreated = $now;
        $user->timemodified = $now;
        $user->id = $DB->insert_record('user', $user);
        echo "✅ Coach creato: <strong>{$cdata['firstname']} {$cdata['lastname']}</strong> (username: {$cdata['username']}, password: {$cdata['password']})<br>";
    } else {
        echo "⏭️ Coach esistente: <strong>{$user->firstname} {$user->lastname}</strong> (id={$user->id})<br>";
    }

    $coach_userids[$initials] = $user->id;

    // Register in local_ftm_coaches.
    $existing_coach = $DB->get_record('local_ftm_coaches', ['initials' => $initials]);
    if (!$existing_coach) {
        $coach_record = new stdClass();
        $coach_record->userid = $user->id;
        $coach_record->initials = $initials;
        $coach_record->role = 'coach';
        $coach_record->can_week2_mon_tue = 1;
        $coach_record->can_week2_thu_fri = 1;
        $coach_record->active = 1;
        $DB->insert_record('local_ftm_coaches', $coach_record);
        echo "  📋 Registrato in local_ftm_coaches ($initials)<br>";
    } else if ($existing_coach->userid != $user->id) {
        $existing_coach->userid = $user->id;
        $DB->update_record('local_ftm_coaches', $existing_coach);
        echo "  🔄 Aggiornato userid in local_ftm_coaches ($initials)<br>";
    }

    // Enroll as editingteacher in all courses.
    foreach ($courses as $course) {
        $context = context_course::instance($course->id);
        if (!is_enrolled($context, $user->id)) {
            $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual', 'status' => 0]);
            if (!$enrol) {
                $enrol = new stdClass();
                $enrol->enrol = 'manual';
                $enrol->courseid = $course->id;
                $enrol->status = 0;
                $enrol->roleid = $teacherroleid;
                $enrol->timecreated = $now;
                $enrol->timemodified = $now;
                $enrol->id = $DB->insert_record('enrol', $enrol);
            }
            $ue = new stdClass();
            $ue->enrolid = $enrol->id;
            $ue->userid = $user->id;
            $ue->status = 0;
            $ue->timestart = 0;
            $ue->timeend = 0;
            $ue->timecreated = $now;
            $ue->timemodified = $now;
            if (!$DB->record_exists('user_enrolments', ['enrolid' => $enrol->id, 'userid' => $user->id])) {
                $DB->insert_record('user_enrolments', $ue);
            }
            role_assign($teacherroleid, $user->id, $context->id);
        }
    }
    echo "  🎓 Iscritto come editingteacher in " . count($courses) . " corsi<br>";
}

// ========================
// STEP 2: CREATE DEMO STUDENTS
// ========================
echo '<br><strong>== STEP 2: Creazione Studenti Demo ==</strong><br>';
$student_data = []; // username => ['userid' => ..., 'percentage' => ..., 'coach_initials' => ...]

foreach ($coaches as $initials => $cdata) {
    foreach ($profiles as $pct) {
        $username = 'demo_' . strtolower($initials) . '_' . $pct;
        $firstname = $cdata['firstname'];
        $lastname = 'Test ' . $pct;
        $email = $username . '@ftm.local';

        $user = $DB->get_record('user', ['username' => $username]);
        if (!$user) {
            $user = new stdClass();
            $user->username = $username;
            $user->firstname = $firstname;
            $user->lastname = $lastname;
            $user->email = $email;
            $user->password = hash_internal_user_password('FtmDemo2026!');
            $user->confirmed = 1;
            $user->mnethostid = $mnethostid;
            $user->timecreated = $now;
            $user->timemodified = $now;
            $user->id = $DB->insert_record('user', $user);
            echo "✅ Studente creato: <strong>{$firstname} {$lastname}</strong> ({$username}) → Coach {$initials}<br>";
        } else {
            echo "⏭️ Studente esistente: <strong>{$user->firstname} {$user->lastname}</strong> (id={$user->id})<br>";
        }

        $student_data[$username] = [
            'userid' => $user->id,
            'percentage' => $pct,
            'coach_initials' => $initials,
            'firstname' => $firstname,
            'lastname' => $lastname,
        ];

        // Enroll as student in all courses.
        foreach ($courses as $course) {
            $context = context_course::instance($course->id);
            $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual', 'status' => 0]);
            if ($enrol && !$DB->record_exists('user_enrolments', ['enrolid' => $enrol->id, 'userid' => $user->id])) {
                $ue = new stdClass();
                $ue->enrolid = $enrol->id;
                $ue->userid = $user->id;
                $ue->status = 0;
                $ue->timestart = 0;
                $ue->timeend = 0;
                $ue->timecreated = $now;
                $ue->timemodified = $now;
                $DB->insert_record('user_enrolments', $ue);
                role_assign($studentroleid, $user->id, $context->id);
            }
        }

        // Create CPURC record.
        if (!$DB->record_exists('local_ftm_cpurc_students', ['userid' => $user->id])) {
            $cpurc = new stdClass();
            $cpurc->userid = $user->id;
            $cpurc->cpurc_id = 'DEMO-' . strtoupper($initials) . '-' . $pct;
            $cpurc->gender = 'M';
            $cpurc->trainer = $initials;
            $cpurc->measure = 'FTM';
            $cpurc->urc_office = 'Bellinzona';
            $cpurc->status = 'In corso';
            $cpurc->last_profession = 'Demo';
            $cpurc->date_start = $date_start;
            $cpurc->date_end_planned = $date_end;
            $cpurc->nationality = 'CH';
            $cpurc->occupation_grade = 100;
            $cpurc->timecreated = $now;
            $cpurc->timemodified = $now;
            $DB->insert_record('local_ftm_cpurc_students', $cpurc);
        }

        // Assign to coach (local_student_coaching).
        $coaching = $DB->get_record('local_student_coaching', ['userid' => $user->id]);
        if (!$coaching) {
            $coaching = new stdClass();
            $coaching->userid = $user->id;
            $coaching->coachid = $coach_userids[$initials];
            $coaching->courseid = 0;
            $coaching->sector = '';
            $coaching->date_start = $date_start;
            $coaching->date_end = $date_end;
            $coaching->current_week = 1;
            $coaching->status = 'active';
            $coaching->timecreated = $now;
            $coaching->timemodified = $now;
            $DB->insert_record('local_student_coaching', $coaching);
        }
    }
}

// ========================
// STEP 3: GENERATE QUIZ ATTEMPTS
// ========================
echo '<br><strong>== STEP 3: Generazione Tentativi Quiz ==</strong><br>';
$quiz_count = 0;

foreach ($courses as $course) {
    $quizzes = $DB->get_records('quiz', ['course' => $course->id]);
    if (empty($quizzes)) {
        continue;
    }

    echo "📚 Corso: <strong>{$course->fullname}</strong> (" . count($quizzes) . " quiz)<br>";

    foreach ($quizzes as $quiz) {
        // Get quiz questions.
        $questions = $DB->get_records_sql("
            SELECT DISTINCT qv.questionid, q.qtype, q.name, qs.slot, qs.maxmark
            FROM {quiz_slots} qs
            JOIN {question_references} qr ON qr.itemid = qs.id
                AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
            JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
            JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            JOIN {question} q ON q.id = qv.questionid
            WHERE qs.quizid = ?
            ORDER BY qs.slot
        ", [$quiz->id]);

        if (empty($questions)) {
            continue;
        }

        foreach ($student_data as $username => $sdata) {
            // Skip if attempt already exists.
            if ($DB->record_exists('quiz_attempts', ['quiz' => $quiz->id, 'userid' => $sdata['userid'], 'state' => 'finished'])) {
                continue;
            }

            try {
                $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
                $context = context_module::instance($cm->id);

                $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $context);
                $quba->set_preferred_behaviour('deferredfeedback');

                foreach ($questions as $q) {
                    $question = question_bank::load_question($q->questionid);
                    $quba->add_question($question, $q->maxmark);
                }

                $quba->start_all_questions();
                question_engine::save_questions_usage_by_activity($quba);

                // Create quiz attempt record.
                $attempt = new stdClass();
                $attempt->quiz = $quiz->id;
                $attempt->userid = $sdata['userid'];
                $attempt->attempt = 1;
                $attempt->uniqueid = $quba->get_id();
                $attempt->layout = implode(',', array_keys($questions));
                $attempt->currentpage = 0;
                $attempt->preview = 0;
                $attempt->state = 'inprogress';
                $attempt->timestart = $now - 3600;
                $attempt->timefinish = 0;
                $attempt->timemodified = $now;
                $attempt->timemodifiedoffline = 0;
                $attempt->sumgrades = null;
                $attempt->id = $DB->insert_record('quiz_attempts', $attempt);

                // Process answers.
                $target_correct = $sdata['percentage'] / 100;
                $slot = 1;
                foreach ($questions as $q) {
                    $rand = mt_rand(1, 100) / 100;
                    $is_correct = ($rand <= $target_correct);
                    $response = generate_demo_response($q, $is_correct, $quba, $slot);
                    if ($response !== null) {
                        $quba->process_action($slot, $response);
                    }
                    $slot++;
                }

                // Finish and grade.
                $quba->finish_all_questions();
                question_engine::save_questions_usage_by_activity($quba);

                $attempt->state = 'finished';
                $attempt->timefinish = $now;
                $attempt->sumgrades = $quba->get_total_mark();
                $DB->update_record('quiz_attempts', $attempt);

                quiz_save_best_grade($quiz, $sdata['userid']);
                $quiz_count++;

            } catch (Exception $e) {
                echo "  ⚠️ Errore quiz '{$quiz->name}' per {$username}: " . $e->getMessage() . "<br>";
            }
        }
    }
}
echo "✅ Generati <strong>{$quiz_count}</strong> tentativi quiz<br>";

// ========================
// STEP 4: GENERATE SELF-ASSESSMENTS
// ========================
echo '<br><strong>== STEP 4: Generazione Autovalutazioni ==</strong><br>';
$sa_count = 0;

foreach ($courses as $course) {
    $competencies = $DB->get_records_sql("
        SELECT DISTINCT c.id, c.shortname, c.idnumber
        FROM {quiz} q
        JOIN {quiz_slots} qs ON qs.quizid = q.id
        JOIN {question_references} qr ON qr.itemid = qs.id
            AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
        JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
        JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
        JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
        JOIN {competency} c ON c.id = qc.competencyid
        WHERE q.course = ?
    ", [$course->id]);

    if (empty($competencies)) {
        continue;
    }

    foreach ($student_data as $username => $sdata) {
        $bloom_range = match(true) {
            $sdata['percentage'] <= 30 => [4, 6],
            $sdata['percentage'] <= 65 => [3, 5],
            default => [2, 4],
        };

        $student_sa = 0;
        foreach ($competencies as $comp) {
            if ($DB->record_exists('local_selfassessment', ['userid' => $sdata['userid'], 'competencyid' => $comp->id])) {
                continue;
            }
            $sa = new stdClass();
            $sa->userid = $sdata['userid'];
            $sa->competencyid = $comp->id;
            $sa->level = mt_rand($bloom_range[0], $bloom_range[1]);
            $sa->timecreated = $now;
            $sa->timemodified = $now;
            $DB->insert_record('local_selfassessment', $sa);
            $student_sa++;
            $sa_count++;
        }
        if ($student_sa > 0) {
            echo "  🧠 {$username}: {$student_sa} autovalutazioni<br>";
        }
    }
}
echo "✅ Generate <strong>{$sa_count}</strong> autovalutazioni totali<br>";

// ========================
// DONE
// ========================
echo '<br><strong style="color:#28a745; font-size:16px;">🎉 Generazione completata!</strong><br>';
echo '</div>';

echo '<div style="margin-top:20px; padding:15px; background:#d4edda; border-radius:8px;">';
echo '<h3 style="margin-top:0;">Riepilogo</h3>';
echo '<ul>';
echo '<li><strong>' . count($coaches) . '</strong> Coach creati/aggiornati</li>';
echo '<li><strong>' . count($student_data) . '</strong> Studenti demo creati</li>';
echo '<li><strong>' . $quiz_count . '</strong> Tentativi quiz generati</li>';
echo '<li><strong>' . $sa_count . '</strong> Autovalutazioni generate</li>';
echo '</ul>';
echo '<p><a href="' . (new moodle_url('/local/ftm_cpurc/index.php'))->out() . '" style="margin-right:10px;">📊 Dashboard CPURC</a>';
echo '<a href="' . (new moodle_url('/local/coachmanager/coach_dashboard_v2.php'))->out() . '" style="margin-right:10px;">👨‍🏫 Coach Dashboard</a>';
echo '<a href="' . (new moodle_url('/local/ftm_testsuite/admin_quiz_tester.php'))->out() . '">🎯 Admin Quiz Tester</a></p>';
echo '</div>';

echo '<p style="margin-top:15px;"><a href="' . (new moodle_url('/local/ftm_testsuite/index.php'))->out() . '">← Torna alla Test Suite</a></p>';
echo '</div>';
echo $OUTPUT->footer();

// ========================
// HELPER FUNCTION
// ========================

/**
 * Generate response for a question (same logic as fixed data_generator).
 */
function generate_demo_response($question, $correct, $quba, $slot) {
    global $DB;

    switch ($question->qtype) {
        case 'multichoice':
            $answers = $DB->get_records('question_answers', ['question' => $question->questionid]);
            $target_id = null;
            $fallback_id = null;
            foreach ($answers as $a) {
                if ($correct && $a->fraction > 0) {
                    $target_id = $a->id;
                    break;
                } else if (!$correct && $a->fraction == 0) {
                    $target_id = $a->id;
                    break;
                }
                $fallback_id = $a->id;
            }
            if (!$target_id) {
                $target_id = $fallback_id;
            }
            // Get shuffled order from question attempt.
            $qa = $quba->get_question_attempt($slot);
            $order = $qa->get_step(0)->get_qt_data();
            if (isset($order['_order'])) {
                $order_ids = explode(',', $order['_order']);
                $choice_index = array_search($target_id, $order_ids);
                if ($choice_index !== false) {
                    return ['answer' => $choice_index];
                }
            }
            return ['answer' => $target_id];

        case 'truefalse':
            $answers = $DB->get_records('question_answers', ['question' => $question->questionid]);
            foreach ($answers as $a) {
                if (($correct && $a->fraction > 0) || (!$correct && $a->fraction == 0)) {
                    return ['answer' => $a->id];
                }
            }
            break;

        case 'shortanswer':
            if ($correct) {
                $answer = $DB->get_record_sql(
                    "SELECT answer FROM {question_answers} WHERE question = ? AND fraction > 0 LIMIT 1",
                    [$question->questionid]
                );
                return $answer ? ['answer' => $answer->answer] : ['answer' => 'wrong'];
            }
            return ['answer' => 'wrong_' . rand(1000, 9999)];

        case 'numerical':
            if ($correct) {
                $answer = $DB->get_record_sql(
                    "SELECT answer FROM {question_answers} WHERE question = ? AND fraction > 0 LIMIT 1",
                    [$question->questionid]
                );
                return $answer ? ['answer' => $answer->answer] : ['answer' => '0'];
            }
            return ['answer' => '-999999'];
    }

    return ['-submit' => 1];
}
