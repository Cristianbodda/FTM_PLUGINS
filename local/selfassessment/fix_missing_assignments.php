<?php
/**
 * Fix: Assegna manualmente le competenze mancanti per autovalutazione
 *
 * Questo script trova tutti i quiz completati che hanno competenze
 * ma non sono state assegnate per l'autovalutazione, e le assegna.
 *
 * Uso: /local/selfassessment/fix_missing_assignments.php?userid=X (singolo utente)
 *      /local/selfassessment/fix_missing_assignments.php?confirm=1 (tutti gli utenti)
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$userid = optional_param('userid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$dryrun = optional_param('dryrun', 1, PARAM_INT); // Default: dry run (solo mostra cosa farebbe)

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/fix_missing_assignments.php'));
$PAGE->set_title('Fix Assegnazioni Autovalutazione Mancanti');

echo $OUTPUT->header();

echo '<h2>Fix Assegnazioni Autovalutazione Mancanti</h2>';

if ($dryrun) {
    echo '<div class="alert alert-warning">MODALITÀ DRY RUN - Nessuna modifica verrà effettuata. Aggiungi &dryrun=0 per eseguire.</div>';
}

// Trova la tabella di mapping competenze-domande
$dbman = $DB->get_manager();
$comp_tables = [
    'qbank_competenciesbyquestion' => 'questionid',
    'qbank_comp_question' => 'questionid',
    'local_competencymanager_qcomp' => 'questionid'
];

$comp_table = null;
foreach ($comp_tables as $table => $field) {
    if ($dbman->table_exists($table)) {
        $comp_table = $table;
        break;
    }
}

if (!$comp_table) {
    echo '<div class="alert alert-danger">Nessuna tabella di mapping competenze-domande trovata!</div>';
    echo $OUTPUT->footer();
    die();
}

echo '<p>Tabella competenze: <strong>' . $comp_table . '</strong></p>';

// Query per trovare quiz completati
$userFilter = $userid ? "AND qa.userid = $userid" : "";

$sql = "
    SELECT DISTINCT
        qa.userid,
        qa.quiz as quizid,
        qa.id as attemptid,
        qa.uniqueid,
        qa.timefinish,
        u.firstname,
        u.lastname,
        q.name as quizname
    FROM {quiz_attempts} qa
    JOIN {user} u ON u.id = qa.userid
    JOIN {quiz} q ON q.id = qa.quiz
    WHERE qa.state = 'finished'
    AND qa.timefinish > 0
    $userFilter
    ORDER BY qa.timefinish DESC
";

$attempts = $DB->get_records_sql($sql, [], 0, $userid ? 100 : 500);

echo '<p>Quiz completati trovati: <strong>' . count($attempts) . '</strong></p>';

$totalFixed = 0;
$usersFixed = [];

echo '<table class="table table-sm">';
echo '<thead><tr><th>Utente</th><th>Quiz</th><th>Completato</th><th>Competenze Quiz</th><th>Già Assegnate</th><th>Da Assegnare</th><th>Azione</th></tr></thead>';
echo '<tbody>';

foreach ($attempts as $attempt) {
    // Trova domande del quiz
    $questions = $DB->get_records_sql("
        SELECT DISTINCT qa.questionid
        FROM {question_attempts} qa
        WHERE qa.questionusageid = ?
    ", [$attempt->uniqueid]);

    if (empty($questions)) {
        continue;
    }

    $questionids = array_keys($questions);

    // Trova competenze associate alle domande
    list($sql_in, $params) = $DB->get_in_or_equal($questionids);
    $competencies = $DB->get_records_sql("
        SELECT DISTINCT competencyid
        FROM {{$comp_table}}
        WHERE questionid $sql_in
    ", $params);

    if (empty($competencies)) {
        continue;
    }

    $competencyids = array_keys($competencies);

    // Conta quante già assegnate
    list($comp_in, $comp_params) = $DB->get_in_or_equal($competencyids);
    $comp_params[] = $attempt->userid;

    $alreadyAssigned = $DB->get_records_sql("
        SELECT competencyid
        FROM {local_selfassessment_assign}
        WHERE competencyid $comp_in AND userid = ?
    ", $comp_params);

    $alreadyIds = array_keys($alreadyAssigned);
    $toAssign = array_diff($competencyids, $alreadyIds);

    $status = '';
    if (count($toAssign) > 0) {
        $status = '<span class="badge badge-warning">' . count($toAssign) . ' da assegnare</span>';

        if (!$dryrun && ($confirm || $userid)) {
            // Assegna le competenze mancanti
            $now = time();
            foreach ($toAssign as $compid) {
                $record = new stdClass();
                $record->userid = $attempt->userid;
                $record->competencyid = $compid;
                $record->source = 'quiz_fix';
                $record->sourceid = $attempt->quizid;
                $record->timecreated = $now;

                try {
                    $DB->insert_record('local_selfassessment_assign', $record);
                    $totalFixed++;
                } catch (Exception $e) {
                    // Ignora duplicati
                }
            }
            $status = '<span class="badge badge-success">' . count($toAssign) . ' ASSEGNATE</span>';
            $usersFixed[$attempt->userid] = true;
        }
    } else {
        $status = '<span class="badge badge-secondary">OK</span>';
    }

    echo '<tr>';
    echo '<td>' . $attempt->firstname . ' ' . $attempt->lastname . ' (ID:' . $attempt->userid . ')</td>';
    echo '<td>' . substr($attempt->quizname, 0, 40) . '...</td>';
    echo '<td>' . userdate($attempt->timefinish, '%d/%m/%Y %H:%M') . '</td>';
    echo '<td>' . count($competencyids) . '</td>';
    echo '<td>' . count($alreadyIds) . '</td>';
    echo '<td>' . count($toAssign) . '</td>';
    echo '<td>' . $status . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';

echo '<hr>';
echo '<h3>Riepilogo</h3>';
echo '<ul>';
echo '<li>Competenze assegnate in questo run: <strong>' . $totalFixed . '</strong></li>';
echo '<li>Utenti interessati: <strong>' . count($usersFixed) . '</strong></li>';
echo '</ul>';

if ($dryrun && $totalFixed == 0) {
    // Conta quante ne assegnerebbe
    echo '<div class="alert alert-info">';
    echo '<p>Per eseguire effettivamente le assegnazioni:</p>';
    echo '<ul>';
    if ($userid) {
        echo '<li><a href="?userid=' . $userid . '&dryrun=0" class="btn btn-primary">Esegui per utente ' . $userid . '</a></li>';
    }
    echo '<li><a href="?dryrun=0&confirm=1" class="btn btn-danger" onclick="return confirm(\'Sei sicuro? Questo assegnerà le competenze a TUTTI gli utenti con quiz completati.\')">Esegui per TUTTI gli utenti</a></li>';
    echo '</ul>';
    echo '</div>';
}

if (!$dryrun && $totalFixed > 0) {
    echo '<div class="alert alert-success">';
    echo '<strong>Completato!</strong> Assegnate ' . $totalFixed . ' competenze a ' . count($usersFixed) . ' utenti.';
    echo '<br><br>Gli utenti vedranno ora il popup dell\'autovalutazione.';
    echo '</div>';
}

// Test observer
echo '<hr>';
echo '<h3>Test Observer</h3>';
echo '<p>Per verificare se l\'observer funziona, uno studente deve completare un NUOVO quiz. ';
echo 'Se dopo il completamento le competenze vengono assegnate automaticamente, l\'observer funziona.</p>';
echo '<p><strong>Se l\'observer non funziona:</strong></p>';
echo '<ol>';
echo '<li>Vai su Amministrazione > Sviluppo > Svuota cache</li>';
echo '<li>Verifica che il plugin local_selfassessment sia installato correttamente</li>';
echo '<li>Controlla i log di Moodle per eventuali errori</li>';
echo '</ol>';

echo $OUTPUT->footer();
