<?php
// ============================================
// DEBUG OBSERVER: Simula la logica dell'observer
// ============================================
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/debug_observer.php'));
$PAGE->set_title('Debug Observer Logic');

$attemptid = optional_param('attemptid', 0, PARAM_INT);
$userid_filter = optional_param('userid', 0, PARAM_INT);
$courseid_filter = optional_param('courseid', 0, PARAM_INT);

echo $OUTPUT->header();
?>
<style>
.debug-container { max-width: 1200px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, sans-serif; }
.debug-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.debug-title { font-size: 1.2em; font-weight: 700; margin-bottom: 15px; color: #333; }
.step { padding: 10px 15px; margin: 10px 0; border-radius: 8px; }
.step-ok { background: #d4edda; border-left: 4px solid #28a745; }
.step-fail { background: #f8d7da; border-left: 4px solid #dc3545; }
.step-warn { background: #fff3cd; border-left: 4px solid #ffc107; }
.step-info { background: #d1ecf1; border-left: 4px solid #17a2b8; }
pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 0.85em; margin: 10px 0; }
.filter-form { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
.filter-form input, .filter-form select { padding: 8px 12px; margin: 0 10px 0 5px; border: 1px solid #ddd; border-radius: 4px; }
.filter-form button { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
</style>

<div class="debug-container">
    <h1>🔬 Debug Observer Logic</h1>
    <p>Questo script simula esattamente cosa fa l'observer quando un quiz viene completato.</p>

    <div class="filter-form">
        <form method="get">
            <strong>Filtri:</strong>
            <label>User ID: <input type="number" name="userid" value="<?php echo $userid_filter; ?>" placeholder="es. 82"></label>
            <label>Course ID: <input type="number" name="courseid" value="<?php echo $courseid_filter; ?>" placeholder="es. 12"></label>
            <button type="submit">Filtra</button>
            <a href="?" style="margin-left: 10px;">Reset</a>
        </form>
        <p style="margin-top: 10px; font-size: 0.9em; color: #666;">
            <strong>Fabio = 82</strong> | Corso Chimica23 = 12 | Corso Automobile = 13
        </p>
    </div>

<?php

// Build query
$where_conditions = ["qa.state = 'finished'"];
$params = [];

if ($userid_filter > 0) {
    $where_conditions[] = "qa.userid = ?";
    $params[] = $userid_filter;
}
if ($courseid_filter > 0) {
    $where_conditions[] = "q.course = ?";
    $params[] = $courseid_filter;
}

$where_sql = implode(' AND ', $where_conditions);

// Lista tentativi
echo '<div class="debug-card">';
echo '<div class="debug-title">📋 Quiz Attempts' . ($userid_filter || $courseid_filter ? ' (Filtrati)' : ' (Ultimi 30)') . '</div>';

$recent_attempts = $DB->get_records_sql("
    SELECT qa.id, qa.userid, qa.quiz, qa.state, qa.timefinish, qa.uniqueid,
           q.name as quizname, q.course as courseid, c.shortname as coursename,
           u.firstname, u.lastname
    FROM {quiz_attempts} qa
    JOIN {quiz} q ON q.id = qa.quiz
    JOIN {course} c ON c.id = q.course
    JOIN {user} u ON u.id = qa.userid
    WHERE {$where_sql}
    ORDER BY qa.timefinish DESC
    LIMIT 30
", $params);

echo '<table border="1" cellpadding="8" style="border-collapse: collapse; width: 100%; font-size: 0.9em;">';
echo '<tr style="background: #f0f0f0;"><th>ID</th><th>Studente</th><th>Corso</th><th>Quiz</th><th>Data</th><th>Debug</th></tr>';
foreach ($recent_attempts as $att) {
    $date = date('d/m H:i', $att->timefinish);
    $params_str = "attemptid={$att->id}";
    if ($userid_filter) $params_str .= "&userid={$userid_filter}";
    if ($courseid_filter) $params_str .= "&courseid={$courseid_filter}";
    $link = "<a href='?{$params_str}' style='background: #007bff; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none;'>Analizza</a>";

    $course_badge = '';
    if (stripos($att->quizname, 'CHIMFARM') !== false) {
        $course_badge = '<span style="background: #6f42c1; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em;">CHIMFARM</span> ';
    } else if (stripos($att->quizname, 'AUTOMOBILE') !== false) {
        $course_badge = '<span style="background: #28a745; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em;">AUTO</span> ';
    } else if (stripos($att->quizname, 'MECCANICA') !== false) {
        $course_badge = '<span style="background: #fd7e14; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em;">MECC</span> ';
    }

    echo "<tr><td>{$att->id}</td><td>{$att->firstname} {$att->lastname}</td><td>{$att->coursename}</td><td>{$course_badge}{$att->quizname}</td><td>{$date}</td><td>{$link}</td></tr>";
}
echo '</table>';
echo '</div>';

if ($attemptid > 0) {
    echo '<div class="debug-card">';
    echo '<div class="debug-title">🔬 Analisi Attempt ID: ' . $attemptid . '</div>';

    // STEP 1: Carica attempt
    echo '<div class="step step-info"><strong>STEP 1:</strong> Caricamento quiz attempt...</div>';

    $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
    if (!$attempt) {
        echo '<div class="step step-fail">❌ Attempt non trovato!</div>';
        echo '</div>';
        echo $OUTPUT->footer();
        die();
    }

    $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz]);
    $user = $DB->get_record('user', ['id' => $attempt->userid]);

    echo '<div class="step step-ok">';
    echo "✓ Attempt trovato<br>";
    echo "• User ID: {$attempt->userid} ({$user->firstname} {$user->lastname})<br>";
    echo "• Quiz ID: {$attempt->quiz} ({$quiz->name})<br>";
    echo "• UniqueID: {$attempt->uniqueid}<br>";
    echo "• State: {$attempt->state}";
    echo '</div>';

    // STEP 2: Ottieni domande dal question_attempts
    echo '<div class="step step-info"><strong>STEP 2:</strong> Ricerca domande dall\'attempt...</div>';

    $questions = $DB->get_records_sql("
        SELECT DISTINCT qa.questionid, qa.slot, q.name, q.qtype
        FROM {question_attempts} qa
        JOIN {question} q ON q.id = qa.questionid
        WHERE qa.questionusageid = ?
    ", [$attempt->uniqueid]);

    if (empty($questions)) {
        echo '<div class="step step-fail">❌ Nessuna domanda trovata per uniqueid=' . $attempt->uniqueid . '</div>';

        // Debug: verifica question_usages
        $usage = $DB->get_record('question_usages', ['id' => $attempt->uniqueid]);
        if ($usage) {
            echo '<pre>question_usages trovato: ' . print_r($usage, true) . '</pre>';
        } else {
            echo '<div class="step step-fail">❌ Anche question_usages non trovato!</div>';
        }
    } else {
        echo '<div class="step step-ok">';
        echo '✓ Trovate ' . count($questions) . ' domande<br><br>';
        echo '<table border="1" cellpadding="5" style="border-collapse: collapse; font-size: 0.85em;">';
        echo '<tr><th>Question ID</th><th>Slot</th><th>Tipo</th><th>Nome</th></tr>';
        foreach ($questions as $q) {
            $name_short = mb_strlen($q->name) > 60 ? mb_substr($q->name, 0, 60) . '...' : $q->name;
            echo "<tr><td>{$q->questionid}</td><td>{$q->slot}</td><td>{$q->qtype}</td><td>{$name_short}</td></tr>";
        }
        echo '</table>';
        echo '</div>';

        $questionids = array_keys($questions);

        // STEP 3: Trova tabella mapping competenze
        echo '<div class="step step-info"><strong>STEP 3:</strong> Ricerca tabella mapping competenze-domande...</div>';

        $comp_tables = [
            'qbank_competenciesbyquestion' => 'questionid',
            'qbank_comp_question' => 'questionid',
            'local_competencymanager_qcomp' => 'questionid'
        ];

        $comp_question_table = null;
        $comp_question_field = null;
        $dbman = $DB->get_manager();

        foreach ($comp_tables as $table => $field) {
            if ($dbman->table_exists($table)) {
                $comp_question_table = $table;
                $comp_question_field = $field;
                break;
            }
        }

        if (!$comp_question_table) {
            echo '<div class="step step-fail">❌ Nessuna tabella di mapping trovata!</div>';
        } else {
            echo '<div class="step step-ok">✓ Usando tabella: <strong>' . $comp_question_table . '</strong></div>';

            // STEP 4: Trova competenze mappate
            echo '<div class="step step-info"><strong>STEP 4:</strong> Ricerca competenze mappate alle domande...</div>';

            list($sql_in, $params) = $DB->get_in_or_equal($questionids);
            $mappings = $DB->get_records_sql("
                SELECT DISTINCT m.competencyid, c.idnumber, c.shortname
                FROM {{$comp_question_table}} m
                JOIN {competency} c ON c.id = m.competencyid
                WHERE m.{$comp_question_field} $sql_in
            ", $params);

            if (empty($mappings)) {
                echo '<div class="step step-fail">';
                echo '❌ NESSUNA competenza mappata a queste domande!<br><br>';
                echo '<strong>Questo è il problema:</strong> Le domande del quiz non hanno competenze associate.<br>';
                echo 'Question IDs cercate: ' . implode(', ', $questionids);
                echo '</div>';

                // Verifica se esistono mapping per queste domande
                echo '<div class="step step-warn">';
                echo '<strong>Verifica dettagliata mapping:</strong><br>';
                $found_any = false;
                foreach ($questionids as $qid) {
                    $count = $DB->count_records($comp_question_table, [$comp_question_field => $qid]);
                    $status = $count > 0 ? "✓ {$count} competenze" : "✗ Nessuna";
                    if ($count > 0) $found_any = true;
                    echo "Question {$qid}: {$status}<br>";
                }
                if (!$found_any) {
                    echo '<br><strong style="color: red;">⚠️ NESSUNA delle domande ha competenze mappate!</strong><br>';
                    echo 'Devi importare le competenze usando il Competency Manager.';
                }
                echo '</div>';
            } else {
                echo '<div class="step step-ok">';
                echo '✓ Trovate ' . count($mappings) . ' competenze mappate<br><br>';
                echo '<table border="1" cellpadding="5" style="border-collapse: collapse; font-size: 0.85em;">';
                echo '<tr><th>Competency ID</th><th>IDNumber</th><th>Shortname</th></tr>';
                foreach ($mappings as $m) {
                    echo "<tr><td>{$m->competencyid}</td><td>{$m->idnumber}</td><td>{$m->shortname}</td></tr>";
                }
                echo '</table>';
                echo '</div>';

                // STEP 5: Verifica settore primario
                echo '<div class="step step-info"><strong>STEP 5:</strong> Verifica settore primario utente...</div>';

                $primary_sector = null;
                if ($dbman->table_exists('local_student_sectors')) {
                    $sector_record = $DB->get_record('local_student_sectors', [
                        'userid' => $attempt->userid,
                        'is_primary' => 1
                    ]);
                    $primary_sector = $sector_record ? $sector_record->sector : null;
                }

                if ($primary_sector) {
                    echo '<div class="step step-warn">';
                    echo "⚠️ Utente ha settore primario: <strong>{$primary_sector}</strong><br>";
                    echo "Le competenze di altri settori verranno SALTATE!";
                    echo '</div>';
                } else {
                    echo '<div class="step step-ok">✓ Nessun settore primario - tutte le competenze verranno assegnate</div>';
                }

                // STEP 6: Simula assegnazioni
                echo '<div class="step step-info"><strong>STEP 6:</strong> Simulazione assegnazioni...</div>';

                $would_assign = 0;
                $already_exists = 0;
                $skipped_sector = 0;
                $details = [];

                foreach ($mappings as $m) {
                    $competencyid = $m->competencyid;

                    // Check sector filter
                    if (!empty($primary_sector)) {
                        $comp_sector = null;
                        if (!empty($m->idnumber)) {
                            $parts = explode('_', $m->idnumber);
                            if (!empty($parts[0])) {
                                $comp_sector = strtoupper($parts[0]);
                            }
                        }
                        if (!empty($comp_sector) && $comp_sector !== $primary_sector) {
                            $skipped_sector++;
                            $details[] = "⊘ {$m->idnumber}: Skip (settore {$comp_sector} != {$primary_sector})";
                            continue;
                        }
                    }

                    // Check if already assigned
                    $exists = $DB->record_exists('local_selfassessment_assign', [
                        'userid' => $attempt->userid,
                        'competencyid' => $competencyid
                    ]);

                    if ($exists) {
                        $already_exists++;
                        $details[] = "○ {$m->idnumber}: Già assegnata";
                    } else {
                        $would_assign++;
                        $details[] = "✓ {$m->idnumber}: NUOVA assegnazione";
                    }
                }

                echo '<div class="step ' . ($would_assign > 0 ? 'step-ok' : 'step-warn') . '">';
                echo "<strong>Risultato simulazione:</strong><br><br>";
                echo "• Nuove assegnazioni: <strong>{$would_assign}</strong><br>";
                echo "• Già esistenti: {$already_exists}<br>";
                echo "• Saltate per settore: {$skipped_sector}<br><br>";
                echo "<strong>Dettaglio:</strong><br>";
                foreach ($details as $d) {
                    echo $d . '<br>';
                }
                echo '</div>';

                if ($would_assign == 0 && $already_exists > 0) {
                    echo '<div class="step step-warn">';
                    echo '<strong>⚠️ Tutte le competenze sono già state assegnate!</strong><br>';
                    echo 'Questo significa che l\'observer HA funzionato in precedenza per questo utente.';
                    echo '</div>';
                }

                if ($would_assign == 0 && $already_exists == 0 && $skipped_sector > 0) {
                    echo '<div class="step step-fail">';
                    echo '<strong>❌ Tutte le competenze sono state saltate per filtro settore!</strong><br>';
                    echo "Rimuovi il settore primario dell'utente per risolvere.";
                    echo '</div>';
                }

                if ($would_assign > 0) {
                    echo '<div class="step step-fail">';
                    echo "<strong>❌ L'observer AVREBBE DOVUTO assegnare {$would_assign} competenze ma NON lo ha fatto!</strong><br><br>";
                    echo "Possibili cause:<br>";
                    echo "1. L'observer non è registrato correttamente<br>";
                    echo "2. La cache di Moodle è vecchia<br>";
                    echo "3. Errore durante l'esecuzione dell'observer<br><br>";
                    echo "<strong>Soluzione:</strong> Svuota la cache Moodle e verifica db/events.php";
                    echo '</div>';
                }
            }
        }
    }

    echo '</div>';

    // Verifica eventi recenti
    echo '<div class="debug-card">';
    echo '<div class="debug-title">📜 Log Eventi per questo Attempt</div>';

    $logs = $DB->get_records_sql("
        SELECT id, eventname, timecreated, other
        FROM {logstore_standard_log}
        WHERE objectid = ?
        AND eventname LIKE '%quiz%'
        ORDER BY timecreated DESC
        LIMIT 10
    ", [$attemptid]);

    if ($logs) {
        echo '<table border="1" cellpadding="5" style="border-collapse: collapse; font-size: 0.85em;">';
        echo '<tr><th>Data</th><th>Evento</th></tr>';
        foreach ($logs as $log) {
            $date = date('d/m H:i:s', $log->timecreated);
            echo "<tr><td>{$date}</td><td><code>" . str_replace('\\', ' \ ', $log->eventname) . "</code></td></tr>";
        }
        echo '</table>';
    } else {
        echo '<p>Nessun log trovato</p>';
    }
    echo '</div>';
}
?>

</div>
<?php
echo $OUTPUT->footer();
