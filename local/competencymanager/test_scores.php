<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/report_generator.php');

$result = \local_competencymanager\report_generator::get_student_competency_scores(3, 12);
echo "Risultati: " . count($result) . "\n";
if (count($result) > 0) {
    $i = 0;
    foreach ($result as $key => $val) {
        echo "Key: $key => ";
        print_r($val);
        if (++$i >= 3) break;
    }
}
