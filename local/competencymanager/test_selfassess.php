<?php
require_once('../../config.php');
require_login();

$userid = 22;

$sql = "SELECT sa.id, sa.competencyid, sa.level, sa.timecreated, c.idnumber, c.shortname 
        FROM {local_selfassessment} sa 
        JOIN {competency} c ON sa.competencyid = c.id 
        WHERE sa.userid = :userid";

$records = $DB->get_records_sql($sql, ['userid' => $userid]);

echo "<h2>Test local_selfassessment per userid=$userid</h2>";
echo "<p>Record trovati: " . count($records) . "</p>";

if (!empty($records)) {
    echo "<table border='1'><tr><th>ID</th><th>Competency</th><th>Level</th><th>%</th></tr>";
    foreach ($records as $rec) {
        $pct = round(($rec->level / 6) * 100, 1);
        echo "<tr><td>{$rec->id}</td><td>{$rec->idnumber}</td><td>{$rec->level}</td><td>{$pct}%</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>Nessun record trovato!</p>";
}
