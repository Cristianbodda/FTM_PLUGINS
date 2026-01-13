<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/report_generator.php');

$quizResults = \local_competencymanager\report_generator::get_student_competency_scores(3, 12);

// Cerca competenza 1975 (CHIMFARM_1C_01)
$compId = 1975;
$compIdnumber = 'CHIMFARM_1C_01';

echo "Cercando competenza ID=$compId, idnumber=$compIdnumber\n\n";
echo "1. isset(\$quizResults[$compId])? " . (isset($quizResults[$compId]) ? "SI" : "NO") . "\n";
echo "2. isset(\$quizResults['$compIdnumber'])? " . (isset($quizResults[$compIdnumber]) ? "SI" : "NO") . "\n";

// Cerca per idnumber nel valore
echo "3. Cerca nel foreach per idnumber:\n";
foreach ($quizResults as $key => $val) {
    if (is_array($val) && isset($val['idnumber']) && $val['idnumber'] == $compIdnumber) {
        echo "   TROVATO! Key=$key, percentage={$val['percentage']}%\n";
        break;
    }
}
