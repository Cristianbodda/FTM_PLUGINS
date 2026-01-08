<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/assessment_manager.php');

$report = \local_competencymanager\assessment_manager::generate_colloquio_report(3, null, 12);

echo "Total competencies: " . $report->stats->total_competencies . "\n";
echo "With quiz: " . $report->stats->with_quiz . "\n";
echo "With self-assessment: " . $report->stats->with_self_assessment . "\n\n";

echo "Primi 3 gaps:\n";
$i = 0;
foreach ($report->gaps as $gap) {
    echo "- {$gap->competency_idnumber}: quiz_percentage=" . ($gap->quiz_percentage ?? 'NULL') . ", self_assessment=" . ($gap->self_assessment ?? 'NULL') . "\n";
    if (++$i >= 3) break;
}
