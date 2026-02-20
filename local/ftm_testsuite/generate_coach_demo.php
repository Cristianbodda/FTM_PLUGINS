<?php
/**
 * Generate Coach Demo - Creates 3 coaches + 1 real coach + 28 demo students (7 sectors per coach).
 *
 * Creates:
 * - 3 demo coach users (Roberto Bravo, Fabio Marinoni, Graziano Margonar)
 * - 1 real coach (Francesco Puglioli - existing user, not created)
 * - 28 demo students (7 per coach, one per sector with varied %)
 * - Enrolls everyone in courses
 * - Generates quiz attempts (sector-specific when possible, fallback to all courses)
 * - Generates self-assessments for sector competencies
 * - Creates CPURC records + assigns primary sector + assigns coach
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/generate_coach_demo.php'));
$PAGE->set_title('Genera Demo Coach');
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHA);

echo $OUTPUT->header();
echo '<div style="max-width:1000px; margin:0 auto; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">';
echo '<h2 style="color:#1e3c72;">🎓 Genera Demo Coach + Studenti per Settore</h2>';

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
    'FP' => [
        'firstname' => 'Francesco',
        'lastname' => 'Puglioli',
        'username' => 'francesco.puglioli',
        'email' => '',  // Will be read from existing user.
        'password' => '',  // Existing user, password not managed here.
        'existing_user' => true,  // Do NOT create, must already exist in Moodle.
    ],
];

$sector_profiles = [
    'MECCANICA'        => ['pct' => 30, 'label' => 'Meccanica',        'short' => 'mecc'],
    'LOGISTICA'        => ['pct' => 65, 'label' => 'Logistica',        'short' => 'log'],
    'AUTOMAZIONE'      => ['pct' => 95, 'label' => 'Automazione',      'short' => 'auto'],
    'ELETTRICITA'      => ['pct' => 65, 'label' => 'Elettricita',      'short' => 'elett'],
    'GENERICO'         => ['pct' => 30, 'label' => 'Generico',         'short' => 'gen'],
    'METALCOSTRUZIONE' => ['pct' => 95, 'label' => 'Metalcostruzione', 'short' => 'metal'],
    'CHIMFARM'         => ['pct' => 65, 'label' => 'Chimica Farm.',    'short' => 'chim'],
];

// ========================
// CONFIRMATION FORM
// ========================
if ($action !== 'generate' && $action !== 'cleanup') {
    echo '<div style="background:#e3f2fd; padding:20px; border-radius:12px; margin-bottom:20px;">';
    echo '<h3>Cosa verra creato:</h3>';
    echo '<ul>';
    echo '<li><strong>4 Coach:</strong> Roberto Bravo (RB), Fabio Marinoni (FM), Graziano Margonar (GM), Francesco Puglioli (FP - utente reale)</li>';
    echo '<li><strong>28 Studenti Demo:</strong> 7 per coach (uno per settore)</li>';
    echo '<li><strong>Quiz:</strong> Del settore assegnato (fallback a tutti i quiz se il settore non ha corsi propri)</li>';
    echo '<li><strong>Autovalutazioni:</strong> Bloom coerente con profilo</li>';
    echo '<li><strong>Settore primario + Record CPURC + Assegnazione Coach</strong></li>';
    echo '</ul>';

    echo '<table style="width:100%; border-collapse:collapse; margin:15px 0;">';
    echo '<tr style="background:#1e3c72; color:white;">';
    echo '<th style="padding:8px;">Coach</th><th style="padding:8px;">Username</th><th style="padding:8px;">Password</th>';
    echo '</tr>';
    foreach ($coaches as $initials => $c) {
        $is_existing = !empty($c['existing_user']);
        echo '<tr style="border-bottom:1px solid #ddd;">';
        echo '<td style="padding:8px;"><strong>' . $c['firstname'] . ' ' . $c['lastname'] . '</strong> (' . $initials . ')';
        if ($is_existing) {
            echo ' <span style="background:#0066cc; color:white; padding:2px 6px; border-radius:4px; font-size:11px;">UTENTE REALE</span>';
        }
        echo '</td>';
        echo '<td style="padding:8px;"><code>' . $c['username'] . '</code></td>';
        echo '<td style="padding:8px;">' . ($is_existing ? '<em style="color:#6c757d;">password esistente</em>' : '<code>' . $c['password'] . '</code>') . '</td>';
        echo '</tr>';
    }
    echo '</table>';

    echo '<table style="width:100%; border-collapse:collapse; margin:15px 0;">';
    echo '<tr style="background:#6f42c1; color:white;">';
    echo '<th style="padding:8px;">Settore</th><th style="padding:8px;">% Quiz</th>';
    echo '<th style="padding:8px;">Studenti (per ogni coach)</th>';
    echo '</tr>';
    foreach ($sector_profiles as $sector => $sp) {
        $color = $sp['pct'] <= 30 ? '#dc3545' : ($sp['pct'] >= 95 ? '#28a745' : '#EAB308');
        echo '<tr style="border-bottom:1px solid #ddd;">';
        echo '<td style="padding:8px;"><strong>' . $sp['label'] . '</strong></td>';
        echo '<td style="padding:8px; text-align:center;"><span style="color:' . $color . '; font-weight:700;">' . $sp['pct'] . '%</span></td>';
        echo '<td style="padding:8px; font-size:13px;">Roberto ' . $sp['label'] . ', Fabio ' . $sp['label'] . ', Graziano ' . $sp['label'] . ', Francesco ' . $sp['label'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';

    echo '<form method="post" style="display:flex; gap:10px;">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<input type="hidden" name="action" value="generate">';
    echo '<button type="submit" style="background:#28a745; color:white; border:none; padding:15px 30px; border-radius:8px; font-size:16px; font-weight:600; cursor:pointer;">✅ Genera Tutto (28 studenti)</button>';
    echo '</form>';

    echo '<form method="post" style="margin-top:15px;">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<input type="hidden" name="action" value="cleanup">';
    echo '<button type="submit" style="background:#dc3545; color:white; border:none; padding:10px 20px; border-radius:8px; font-size:14px; cursor:pointer;" onclick="return confirm(\'Sei sicuro? Elimina tutti i dati demo (28 studenti).\')">🗑️ Pulisci Demo Precedente</button>';
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

    // Find all test students (demo_* from this tool + ftm_test_* from admin_quiz_tester).
    $demo_users = $DB->get_records_sql("
        SELECT id, username, firstname, lastname
        FROM {user}
        WHERE (username LIKE 'demo\\_%' OR username LIKE 'ftm\\_test\\_%')
          AND deleted = 0
    ");

    foreach ($demo_users as $user) {
        // Delete quiz attempts.
        $attempts = $DB->get_records('quiz_attempts', ['userid' => $user->id]);
        foreach ($attempts as $a) {
            $qas = $DB->get_records('question_attempts', ['questionusageid' => $a->uniqueid]);
            foreach ($qas as $qa) {
                $steps = $DB->get_records('question_attempt_steps', ['questionattemptid' => $qa->id]);
                foreach ($steps as $step) {
                    $DB->delete_records('question_attempt_step_data', ['attemptstepid' => $step->id]);
                }
                $DB->delete_records('question_attempt_steps', ['questionattemptid' => $qa->id]);
            }
            $DB->delete_records('question_attempts', ['questionusageid' => $a->uniqueid]);
            $DB->delete_records('question_usages', ['id' => $a->uniqueid]);
            $DB->delete_records('quiz_attempts', ['id' => $a->id]);
        }
        $DB->delete_records('local_selfassessment', ['userid' => $user->id]);
        $DB->delete_records('local_student_coaching', ['userid' => $user->id]);
        $DB->delete_records('local_ftm_cpurc_students', ['userid' => $user->id]);
        $DB->delete_records('quiz_grades', ['userid' => $user->id]);
        $DB->delete_records('local_student_sectors', ['userid' => $user->id]);

        echo "<p>🗑️ Puliti dati per <strong>{$user->firstname} {$user->lastname}</strong> ({$user->username})</p>";
        $cleaned++;
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
echo '<div style="background:#f8f9fa; padding:20px; border-radius:12px; font-family:monospace; font-size:13px; line-height:1.8; max-height:600px; overflow-y:auto;">';

$mnethostid = $CFG->mnet_localhost_id;
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
// BUILD QUIZ → SECTOR MAP (from competency idnumbers per quiz)
// ========================
echo '<strong>== Analisi Quiz → Settori ==</strong><br>';

// Get competency idnumbers per quiz (not just per course).
$quiz_comp_rs = $DB->get_recordset_sql("
    SELECT q.id as quizid, q.course as courseid, q.name as quizname, comp.idnumber
    FROM {quiz} q
    JOIN {quiz_slots} qs ON qs.quizid = q.id
    JOIN {question_references} qr ON qr.itemid = qs.id
        AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
    JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
    JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
    JOIN {competency} comp ON comp.id = qc.competencyid
    WHERE q.course > 1
");

$quiz_sector_counts = [];
foreach ($quiz_comp_rs as $row) {
    $parts = explode('_', $row->idnumber);
    $prefix = strtoupper($parts[0]);
    if ($prefix === 'GEN') {
        $prefix = 'GENERICO';
    }
    if (!isset($quiz_sector_counts[$row->quizid])) {
        $quiz_sector_counts[$row->quizid] = [];
    }
    if (!isset($quiz_sector_counts[$row->quizid][$prefix])) {
        $quiz_sector_counts[$row->quizid][$prefix] = 0;
    }
    $quiz_sector_counts[$row->quizid][$prefix]++;
}
$quiz_comp_rs->close();

// Dominant sector per quiz.
$quiz_sectors = [];
foreach ($quiz_sector_counts as $quizid => $sectors) {
    arsort($sectors);
    $quiz_sectors[$quizid] = array_key_first($sectors);
}

// Group quizzes by sector and count.
$sector_quiz_count = [];
foreach ($quiz_sectors as $quizid => $sector) {
    if (!isset($sector_quiz_count[$sector])) {
        $sector_quiz_count[$sector] = 0;
    }
    $sector_quiz_count[$sector]++;
}

// Also build course-level sector map (for course enrollments).
$course_sectors = [];
$sector_courses = [];
foreach ($courses as $course) {
    // Find dominant sector from quizzes in this course.
    $course_sector_counts = [];
    foreach ($quiz_sectors as $quizid => $sector) {
        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'course');
        if ($quiz && $quiz->course == $course->id) {
            if (!isset($course_sector_counts[$sector])) {
                $course_sector_counts[$sector] = 0;
            }
            $course_sector_counts[$sector]++;
        }
    }
    if (!empty($course_sector_counts)) {
        arsort($course_sector_counts);
        $course_sectors[$course->id] = array_key_first($course_sector_counts);
        $sector_courses[array_key_first($course_sector_counts)][] = $course->id;
    }
}

foreach ($sector_quiz_count as $sector => $count) {
    echo "📌 <strong>{$sector}</strong>: {$count} quiz<br>";
}

$missing_sectors = [];
foreach ($sector_profiles as $sector => $sp) {
    if (!isset($sector_quiz_count[$sector])) {
        $missing_sectors[] = $sector;
    }
}
if (!empty($missing_sectors)) {
    echo "ℹ️ Settori senza quiz propri: <strong>" . implode(', ', $missing_sectors) . "</strong> (useranno tutti i quiz come fallback)<br>";
}

// ========================
// STEP 1: CREATE COACHES
// ========================
echo '<br><strong>== STEP 1: Creazione Coach ==</strong><br>';
$coach_userids = [];

foreach ($coaches as $initials => $cdata) {
    $is_existing = !empty($cdata['existing_user']);

    $user = $DB->get_record('user', ['username' => $cdata['username'], 'deleted' => 0]);
    if (!$user && $is_existing) {
        // Try by firstname + lastname as fallback for existing users.
        $user = $DB->get_record('user', ['firstname' => $cdata['firstname'], 'lastname' => $cdata['lastname'], 'deleted' => 0]);
    }
    if (!$user && $is_existing) {
        // Existing user not found - skip this coach entirely.
        echo "⚠️ <strong>{$cdata['firstname']} {$cdata['lastname']}</strong> ({$cdata['username']}) NON trovato in Moodle - saltato. Crea prima l'utente!<br>";
        continue;
    } else if (!$user) {
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
        echo "✅ Coach creato: <strong>{$cdata['firstname']} {$cdata['lastname']}</strong> ({$cdata['username']} / {$cdata['password']})<br>";
    } else if ($is_existing) {
        echo "✅ Coach reale trovato: <strong>{$user->firstname} {$user->lastname}</strong> (id={$user->id})<br>";
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

    // Assign editingteacher at SYSTEM level (needed for local/coachmanager:view capability).
    $system_context = context_system::instance();
    if (!has_capability('local/coachmanager:view', $system_context, $user->id)) {
        role_assign($teacherroleid, $user->id, $system_context->id);
        echo "  🔑 Ruolo editingteacher assegnato a livello SISTEMA (capability coach dashboard)<br>";
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
// STEP 2: CREATE DEMO STUDENTS (per sector)
// ========================
echo '<br><strong>== STEP 2: Creazione Studenti Demo (7 settori x ' . count($coach_userids) . ' coach) ==</strong><br>';
$student_data = [];

foreach ($coaches as $initials => $cdata) {
    // Skip coaches that were not found (existing_user not in Moodle).
    if (!isset($coach_userids[$initials])) {
        continue;
    }
    foreach ($sector_profiles as $sector => $sp) {
        $username = 'demo_' . strtolower($initials) . '_' . $sp['short'];
        $firstname = $cdata['firstname'];
        $lastname = $sp['label'];
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
            echo "✅ <strong>{$firstname} {$lastname}</strong> ({$username}) → {$initials}, {$sector} {$sp['pct']}%<br>";
        } else {
            echo "⏭️ <strong>{$user->firstname} {$user->lastname}</strong> (id={$user->id})<br>";
        }

        $student_data[$username] = [
            'userid' => $user->id,
            'percentage' => $sp['pct'],
            'sector' => $sector,
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

        // Assign primary sector (local_student_sectors).
        $DB->delete_records('local_student_sectors', ['userid' => $user->id, 'source' => 'manual']);
        $ss = new stdClass();
        $ss->userid = $user->id;
        $ss->courseid = 0;
        $ss->sector = $sector;
        $ss->is_primary = 1;
        $ss->source = 'manual';
        $ss->quiz_count = 0;
        $ss->first_detected = $now;
        $ss->last_detected = $now;
        $ss->timecreated = $now;
        $ss->timemodified = $now;
        $DB->insert_record('local_student_sectors', $ss);

        // Create CPURC record.
        if (!$DB->record_exists('local_ftm_cpurc_students', ['userid' => $user->id])) {
            $cpurc = new stdClass();
            $cpurc->userid = $user->id;
            $cpurc->cpurc_id = 'DEMO-' . strtoupper($initials) . '-' . strtoupper($sp['short']);
            $cpurc->gender = 'M';
            $cpurc->trainer = $initials;
            $cpurc->measure = 'FTM';
            $cpurc->urc_office = 'Bellinzona';
            $cpurc->status = 'In corso';
            $cpurc->last_profession = 'Demo ' . $sp['label'];
            $cpurc->date_start = $date_start;
            $cpurc->date_end_planned = $date_end;
            $cpurc->nationality = 'CH';
            $cpurc->occupation_grade = 100;
            $cpurc->timecreated = $now;
            $cpurc->timemodified = $now;
            $DB->insert_record('local_ftm_cpurc_students', $cpurc);
        }

        // Assign to coach with sector (local_student_coaching).
        $coaching = $DB->get_record('local_student_coaching', ['userid' => $user->id]);
        if (!$coaching) {
            $coaching = new stdClass();
            $coaching->userid = $user->id;
            $coaching->coachid = $coach_userids[$initials];
            $coaching->courseid = 0;
            $coaching->sector = $sector;
            $coaching->date_start = $date_start;
            $coaching->date_end = $date_end;
            $coaching->current_week = 1;
            $coaching->status = 'active';
            $coaching->timecreated = $now;
            $coaching->timemodified = $now;
            $DB->insert_record('local_student_coaching', $coaching);
        } else {
            $coaching->sector = $sector;
            $coaching->coachid = $coach_userids[$initials];
            $coaching->timemodified = $now;
            $DB->update_record('local_student_coaching', $coaching);
        }
    }
}

// ========================
// STEP 3: GENERATE QUIZ ATTEMPTS (filtered per quiz sector)
// ========================
echo '<br><strong>== STEP 3: Generazione Tentativi Quiz (per settore quiz) ==</strong><br>';
$quiz_count = 0;
$sector_attempt_counts = [];

foreach ($courses as $course) {
    $quizzes = $DB->get_records('quiz', ['course' => $course->id]);
    if (empty($quizzes)) {
        continue;
    }

    echo "📚 <strong>{$course->fullname}</strong> (" . count($quizzes) . " quiz)<br>";

    foreach ($quizzes as $quiz) {
        $quiz_sector = $quiz_sectors[$quiz->id] ?? 'UNKNOWN';

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

        // Find students matching THIS quiz's sector, or fallback students.
        foreach ($student_data as $username => $sdata) {
            $student_sector = $sdata['sector'];
            // Match: student sector == quiz sector, OR student's sector has no quizzes (fallback).
            if ($student_sector !== $quiz_sector && isset($sector_quiz_count[$student_sector])) {
                continue; // Student has own sector quizzes, skip non-matching quiz.
            }
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

                $attempt = new stdClass();
                $attempt->quiz = $quiz->id;
                $attempt->userid = $sdata['userid'];
                $attempt->attempt = 1;
                $attempt->uniqueid = $quba->get_id();
                $layout_slots = [];
                foreach ($questions as $q) {
                    $layout_slots[] = $q->slot;
                }
                sort($layout_slots);
                $attempt->layout = implode(',', $layout_slots) . ',0';
                $attempt->currentpage = 0;
                $attempt->preview = 0;
                $attempt->state = 'inprogress';
                $attempt->timestart = $now - 3600;
                $attempt->timefinish = 0;
                $attempt->timemodified = $now;
                $attempt->timemodifiedoffline = 0;
                $attempt->sumgrades = null;
                $attempt->id = $DB->insert_record('quiz_attempts', $attempt);

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

                $quba->finish_all_questions();
                question_engine::save_questions_usage_by_activity($quba);

                $attempt->state = 'finished';
                $attempt->timefinish = $now;
                $attempt->sumgrades = $quba->get_total_mark();
                $DB->update_record('quiz_attempts', $attempt);

                quiz_save_best_grade($quiz, $sdata['userid']);
                $quiz_count++;
                if (!isset($sector_attempt_counts[$student_sector])) {
                    $sector_attempt_counts[$student_sector] = 0;
                }
                $sector_attempt_counts[$student_sector]++;

            } catch (Exception $e) {
                echo "  ⚠️ Errore '{$quiz->name}' per {$username}: " . $e->getMessage() . "<br>";
            }
        }
    }
}
echo "✅ Generati <strong>{$quiz_count}</strong> tentativi quiz<br>";
foreach ($sector_attempt_counts as $sec => $cnt) {
    echo "  📊 {$sec}: {$cnt} tentativi<br>";
}

// ========================
// STEP 4: GENERATE SELF-ASSESSMENTS (filtered by competency sector)
// ========================
echo '<br><strong>== STEP 4: Generazione Autovalutazioni (per settore competenze) ==</strong><br>';
$sa_count = 0;

// Get ALL competencies with sector info.
$all_competencies = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.shortname, c.idnumber
    FROM {competency} c
    JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
    JOIN {question_versions} qv ON qv.questionid = qc.questionid
    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
    JOIN {question_references} qr ON qr.questionbankentryid = qbe.id
        AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
");

// Group competencies by sector.
$competencies_by_sector = [];
foreach ($all_competencies as $comp) {
    $parts = explode('_', $comp->idnumber);
    $prefix = strtoupper($parts[0]);
    if ($prefix === 'GEN') {
        $prefix = 'GENERICO';
    }
    $competencies_by_sector[$prefix][$comp->id] = $comp;
}

foreach ($student_data as $username => $sdata) {
    $student_sector = $sdata['sector'];

    // Get competencies for this student's sector. Fallback to all if sector has none.
    if (isset($competencies_by_sector[$student_sector])) {
        $comps = $competencies_by_sector[$student_sector];
    } else {
        // Fallback: use all competencies.
        $comps = $all_competencies;
    }

    if (empty($comps)) {
        continue;
    }

    $bloom_range = match(true) {
        $sdata['percentage'] <= 30 => [4, 6],
        $sdata['percentage'] <= 65 => [3, 5],
        default => [2, 4],
    };

    $student_sa = 0;
    foreach ($comps as $comp) {
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
        echo "  🧠 {$username} ({$student_sector}): {$student_sa} autovalutazioni<br>";
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
echo '<li><strong>' . count($coach_userids) . '</strong> Coach attivi (' . implode(', ', array_keys($coach_userids)) . ')</li>';
echo '<li><strong>' . count($student_data) . '</strong> Studenti demo (7 settori x ' . count($coach_userids) . ' coach)</li>';
echo '<li><strong>' . $quiz_count . '</strong> Tentativi quiz generati (solo settore assegnato)</li>';
echo '<li><strong>' . $sa_count . '</strong> Autovalutazioni generate</li>';
if (!empty($missing_sectors)) {
    echo '<li style="color:#0066cc;">ℹ️ Settori senza corsi propri (fallback attivo): ' . implode(', ', $missing_sectors) . '</li>';
}
echo '</ul>';
echo '<p>';
echo '<a href="' . (new moodle_url('/local/ftm_cpurc/index.php'))->out() . '" style="margin-right:10px;">📊 Dashboard CPURC</a>';
echo '<a href="' . (new moodle_url('/local/coachmanager/coach_dashboard_v2.php'))->out() . '" style="margin-right:10px;">👨‍🏫 Coach Dashboard</a>';
echo '<a href="' . (new moodle_url('/local/ftm_testsuite/admin_quiz_tester.php'))->out() . '">🎯 Admin Quiz Tester</a>';
echo '</p>';
echo '</div>';

echo '<p style="margin-top:15px;"><a href="' . (new moodle_url('/local/ftm_testsuite/index.php'))->out() . '">← Torna alla Test Suite</a></p>';
echo '</div>';
echo $OUTPUT->footer();

// ========================
// HELPER FUNCTION
// ========================

/**
 * Generate response for a question.
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
