<?php
/**
 * Seed CPURC records for the 3 FTM test students.
 * Run once, then delete this file.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_cpurc/seed_test_students.php'));
$PAGE->set_title('Seed Test Students');

echo $OUTPUT->header();
echo '<h2>Seed CPURC - Test Students</h2>';

$testusers = [
    'ftm_test_low30' => [
        'trainer' => 'CB',
        'measure' => 'FTM',
        'urc_office' => 'Bellinzona',
        'status' => 'In corso',
        'last_profession' => 'Logistico',
    ],
    'ftm_test_medium65' => [
        'trainer' => 'FM',
        'measure' => 'FTM',
        'urc_office' => 'Bellinzona',
        'status' => 'In corso',
        'last_profession' => 'Meccanico',
    ],
    'ftm_test_high95' => [
        'trainer' => 'GM',
        'measure' => 'FTM',
        'urc_office' => 'Locarno',
        'status' => 'In corso',
        'last_profession' => 'Elettricista',
    ],
];

$now = time();
$datestart = $now;
$dateend = $now + (6 * 7 * 86400); // +6 weeks

$created = 0;
$skipped = 0;

foreach ($testusers as $username => $data) {
    $user = $DB->get_record('user', ['username' => $username]);
    if (!$user) {
        echo "<p style='color:red;'>Utente <strong>$username</strong> non trovato in Moodle. Esegui prima la Test Suite per creare gli utenti test.</p>";
        continue;
    }

    // Check if CPURC record already exists.
    $existing = $DB->get_record('local_ftm_cpurc_students', ['userid' => $user->id]);
    if ($existing) {
        echo "<p style='color:orange;'>Utente <strong>{$user->firstname} {$user->lastname}</strong> (id={$user->id}) - Record CPURC gia esistente, skip.</p>";
        $skipped++;
        continue;
    }

    // Insert CPURC record.
    $record = new stdClass();
    $record->userid = $user->id;
    $record->cpurc_id = 'TEST-' . strtoupper(substr($username, 9));
    $record->gender = 'M';
    $record->trainer = $data['trainer'];
    $record->measure = $data['measure'];
    $record->urc_office = $data['urc_office'];
    $record->status = $data['status'];
    $record->last_profession = $data['last_profession'];
    $record->date_start = $datestart;
    $record->date_end_planned = $dateend;
    $record->nationality = 'CH';
    $record->occupation_grade = 100;
    $record->timecreated = $now;
    $record->timemodified = $now;

    $id = $DB->insert_record('local_ftm_cpurc_students', $record);

    echo "<p style='color:green;'>Creato record CPURC (id=$id) per <strong>{$user->firstname} {$user->lastname}</strong> (userid={$user->id}, trainer={$data['trainer']}, professione={$data['last_profession']})</p>";
    $created++;
}

echo "<hr>";
echo "<p><strong>Risultato:</strong> $created creati, $skipped gia esistenti.</p>";

if ($created > 0) {
    echo "<p>Ora vai alla <a href='" . (new moodle_url('/local/ftm_cpurc/index.php'))->out() . "'>Dashboard CPURC</a> per verificare.</p>";
}

echo "<p style='margin-top:20px; color: #888; font-size: 12px;'>Dopo l'uso, cancella questo file dal server.</p>";

echo $OUTPUT->footer();
