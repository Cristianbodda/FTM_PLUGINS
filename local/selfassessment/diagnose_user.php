<?php
/**
 * Diagnostica autovalutazione per un utente specifico
 *
 * Uso: /local/selfassessment/diagnose_user.php?userid=X&quizid=Y
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$userid = required_param('userid', PARAM_INT);
$quizid = optional_param('quizid', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/diagnose_user.php', ['userid' => $userid]));
$PAGE->set_title('Diagnostica Autovalutazione');

echo $OUTPUT->header();

$user = $DB->get_record('user', ['id' => $userid]);
if (!$user) {
    echo '<div class="alert alert-danger">Utente non trovato</div>';
    echo $OUTPUT->footer();
    die();
}

echo '<h2>Diagnostica Autovalutazione per: ' . fullname($user) . ' (ID: ' . $userid . ')</h2>';

// 1. Settore primario
echo '<h3>1. Settore Primario</h3>';
$dbman = $DB->get_manager();
if ($dbman->table_exists('local_student_sectors')) {
    $primarySector = $DB->get_record('local_student_sectors', ['userid' => $userid, 'is_primary' => 1]);
    if ($primarySector) {
        echo '<div class="alert alert-info">Settore primario: <strong>' . $primarySector->sector . '</strong></div>';
    } else {
        echo '<div class="alert alert-warning">Nessun settore primario assegnato - tutte le competenze saranno assegnate</div>';
    }

    $allSectors = $DB->get_records('local_student_sectors', ['userid' => $userid]);
    if ($allSectors) {
        echo '<p>Tutti i settori: ';
        foreach ($allSectors as $s) {
            echo '<span class="badge badge-secondary" style="margin-right:5px;">' . $s->sector . ($s->is_primary ? ' (primario)' : '') . '</span>';
        }
        echo '</p>';
    }
} else {
    echo '<div class="alert alert-secondary">Tabella local_student_sectors non esiste - filtro settore disabilitato</div>';
}

// 2. Status autovalutazione
echo '<h3>2. Status Autovalutazione</h3>';
if ($dbman->table_exists('local_selfassessment_status')) {
    $status = $DB->get_record('local_selfassessment_status', ['userid' => $userid]);
    if ($status) {
        echo '<ul>';
        echo '<li>Abilitato: ' . ($status->enabled ? 'SI' : 'NO') . '</li>';
        echo '<li>Skip permanente: ' . ($status->skip_accepted ? '<strong style="color:red;">SI</strong>' : 'NO') . '</li>';
        if ($status->disabled_by) {
            echo '<li>Disabilitato da: ' . $status->disabled_by . ' - ' . $status->reason . '</li>';
        }
        echo '</ul>';

        if ($status->skip_accepted) {
            echo '<div class="alert alert-danger">ATTENZIONE: Utente ha skip permanente attivo. Non riceverà popup autovalutazione!</div>';
        }
    } else {
        echo '<div class="alert alert-success">Nessuno status speciale - autovalutazione abilitata di default</div>';
    }
} else {
    echo '<div class="alert alert-secondary">Tabella local_selfassessment_status non esiste</div>';
}

// 3. Competenze già assegnate per autovalutazione
echo '<h3>3. Competenze Assegnate per Autovalutazione</h3>';
if ($dbman->table_exists('local_selfassessment_assign')) {
    $assigned = $DB->get_records_sql("
        SELECT sa.*, c.idnumber, c.shortname
        FROM {local_selfassessment_assign} sa
        JOIN {competency} c ON c.id = sa.competencyid
        WHERE sa.userid = ?
        ORDER BY sa.timecreated DESC
    ", [$userid]);

    if ($assigned) {
        echo '<div class="alert alert-info">Trovate ' . count($assigned) . ' competenze assegnate</div>';
        echo '<table class="table table-sm"><thead><tr><th>Competenza</th><th>Fonte</th><th>Data</th></tr></thead><tbody>';
        foreach (array_slice($assigned, 0, 20) as $a) {
            echo '<tr>';
            echo '<td>' . $a->idnumber . ' - ' . $a->shortname . '</td>';
            echo '<td>' . $a->source . ' (ID: ' . $a->sourceid . ')</td>';
            echo '<td>' . userdate($a->timecreated) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        if (count($assigned) > 20) {
            echo '<p>... e altre ' . (count($assigned) - 20) . '</p>';
        }
    } else {
        echo '<div class="alert alert-warning">Nessuna competenza assegnata per autovalutazione</div>';
    }
} else {
    echo '<div class="alert alert-danger">Tabella local_selfassessment_assign non esiste!</div>';
}

// 4. Autovalutazioni completate
echo '<h3>4. Autovalutazioni Completate</h3>';
if ($dbman->table_exists('local_selfassessment')) {
    $completed = $DB->count_records('local_selfassessment', ['userid' => $userid]);
    echo '<div class="alert alert-info">Autovalutazioni completate: ' . $completed . '</div>';
} else {
    echo '<div class="alert alert-secondary">Tabella local_selfassessment non esiste</div>';
}

// 5. Quiz completati
echo '<h3>5. Quiz Completati dall\'utente</h3>';
$attempts = $DB->get_records_sql("
    SELECT qa.id, qa.quiz, qa.state, qa.timefinish, q.name as quizname, q.course
    FROM {quiz_attempts} qa
    JOIN {quiz} q ON q.id = qa.quiz
    WHERE qa.userid = ?
    ORDER BY qa.timefinish DESC
    LIMIT 10
", [$userid]);

if ($attempts) {
    echo '<table class="table table-sm"><thead><tr><th>Quiz</th><th>Corso</th><th>Stato</th><th>Completato</th><th>Azione</th></tr></thead><tbody>';
    foreach ($attempts as $a) {
        echo '<tr>';
        echo '<td>' . $a->quizname . '</td>';
        echo '<td>' . $a->course . '</td>';
        echo '<td>' . $a->state . '</td>';
        echo '<td>' . ($a->timefinish ? userdate($a->timefinish) : '-') . '</td>';
        echo '<td><a href="?userid=' . $userid . '&quizid=' . $a->quiz . '" class="btn btn-sm btn-primary">Analizza</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<div class="alert alert-warning">Nessun quiz completato</div>';
}

// 6. Analisi quiz specifico
if ($quizid) {
    echo '<h3>6. Analisi Quiz ID: ' . $quizid . '</h3>';

    $quiz = $DB->get_record('quiz', ['id' => $quizid]);
    if ($quiz) {
        echo '<p><strong>Quiz:</strong> ' . $quiz->name . ' (Corso: ' . $quiz->course . ')</p>';

        // Trova ultimo attempt
        $attempt = $DB->get_record_sql("
            SELECT * FROM {quiz_attempts}
            WHERE userid = ? AND quiz = ?
            ORDER BY timefinish DESC
            LIMIT 1
        ", [$userid, $quizid]);

        if ($attempt) {
            echo '<p><strong>Ultimo attempt:</strong> ID ' . $attempt->id . ', stato: ' . $attempt->state . '</p>';

            // Trova domande
            $questions = $DB->get_records_sql("
                SELECT DISTINCT qa.questionid
                FROM {question_attempts} qa
                WHERE qa.questionusageid = ?
            ", [$attempt->uniqueid]);

            echo '<p><strong>Domande nel quiz:</strong> ' . count($questions) . '</p>';

            if ($questions) {
                $questionids = array_keys($questions);

                // Cerca competenze associate
                $comp_tables = [
                    'qbank_competenciesbyquestion' => 'questionid',
                    'qbank_comp_question' => 'questionid',
                    'local_competencymanager_qcomp' => 'questionid'
                ];

                $foundTable = null;
                foreach ($comp_tables as $table => $field) {
                    if ($dbman->table_exists($table)) {
                        $foundTable = $table;
                        break;
                    }
                }

                if ($foundTable) {
                    echo '<p><strong>Tabella competenze:</strong> ' . $foundTable . '</p>';

                    list($sql_in, $params) = $DB->get_in_or_equal($questionids);
                    $mappings = $DB->get_records_sql("
                        SELECT cq.*, c.idnumber, c.shortname
                        FROM {{$foundTable}} cq
                        JOIN {competency} c ON c.id = cq.competencyid
                        WHERE cq.questionid $sql_in
                    ", $params);

                    if ($mappings) {
                        echo '<div class="alert alert-success">Trovate ' . count($mappings) . ' competenze associate alle domande</div>';
                        echo '<table class="table table-sm"><thead><tr><th>Question ID</th><th>Competenza</th><th>Settore (estratto)</th></tr></thead><tbody>';

                        foreach ($mappings as $m) {
                            $parts = explode('_', $m->idnumber);
                            $sector = strtoupper($parts[0] ?? '');

                            $sectorMatch = '';
                            if (isset($primarySector) && $primarySector) {
                                $normalizedPrimary = str_replace(['À', 'È', 'É', 'Ì', 'Ò', 'Ù'], ['A', 'E', 'E', 'I', 'O', 'U'], strtoupper($primarySector->sector));
                                $normalizedSector = str_replace(['À', 'È', 'É', 'Ì', 'Ò', 'Ù'], ['A', 'E', 'E', 'I', 'O', 'U'], $sector);
                                if ($normalizedSector === $normalizedPrimary) {
                                    $sectorMatch = '<span class="badge badge-success">MATCH</span>';
                                } else {
                                    $sectorMatch = '<span class="badge badge-danger">NO MATCH (verrebbe filtrata)</span>';
                                }
                            }

                            echo '<tr>';
                            echo '<td>' . $m->questionid . '</td>';
                            echo '<td>' . $m->idnumber . '</td>';
                            echo '<td>' . $sector . ' ' . $sectorMatch . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table>';
                    } else {
                        echo '<div class="alert alert-danger">NESSUNA competenza associata alle domande del quiz! Questo è il problema.</div>';
                        echo '<p>Le domande di questo quiz non hanno competenze assegnate nella tabella ' . $foundTable . '.</p>';
                        echo '<p><strong>Soluzione:</strong> Assegna le competenze alle domande tramite Question Bank o Setup Universale.</p>';
                    }
                } else {
                    echo '<div class="alert alert-danger">Nessuna tabella di mapping competenze-domande trovata!</div>';
                }
            }
        } else {
            echo '<div class="alert alert-warning">Nessun attempt trovato per questo quiz</div>';
        }
    } else {
        echo '<div class="alert alert-danger">Quiz non trovato</div>';
    }
}

// Link utili
echo '<h3>Link Utili</h3>';
echo '<ul>';
echo '<li><a href="/local/selfassessment/compile.php" target="_blank">Pagina Autovalutazione</a></li>';
echo '<li><a href="/local/selfassessment/index.php" target="_blank">Gestione Autovalutazione</a></li>';
echo '</ul>';

echo $OUTPUT->footer();
