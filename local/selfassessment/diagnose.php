<?php
// ============================================
// Self Assessment - Script di Diagnosi
// ============================================
// Esegui questo script per capire perché le
// autovalutazioni non vengono assegnate
// ============================================

require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/diagnose.php'));
$PAGE->set_title('Diagnosi Self Assessment');

echo $OUTPUT->header();
?>

<style>
.diag-container { max-width: 1000px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, sans-serif; }
.diag-section { background: white; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.diag-title { font-size: 1.3em; font-weight: 700; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
.diag-ok { color: #28a745; }
.diag-warn { color: #ffc107; }
.diag-error { color: #dc3545; }
.diag-table { width: 100%; border-collapse: collapse; }
.diag-table th, .diag-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
.diag-table th { background: #f8f9fa; font-weight: 600; }
.badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.85em; font-weight: 600; }
.badge-ok { background: #d4edda; color: #155724; }
.badge-warn { background: #fff3cd; color: #856404; }
.badge-error { background: #f8d7da; color: #721c24; }
.code { background: #f4f4f4; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 0.9em; }
</style>

<div class="diag-container">
    <h1>🔍 Diagnosi Self Assessment</h1>
    <p style="color: #666;">Questo script verifica tutti i componenti necessari per il funzionamento delle autovalutazioni.</p>

    <?php
    // ============================================
    // 1. VERIFICA TABELLE DATABASE
    // ============================================
    echo '<div class="diag-section">';
    echo '<div class="diag-title">📊 1. Tabelle Database</div>';

    $dbman = $DB->get_manager();

    $tables_to_check = [
        'local_selfassessment' => 'Autovalutazioni salvate',
        'local_selfassessment_status' => 'Stato abilitazione studenti',
        'local_selfassessment_assign' => 'Assegnazioni competenze',
        'local_selfassessment_reminders' => 'Log reminder inviati',
        'competency' => 'Competenze Moodle',
        'competency_framework' => 'Framework competenze',
        'qbank_competenciesbyquestion' => 'Mapping domande-competenze (plugin qbank)',
        'local_competencymanager_qcomp' => 'Mapping domande-competenze (competencymanager)',
    ];

    echo '<table class="diag-table"><tr><th>Tabella</th><th>Stato</th><th>Record</th><th>Descrizione</th></tr>';

    $mapping_table_found = false;

    foreach ($tables_to_check as $table => $desc) {
        $exists = $dbman->table_exists($table);
        $count = $exists ? $DB->count_records($table) : '-';

        if ($exists) {
            $badge = '<span class="badge badge-ok">✓ Esiste</span>';
            if (strpos($table, 'competenciesbyquestion') !== false || strpos($table, 'qcomp') !== false) {
                $mapping_table_found = true;
            }
        } else {
            $badge = '<span class="badge badge-error">✗ Non esiste</span>';
        }

        echo "<tr><td class='code'>$table</td><td>$badge</td><td>$count</td><td>$desc</td></tr>";
    }
    echo '</table>';

    if (!$mapping_table_found) {
        echo '<div style="margin-top: 15px; padding: 15px; background: #f8d7da; border-radius: 8px; color: #721c24;">
              <strong>⚠️ PROBLEMA:</strong> Nessuna tabella di mapping domande-competenze trovata!<br>
              L\'observer non può assegnare competenze perché non sa quali domande sono collegate a quali competenze.
              </div>';
    }

    echo '</div>';

    // ============================================
    // 2. VERIFICA OBSERVER REGISTRATO
    // ============================================
    echo '<div class="diag-section">';
    echo '<div class="diag-title">👁️ 2. Observer Eventi</div>';

    // Verifica file events.php
    $events_file = __DIR__ . '/db/events.php';
    if (file_exists($events_file)) {
        echo '<p><span class="badge badge-ok">✓</span> File <code>db/events.php</code> esiste</p>';

        // Leggi contenuto
        $content = file_get_contents($events_file);
        if (strpos($content, 'attempt_submitted') !== false) {
            echo '<p><span class="badge badge-ok">✓</span> Observer per <code>attempt_submitted</code> registrato nel file</p>';
        } else {
            echo '<p><span class="badge badge-error">✗</span> Observer per attempt_submitted NON trovato nel file</p>';
        }
    } else {
        echo '<p><span class="badge badge-error">✗</span> File db/events.php NON ESISTE!</p>';
    }

    // Verifica cache eventi Moodle
    $cache = cache::make('core', 'observers');
    echo '<p><em>Nota: Se hai appena installato/aggiornato, svuota la cache Moodle.</em></p>';

    echo '</div>';

    // ============================================
    // 3. VERIFICA QUIZ COMPLETATI
    // ============================================
    echo '<div class="diag-section">';
    echo '<div class="diag-title">📝 3. Quiz Completati</div>';

    $quiz_attempts = $DB->get_records_sql("
        SELECT qa.id, qa.quiz, qa.userid, qa.state, qa.timefinish,
               u.firstname, u.lastname, q.name as quizname
        FROM {quiz_attempts} qa
        JOIN {user} u ON u.id = qa.userid
        JOIN {quiz} q ON q.id = qa.quiz
        WHERE qa.state = 'finished'
        ORDER BY qa.timefinish DESC
        LIMIT 10
    ");

    if (empty($quiz_attempts)) {
        echo '<p><span class="badge badge-warn">⚠</span> Nessun quiz completato trovato</p>';
    } else {
        echo '<p><span class="badge badge-ok">✓</span> ' . count($quiz_attempts) . ' quiz completati (ultimi 10)</p>';
        echo '<table class="diag-table"><tr><th>Studente</th><th>Quiz</th><th>Data</th><th>Attempt ID</th></tr>';
        foreach ($quiz_attempts as $qa) {
            $date = date('d/m/Y H:i', $qa->timefinish);
            echo "<tr><td>{$qa->firstname} {$qa->lastname}</td><td>{$qa->quizname}</td><td>$date</td><td>{$qa->id}</td></tr>";
        }
        echo '</table>';
    }

    echo '</div>';

    // ============================================
    // 4. VERIFICA MAPPING DOMANDE-COMPETENZE
    // ============================================
    echo '<div class="diag-section">';
    echo '<div class="diag-title">🔗 4. Mapping Domande-Competenze</div>';

    $mapping_table = null;
    foreach (['qbank_competenciesbyquestion', 'local_competencymanager_qcomp'] as $t) {
        if ($dbman->table_exists($t)) {
            $mapping_table = $t;
            break;
        }
    }

    if ($mapping_table) {
        $mappings_count = $DB->count_records($mapping_table);
        echo '<p><span class="badge badge-ok">✓</span> Tabella mapping: <code>' . $mapping_table . '</code> (' . $mappings_count . ' record)</p>';

        if ($mappings_count > 0) {
            // Mostra alcuni esempi
            $examples = $DB->get_records_sql("
                SELECT m.*, q.name as questionname, c.idnumber as competency_code, c.shortname as competency_name
                FROM {{$mapping_table}} m
                LEFT JOIN {question} q ON q.id = m.questionid
                LEFT JOIN {competency} c ON c.id = m.competencyid
                LIMIT 5
            ");

            if (!empty($examples)) {
                echo '<table class="diag-table"><tr><th>Domanda</th><th>Competenza</th></tr>';
                foreach ($examples as $ex) {
                    $qname = $ex->questionname ?? 'ID: ' . $ex->questionid;
                    $cname = $ex->competency_code ?? 'ID: ' . $ex->competencyid;
                    echo "<tr><td>$qname</td><td class='code'>$cname</td></tr>";
                }
                echo '</table>';
            }
        } else {
            echo '<div style="margin-top: 10px; padding: 15px; background: #fff3cd; border-radius: 8px; color: #856404;">
                  <strong>⚠️ ATTENZIONE:</strong> La tabella mapping esiste ma è VUOTA!<br>
                  Le domande del quiz non sono collegate a nessuna competenza.
                  </div>';
        }
    } else {
        echo '<p><span class="badge badge-error">✗</span> Nessuna tabella di mapping trovata</p>';
    }

    echo '</div>';

    // ============================================
    // 5. VERIFICA ASSEGNAZIONI
    // ============================================
    echo '<div class="diag-section">';
    echo '<div class="diag-title">📋 5. Assegnazioni Autovalutazione</div>';

    if ($dbman->table_exists('local_selfassessment_assign')) {
        $assigns_count = $DB->count_records('local_selfassessment_assign');
        echo '<p>Totale assegnazioni: <strong>' . $assigns_count . '</strong></p>';

        // Per sorgente
        $by_source = $DB->get_records_sql("
            SELECT source, COUNT(*) as cnt
            FROM {local_selfassessment_assign}
            GROUP BY source
        ");

        if (!empty($by_source)) {
            echo '<table class="diag-table"><tr><th>Sorgente</th><th>Conteggio</th></tr>';
            foreach ($by_source as $s) {
                echo "<tr><td>{$s->source}</td><td>{$s->cnt}</td></tr>";
            }
            echo '</table>';
        }

        // Ultime assegnazioni
        $recent = $DB->get_records_sql("
            SELECT sa.*, u.firstname, u.lastname, c.idnumber as comp_code
            FROM {local_selfassessment_assign} sa
            JOIN {user} u ON u.id = sa.userid
            JOIN {competency} c ON c.id = sa.competencyid
            ORDER BY sa.timecreated DESC
            LIMIT 5
        ");

        if (!empty($recent)) {
            echo '<h4>Ultime 5 assegnazioni:</h4>';
            echo '<table class="diag-table"><tr><th>Studente</th><th>Competenza</th><th>Sorgente</th><th>Data</th></tr>';
            foreach ($recent as $r) {
                $date = date('d/m/Y H:i', $r->timecreated);
                echo "<tr><td>{$r->firstname} {$r->lastname}</td><td class='code'>{$r->comp_code}</td><td>{$r->source}</td><td>$date</td></tr>";
            }
            echo '</table>';
        }

        if ($assigns_count == 0) {
            echo '<div style="margin-top: 10px; padding: 15px; background: #f8d7da; border-radius: 8px; color: #721c24;">
                  <strong>⚠️ PROBLEMA:</strong> Nessuna assegnazione trovata!<br>
                  Gli studenti non vedranno competenze da autovalutare.<br><br>
                  <strong>Possibili cause:</strong><br>
                  - L\'observer non sta funzionando<br>
                  - Le domande non sono mappate a competenze<br>
                  - Nessun quiz è stato completato dopo l\'installazione del plugin
                  </div>';
        }
    }

    echo '</div>';

    // ============================================
    // 6. TEST STUDENTE SPECIFICO
    // ============================================
    echo '<div class="diag-section">';
    echo '<div class="diag-title">👤 6. Test per Studente Specifico</div>';

    $test_userid = optional_param('userid', 0, PARAM_INT);

    echo '<form method="get" style="margin-bottom: 20px;">
          <label>ID Studente: <input type="number" name="userid" value="' . $test_userid . '" style="width: 100px; padding: 8px;"></label>
          <button type="submit" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Verifica</button>
          </form>';

    if ($test_userid > 0) {
        $user = $DB->get_record('user', ['id' => $test_userid]);

        if ($user) {
            echo '<h4>Studente: ' . fullname($user) . ' (ID: ' . $test_userid . ')</h4>';

            // Quiz completati
            $user_quizzes = $DB->count_records('quiz_attempts', ['userid' => $test_userid, 'state' => 'finished']);
            echo '<p>Quiz completati: <strong>' . $user_quizzes . '</strong></p>';

            // Competenze assegnate
            $user_assigns = $DB->count_records('local_selfassessment_assign', ['userid' => $test_userid]);
            echo '<p>Competenze assegnate per autovalutazione: <strong>' . $user_assigns . '</strong></p>';

            // Autovalutazioni fatte
            $user_assessments = $DB->count_records('local_selfassessment', ['userid' => $test_userid]);
            echo '<p>Autovalutazioni completate: <strong>' . $user_assessments . '</strong></p>';

            // Status
            $status = $DB->get_record('local_selfassessment_status', ['userid' => $test_userid]);
            $enabled = !$status || $status->enabled == 1;
            echo '<p>Autovalutazione abilitata: <strong>' . ($enabled ? 'Sì ✓' : 'No ✗') . '</strong></p>';

            // Capability
            $context = context_system::instance();
            $has_cap = has_capability('local/selfassessment:complete', $context, $test_userid);
            echo '<p>Ha capability complete: <strong>' . ($has_cap ? 'Sì ✓' : 'No ✗') . '</strong></p>';

            if ($user_assigns == 0 && $user_quizzes > 0) {
                echo '<div style="margin-top: 10px; padding: 15px; background: #fff3cd; border-radius: 8px; color: #856404;">
                      <strong>⚠️</strong> Lo studente ha quiz completati ma nessuna competenza assegnata.<br>
                      Probabilmente l\'observer non ha funzionato o le domande non erano mappate.
                      </div>';
            }
        } else {
            echo '<p class="badge badge-error">Studente non trovato</p>';
        }
    }

    echo '</div>';

    // ============================================
    // 7. AZIONI SUGGERITE
    // ============================================
    echo '<div class="diag-section">';
    echo '<div class="diag-title">🛠️ 7. Azioni Suggerite</div>';

    echo '<ol style="line-height: 2;">';

    if (!$mapping_table_found) {
        echo '<li><strong style="color: #dc3545;">CRITICO:</strong> Installa il plugin <code>question/bank/competenciesbyquestion</code> e mappa le domande alle competenze</li>';
    } else if ($mappings_count == 0) {
        echo '<li><strong style="color: #ffc107;">IMPORTANTE:</strong> Vai nell\'editor domande e associa le competenze alle domande del quiz</li>';
    }

    if ($assigns_count == 0) {
        echo '<li>Esegui <a href="catchup_assignments.php" style="color: #007bff;">Catch-up Assignments</a> per creare assegnazioni retroattive dai quiz già completati</li>';
    }

    echo '<li>Svuota la cache Moodle: <code>Amministrazione > Sviluppo > Svuota tutte le cache</code></li>';
    echo '<li>Verifica che gli studenti abbiano il ruolo corretto con la capability <code>local/selfassessment:complete</code></li>';
    echo '<li>Testa completando un quiz con domande mappate e verifica se appare l\'assegnazione</li>';

    echo '</ol>';

    echo '</div>';
    ?>

    <div style="text-align: center; padding: 20px; color: #666;">
        <a href="index.php" style="color: #007bff;">← Torna alla Dashboard Self Assessment</a>
    </div>
</div>

<?php
echo $OUTPUT->footer();
