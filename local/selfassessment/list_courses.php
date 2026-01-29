<?php
// Lista tutti i corsi
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/selfassessment/list_courses.php'));
$PAGE->set_title('Lista Corsi');

echo $OUTPUT->header();
echo '<h2>📚 Tutti i Corsi nel Sistema</h2>';

$courses = $DB->get_records_sql("
    SELECT id, shortname, fullname, visible
    FROM {course}
    WHERE id > 1
    ORDER BY fullname
");

echo '<table border="1" cellpadding="8" style="border-collapse: collapse; width: 100%;">';
echo '<tr style="background: #f0f0f0;"><th>ID</th><th>Shortname</th><th>Fullname</th><th>Visibile</th></tr>';

foreach ($courses as $c) {
    $vis = $c->visible ? '✓' : '✗';
    $style = stripos($c->shortname, 'chim') !== false || stripos($c->fullname, 'chim') !== false
        ? 'background: #ffffcc; font-weight: bold;'
        : '';
    echo "<tr style='{$style}'><td>{$c->id}</td><td>{$c->shortname}</td><td>{$c->fullname}</td><td>{$vis}</td></tr>";
}
echo '</table>';

echo '<h3 style="margin-top: 30px;">🔍 Corsi dove Fabio (ID 82) è iscritto:</h3>';

$fabio_courses = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.shortname, c.fullname, r.shortname as role
    FROM {course} c
    JOIN {enrol} e ON e.courseid = c.id
    JOIN {user_enrolments} ue ON ue.enrolid = e.id
    JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
    JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = ue.userid
    JOIN {role} r ON r.id = ra.roleid
    WHERE ue.userid = 82
    ORDER BY c.fullname
");

if ($fabio_courses) {
    echo '<table border="1" cellpadding="8" style="border-collapse: collapse;">';
    echo '<tr style="background: #f0f0f0;"><th>ID</th><th>Shortname</th><th>Fullname</th><th>Ruolo</th></tr>';
    foreach ($fabio_courses as $c) {
        echo "<tr><td>{$c->id}</td><td>{$c->shortname}</td><td>{$c->fullname}</td><td><strong>{$c->role}</strong></td></tr>";
    }
    echo '</table>';
} else {
    echo '<p>Nessun corso trovato per Fabio</p>';
}

echo $OUTPUT->footer();
