<?php
// ============================================
// FORCE ASSIGN: Esegue manualmente l'assegnazione
// ============================================
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/force_assign.php'));
$PAGE->set_title('Force Assign');

$action = optional_param('action', '', PARAM_ALPHA);
$attemptid = optional_param('attemptid', 0, PARAM_INT);

echo $OUTPUT->header();
?>
<style>
.container { max-width: 1000px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, sans-serif; }
.card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.success { background: #d4edda; padding: 15px; border-radius: 8px; color: #155724; }
.error { background: #f8d7da; padding: 15px; border-radius: 8px; color: #721c24; }
.warning { background: #fff3cd; padding: 15px; border-radius: 8px; color: #856404; }
.info { background: #d1ecf1; padding: 15px; border-radius: 8px; color: #0c5460; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
th { background: #f8f9fa; }
.btn { display: inline-block; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; margin: 5px; }
.btn-danger { background: #dc3545; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-primary { background: #007bff; color: white; }
</style>

<div class="container">
<h1>⚡ Force Assign - Esecuzione Manuale Observer</h1>

<?php
$dbman = $DB->get_manager();

if ($action === 'process' && $attemptid > 0) {
    echo '<div class="card">';
    echo '<h2>Elaborazione Attempt ID: ' . $attemptid . '</h2>';

    // Carica attempt
    $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
    if (!$attempt) {
        echo '<div class="error">Attempt non trovato!</div>';
    } else {
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz]);
        $user = $DB->get_record('user', ['id' => $attempt->userid]);

        echo "<p><strong>Utente:</strong> {$user->firstname} {$user->lastname} (ID: {$user->id})</p>";
        echo "<p><strong>Quiz:</strong> {$quiz->name}</p>";
        echo "<p><strong>UniqueID:</strong> {$attempt->uniqueid}</p>";

        // STEP 1: Ottieni domande
        $questions = $DB->get_records_sql("
            SELECT DISTINCT qa.questionid
            FROM {question_attempts} qa
            WHERE qa.questionusageid = ?
        ", [$attempt->uniqueid]);

        echo '<h3>Step 1: Domande</h3>';
        if (empty($questions)) {
            echo '<div class="error">Nessuna domanda trovata!</div>';
        } else {
            echo '<div class="success">Trovate ' . count($questions) . ' domande</div>';
            $questionids = array_keys($questions);

            // STEP 2: Trova tabella mapping
            echo '<h3>Step 2: Tabella Mapping</h3>';
            $comp_table = null;
            foreach (['qbank_competenciesbyquestion', 'local_competencymanager_qcomp'] as $t) {
                if ($dbman->table_exists($t)) {
                    $comp_table = $t;
                    break;
                }
            }

            if (!$comp_table) {
                echo '<div class="error">Tabella mapping non trovata!</div>';
            } else {
                echo "<div class='success'>Usando: {$comp_table}</div>";

                // STEP 3: Trova competenze
                echo '<h3>Step 3: Competenze Mappate</h3>';
                list($sql_in, $params) = $DB->get_in_or_equal($questionids);
                $mappings = $DB->get_records_sql("
                    SELECT DISTINCT m.competencyid, c.idnumber, c.shortname
                    FROM {{$comp_table}} m
                    JOIN {competency} c ON c.id = m.competencyid
                    WHERE m.questionid $sql_in
                ", $params);

                if (empty($mappings)) {
                    echo '<div class="error">Nessuna competenza mappata!</div>';
                } else {
                    echo '<div class="success">Trovate ' . count($mappings) . ' competenze uniche</div>';

                    // STEP 4: Verifica settore primario
                    echo '<h3>Step 4: Settore Primario</h3>';
                    $primary_sector = null;
                    if ($dbman->table_exists('local_student_sectors')) {
                        $sector = $DB->get_record('local_student_sectors', [
                            'userid' => $attempt->userid,
                            'is_primary' => 1
                        ]);
                        $primary_sector = $sector ? $sector->sector : null;
                    }

                    if ($primary_sector) {
                        echo "<div class='warning'>Settore primario: {$primary_sector} - Le competenze di altri settori verranno saltate!</div>";
                    } else {
                        echo '<div class="success">Nessun settore primario - tutte le competenze saranno assegnate</div>';
                    }

                    // STEP 5: Assegna competenze
                    echo '<h3>Step 5: ASSEGNAZIONE COMPETENZE</h3>';

                    $assigned = 0;
                    $skipped_sector = 0;
                    $already_exists = 0;
                    $errors = 0;
                    $now = time();
                    $details = [];

                    foreach ($mappings as $m) {
                        $competencyid = $m->competencyid;

                        // Filtro settore
                        if (!empty($primary_sector) && !empty($m->idnumber)) {
                            $parts = explode('_', $m->idnumber);
                            $comp_sector = strtoupper($parts[0] ?? '');
                            if (!empty($comp_sector) && $comp_sector !== $primary_sector) {
                                $skipped_sector++;
                                $details[] = "⊘ {$m->idnumber}: Settore {$comp_sector} != {$primary_sector}";
                                continue;
                            }
                        }

                        // Verifica se già esiste
                        $exists = $DB->record_exists('local_selfassessment_assign', [
                            'userid' => $attempt->userid,
                            'competencyid' => $competencyid
                        ]);

                        if ($exists) {
                            $already_exists++;
                            $details[] = "○ {$m->idnumber}: Già esistente";
                        } else {
                            // INSERISCI!
                            $record = new stdClass();
                            $record->userid = $attempt->userid;
                            $record->competencyid = $competencyid;
                            $record->source = 'quiz';
                            $record->sourceid = $attempt->quiz;
                            $record->timecreated = $now;

                            try {
                                $id = $DB->insert_record('local_selfassessment_assign', $record);
                                $assigned++;
                                $details[] = "✓ {$m->idnumber}: ASSEGNATA (ID: {$id})";
                            } catch (Exception $e) {
                                $errors++;
                                $details[] = "✗ {$m->idnumber}: ERRORE - " . $e->getMessage();
                            }
                        }
                    }

                    // Riepilogo
                    echo '<div class="' . ($assigned > 0 ? 'success' : 'warning') . '">';
                    echo '<strong>RISULTATO:</strong><br>';
                    echo "• Nuove assegnazioni: <strong>{$assigned}</strong><br>";
                    echo "• Già esistenti: {$already_exists}<br>";
                    echo "• Saltate (settore): {$skipped_sector}<br>";
                    echo "• Errori: {$errors}";
                    echo '</div>';

                    if (!empty($details)) {
                        echo '<h4>Dettaglio (prime 30):</h4>';
                        echo '<div style="max-height: 300px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 8px; font-family: monospace; font-size: 0.85em;">';
                        $i = 0;
                        foreach ($details as $d) {
                            if ($i++ >= 30) {
                                echo "... e altri " . (count($details) - 30) . " record<br>";
                                break;
                            }
                            echo $d . '<br>';
                        }
                        echo '</div>';
                    }

                    if ($assigned > 0) {
                        echo '<div class="success" style="margin-top: 15px;">';
                        echo '<strong>✅ SUCCESSO!</strong> Le assegnazioni sono state create. ';
                        echo "L'utente {$user->firstname} {$user->lastname} può ora completare le autovalutazioni.";
                        echo '</div>';
                    }
                }
            }
        }
    }
    echo '</div>';
}

// Lista tentativi recenti senza assegnazioni
echo '<div class="card">';
echo '<h2>Quiz Completati Oggi (senza assegnazioni)</h2>';

$today = strtotime('today');
$attempts = $DB->get_records_sql("
    SELECT qa.id, qa.userid, qa.quiz, qa.uniqueid, qa.timefinish,
           q.name as quizname, q.course, u.firstname, u.lastname
    FROM {quiz_attempts} qa
    JOIN {quiz} q ON q.id = qa.quiz
    JOIN {user} u ON u.id = qa.userid
    WHERE qa.state = 'finished'
    AND qa.timefinish > ?
    ORDER BY qa.timefinish DESC
", [$today]);

if (empty($attempts)) {
    echo '<div class="warning">Nessun quiz completato oggi</div>';
} else {
    echo '<table>';
    echo '<tr><th>ID</th><th>Utente</th><th>Quiz</th><th>Ora</th><th>Assegn.</th><th>Azione</th></tr>';

    foreach ($attempts as $att) {
        // Conta assegnazioni da questo quiz per questo utente
        $assign_count = $DB->count_records_select('local_selfassessment_assign',
            "userid = ? AND source = 'quiz' AND sourceid = ?",
            [$att->userid, $att->quiz]
        );

        $time = date('H:i', $att->timefinish);
        $badge = $assign_count > 0
            ? "<span style='color: green;'>✓ {$assign_count}</span>"
            : "<span style='color: red;'>0</span>";

        $action = $assign_count == 0
            ? "<a href='?action=process&attemptid={$att->id}' class='btn btn-success'>▶ Elabora</a>"
            : "<span style='color: #999;'>OK</span>";

        echo "<tr>";
        echo "<td>{$att->id}</td>";
        echo "<td>{$att->firstname} {$att->lastname}</td>";
        echo "<td>{$att->quizname}</td>";
        echo "<td>{$time}</td>";
        echo "<td>{$badge}</td>";
        echo "<td>{$action}</td>";
        echo "</tr>";
    }
    echo '</table>';

    // Bottone elabora tutti
    $missing = array_filter($attempts, function($a) use ($DB) {
        return !$DB->count_records_select('local_selfassessment_assign',
            "userid = ? AND source = 'quiz' AND sourceid = ?",
            [$a->userid, $a->quiz]
        );
    });

    if (!empty($missing)) {
        echo '<div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">';
        echo '<strong>' . count($missing) . ' quiz senza assegnazioni</strong><br><br>';
        echo '<a href="catchup_assignments.php" class="btn btn-danger">🔄 Elabora TUTTI con Catchup</a>';
        echo '</div>';
    }
}
echo '</div>';

// Link utili
echo '<div class="card">';
echo '<h2>Link Utili</h2>';
echo '<a href="catchup_assignments.php" class="btn btn-primary">🔄 Catchup Assegnazioni</a>';
echo '<a href="diagnose.php" class="btn btn-primary">🔍 Diagnosi</a>';
echo '<a href="debug_chimfarm.php" class="btn btn-primary">🧪 Debug CHIMFARM</a>';
echo '<a href="' . (new moodle_url('/admin/purgecaches.php'))->out() . '" class="btn btn-danger">🗑️ Svuota Cache</a>';
echo '</div>';

?>
</div>
<?php
echo $OUTPUT->footer();
