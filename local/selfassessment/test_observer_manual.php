<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Diagnostic tool: manually test self-assessment observer for a user.
 *
 * Usage: /local/selfassessment/test_observer_manual.php?userid=XX
 * Optional: &force=1 to actually assign competencies
 *
 * @package    local_selfassessment
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/selfassessment/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$userid = required_param('userid', PARAM_INT);
$force = optional_param('force', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/test_observer_manual.php', ['userid' => $userid]));
$PAGE->set_title('Test Observer - Diagnostica');

echo $OUTPUT->header();

$user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

echo '<div style="max-width:900px; margin:0 auto; font-family:monospace; font-size:14px;">';
echo '<h2>Diagnostica Observer Autovalutazione</h2>';
echo '<p><strong>Utente:</strong> ' . fullname($user) . ' (id=' . $userid . ', email=' . s($user->email) . ')</p>';
echo '<hr>';

// Step 1: Check capabilities.
echo '<h3>1. Capability check</h3>';
$context = context_system::instance();
$hasComplete = has_capability('local/selfassessment:complete', $context, $userid);
$hasView = has_capability('local/selfassessment:view', $context, $userid);
echo '<p>local/selfassessment:complete = ' . ($hasComplete ? '<span style="color:green">SI</span>' : '<span style="color:red">NO</span>') . '</p>';
echo '<p>local/selfassessment:view = ' . ($hasView ? '<span style="color:orange">SI (coach/admin - reminder NON mostrato)</span>' : '<span style="color:green">NO (studente - ok)</span>') . '</p>';

if ($hasView) {
    echo '<p style="color:red; font-weight:bold;">PROBLEMA: L\'utente ha capability :view, quindi il reminder non viene mai mostrato. Questo ruolo e per coach/admin, non studenti.</p>';
}

// Step 2: Check selfassessment enabled.
echo '<h3>2. Autovalutazione abilitata?</h3>';
$enabled = local_selfassessment_is_enabled($userid);
echo '<p>Abilitata = ' . ($enabled ? '<span style="color:green">SI</span>' : '<span style="color:red">NO (disabilitata)</span>') . '</p>';

$status = $DB->get_record('local_selfassessment_status', ['userid' => $userid]);
if ($status) {
    echo '<p>Record status: enabled=' . $status->enabled . ', skip_accepted=' . $status->skip_accepted . '</p>';
} else {
    echo '<p>Nessun record status (default: abilitata)</p>';
}

// Step 3: Check quiz attempts.
echo '<h3>3. Quiz attempts dello studente</h3>';
$attempts = $DB->get_records_sql("
    SELECT qa.id, qa.quiz, qa.uniqueid, qa.state, qa.timefinish, q.name as quizname, q.course
    FROM {quiz_attempts} qa
    JOIN {quiz} q ON q.id = qa.quiz
    WHERE qa.userid = ?
    ORDER BY qa.timefinish DESC
", [$userid]);

if (empty($attempts)) {
    echo '<p style="color:red;">Nessun tentativo quiz trovato per questo utente.</p>';
} else {
    echo '<p>Trovati <strong>' . count($attempts) . '</strong> tentativi:</p>';
    echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse; width:100%;">';
    echo '<tr style="background:#f0f0f0;"><th>ID</th><th>Quiz</th><th>Stato</th><th>Data</th><th>Domande</th><th>Competenze mappate</th></tr>';

    foreach ($attempts as $att) {
        // Count questions in this attempt.
        $qcount = $DB->count_records_sql("
            SELECT COUNT(DISTINCT qa.questionid)
            FROM {question_attempts} qa
            WHERE qa.questionusageid = ?
        ", [$att->uniqueid]);

        // Get question IDs.
        $questions = $DB->get_records_sql("
            SELECT DISTINCT qa.questionid
            FROM {question_attempts} qa
            WHERE qa.questionusageid = ?
        ", [$att->uniqueid]);
        $questionids = array_keys($questions);

        // Check competency mappings.
        $compcount = 0;
        if (!empty($questionids)) {
            // Find the competency table.
            $comptable = null;
            foreach (['qbank_competenciesbyquestion', 'qbank_comp_question', 'local_competencymanager_qcomp'] as $t) {
                if ($DB->get_manager()->table_exists($t)) {
                    $comptable = $t;
                    break;
                }
            }
            if ($comptable) {
                list($sqlin, $params) = $DB->get_in_or_equal($questionids);
                $compcount = $DB->count_records_sql("
                    SELECT COUNT(DISTINCT competencyid) FROM {{$comptable}} WHERE questionid $sqlin
                ", $params);
            }
        }

        $statecolor = $att->state === 'finished' ? 'green' : 'orange';
        $compcolor = $compcount > 0 ? 'green' : 'red';

        echo '<tr>';
        echo '<td>' . $att->id . '</td>';
        echo '<td>' . s($att->quizname) . ' (id=' . $att->quiz . ')</td>';
        echo '<td style="color:' . $statecolor . ';">' . $att->state . '</td>';
        echo '<td>' . ($att->timefinish ? userdate($att->timefinish) : '-') . '</td>';
        echo '<td>' . $qcount . '</td>';
        echo '<td style="color:' . $compcolor . '; font-weight:bold;">' . $compcount . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}

// Step 4: Check competency mapping table.
echo '<h3>4. Tabella mapping competenze-domande</h3>';
$comptable = null;
foreach (['qbank_competenciesbyquestion', 'qbank_comp_question', 'local_competencymanager_qcomp'] as $t) {
    $exists = $DB->get_manager()->table_exists($t);
    echo '<p>' . $t . ' = ' . ($exists ? '<span style="color:green">ESISTE</span>' : 'non esiste') . '</p>';
    if ($exists && !$comptable) {
        $comptable = $t;
    }
}

if ($comptable) {
    $totalMappings = $DB->count_records($comptable);
    echo '<p>Tabella usata: <strong>' . $comptable . '</strong> (' . $totalMappings . ' mapping totali)</p>';
}

// Step 5: Check student primary sector.
echo '<h3>5. Settore primario studente</h3>';
$dbman = $DB->get_manager();
if ($dbman->table_exists('local_student_sectors')) {
    $sectors = $DB->get_records('local_student_sectors', ['userid' => $userid]);
    if (empty($sectors)) {
        echo '<p>Nessun settore assegnato (ok, nessun filtro applicato)</p>';
    } else {
        foreach ($sectors as $s) {
            $primary = $s->is_primary ? ' <strong>[PRIMARIO]</strong>' : '';
            echo '<p>Settore: ' . s($s->sector) . $primary . '</p>';
        }
    }
} else {
    echo '<p>Tabella local_student_sectors non esiste</p>';
}

// Step 6: Check already assigned competencies.
echo '<h3>6. Competenze gia assegnate per autovalutazione</h3>';
$assigned = $DB->get_records('local_selfassessment_assign', ['userid' => $userid]);
echo '<p>Record in local_selfassessment_assign: <strong>' . count($assigned) . '</strong></p>';

if (!empty($assigned)) {
    echo '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;">';
    echo '<tr style="background:#f0f0f0;"><th>Competency ID</th><th>Idnumber</th><th>Source</th><th>Data</th></tr>';
    foreach ($assigned as $a) {
        $comp = $DB->get_record('competency', ['id' => $a->competencyid]);
        echo '<tr>';
        echo '<td>' . $a->competencyid . '</td>';
        echo '<td>' . ($comp ? s($comp->idnumber) : '?') . '</td>';
        echo '<td>' . s($a->source) . '</td>';
        echo '<td>' . userdate($a->timecreated) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}

// Step 7: Check completed assessments.
echo '<h3>7. Autovalutazioni completate</h3>';
$assessed = $DB->get_records('local_selfassessment', ['userid' => $userid]);
echo '<p>Record in local_selfassessment: <strong>' . count($assessed) . '</strong></p>';

// Step 8: Reminder status.
echo '<h3>8. Stato reminder</h3>';
$reminderStatus = local_selfassessment_get_reminder_status($userid);
echo '<pre>' . print_r($reminderStatus, true) . '</pre>';

// Step 9: Simulate observer (dry run or force).
if (!empty($attempts)) {
    echo '<h3>9. Simulazione observer' . ($force ? ' (FORCE - assegnamento reale!)' : ' (dry run)') . '</h3>';

    // Use the latest finished attempt.
    $latestAttempt = null;
    foreach ($attempts as $att) {
        if ($att->state === 'finished') {
            $latestAttempt = $att;
            break;
        }
    }

    if (!$latestAttempt) {
        echo '<p style="color:red;">Nessun tentativo completato (state=finished)</p>';
    } else {
        echo '<p>Uso tentativo: #' . $latestAttempt->id . ' - ' . s($latestAttempt->quizname) . '</p>';

        // Get questions.
        $questions = $DB->get_records_sql("
            SELECT DISTINCT qa.questionid
            FROM {question_attempts} qa
            WHERE qa.questionusageid = ?
        ", [$latestAttempt->uniqueid]);
        $questionids = array_keys($questions);
        echo '<p>Domande nel tentativo: ' . count($questionids) . '</p>';

        if (!empty($questionids) && $comptable) {
            list($sqlin, $params) = $DB->get_in_or_equal($questionids);
            $mappings = $DB->get_records_sql("
                SELECT DISTINCT competencyid FROM {{$comptable}} WHERE questionid $sqlin
            ", $params);

            echo '<p>Competenze trovate dai mapping: <strong>' . count($mappings) . '</strong></p>';

            if (!empty($mappings)) {
                // Get primary sector.
                $primarySector = null;
                if ($dbman->table_exists('local_student_sectors')) {
                    $rec = $DB->get_record('local_student_sectors', ['userid' => $userid, 'is_primary' => 1]);
                    $primarySector = $rec ? $rec->sector : null;
                }

                $genericSectors = ['GEN', 'GENERICO', 'GENERICHE', 'TRASVERSALI'];
                $wouldAssign = 0;
                $skippedSector = 0;
                $alreadyExists = 0;

                echo '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse; width:100%;">';
                echo '<tr style="background:#f0f0f0;"><th>Comp ID</th><th>Idnumber</th><th>Settore estratto</th><th>Risultato</th></tr>';

                foreach ($mappings as $m) {
                    $comp = $DB->get_record('competency', ['id' => $m->competencyid]);
                    $idnumber = $comp ? $comp->idnumber : '?';

                    // Extract sector.
                    $compSector = '';
                    if ($comp && !empty($comp->idnumber)) {
                        $parts = explode('_', $comp->idnumber);
                        $compSector = strtoupper($parts[0]);
                    }

                    // Check sector filter.
                    $action = '';
                    if (!empty($primarySector) && !empty($compSector)
                        && $compSector !== $primarySector
                        && !in_array($compSector, $genericSectors)) {
                        $action = '<span style="color:orange;">SKIP (settore ' . s($compSector) . ' != ' . s($primarySector) . ')</span>';
                        $skippedSector++;
                    } else {
                        // Check if already exists.
                        $exists = $DB->record_exists('local_selfassessment_assign', [
                            'userid' => $userid,
                            'competencyid' => $m->competencyid,
                        ]);
                        if ($exists) {
                            $action = '<span style="color:blue;">GIA ASSEGNATA</span>';
                            $alreadyExists++;
                        } else {
                            $action = '<span style="color:green; font-weight:bold;">ASSEGNARE</span>';
                            $wouldAssign++;

                            if ($force) {
                                $record = new stdClass();
                                $record->userid = $userid;
                                $record->competencyid = $m->competencyid;
                                $record->source = 'quiz';
                                $record->sourceid = $latestAttempt->quiz;
                                $record->timecreated = time();
                                $DB->insert_record('local_selfassessment_assign', $record);
                                $action = '<span style="color:green; font-weight:bold;">ASSEGNATA!</span>';
                            }
                        }
                    }

                    echo '<tr>';
                    echo '<td>' . $m->competencyid . '</td>';
                    echo '<td>' . s($idnumber) . '</td>';
                    echo '<td>' . s($compSector) . '</td>';
                    echo '<td>' . $action . '</td>';
                    echo '</tr>';
                }
                echo '</table>';

                echo '<p style="margin-top:10px;">';
                echo 'Da assegnare: <strong style="color:green;">' . $wouldAssign . '</strong> | ';
                echo 'Skip settore: <strong style="color:orange;">' . $skippedSector . '</strong> | ';
                echo 'Gia assegnate: <strong style="color:blue;">' . $alreadyExists . '</strong>';
                echo '</p>';

                if (!$force && $wouldAssign > 0) {
                    echo '<p style="margin-top:15px;">';
                    echo '<a href="?userid=' . $userid . '&force=1" ';
                    echo 'style="background:#28a745; color:white; padding:10px 25px; border-radius:6px; text-decoration:none; font-weight:bold;" ';
                    echo 'onclick="return confirm(\'Sei sicuro? Verranno assegnate ' . $wouldAssign . ' competenze.\');">';
                    echo 'FORZA ASSEGNAMENTO (' . $wouldAssign . ' competenze)</a>';
                    echo '</p>';
                }

                if ($force && $wouldAssign > 0) {
                    echo '<p style="background:#d4edda; padding:15px; border-radius:8px; margin-top:15px;">';
                    echo '<strong>Assegnate ' . $wouldAssign . ' competenze!</strong> ';
                    echo 'Lo studente dovrebbe ora vedere il popup di autovalutazione al prossimo login.</p>';
                }
            }
        }
    }
}

echo '<hr>';
echo '<p><a href="' . (new moodle_url('/local/ftm_cpurc/index.php'))->out() . '">Torna alla Dashboard CPURC</a></p>';
echo '</div>';

echo $OUTPUT->footer();
