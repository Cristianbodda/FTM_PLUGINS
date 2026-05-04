<?php
/**
 * Debug: confronta idnumber tra coach_eval_ratings e areasData competencies.
 */
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$userid = required_param('userid', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
echo $OUTPUT->header();
echo '<pre style="font-size:12px;">';

// 1. Carica coach ratings.
$eval = $DB->get_record_sql(
    "SELECT id FROM {local_coach_evaluations} WHERE studentid = ? ORDER BY id DESC LIMIT 1",
    [$userid]
);

$coachRatings = [];
if ($eval) {
    $ratings = $DB->get_records_sql(
        "SELECT r.id, c.idnumber, r.rating FROM {local_coach_eval_ratings} r
         JOIN {competency} c ON c.id = r.competencyid WHERE r.evaluationid = ?",
        [$eval->id]
    );
    foreach ($ratings as $r) {
        $coachRatings[$r->idnumber] = $r->rating;
    }
}

echo "Coach ratings trovati: " . count($coachRatings) . "\n";
echo "Primi 5 idnumber coach: " . implode(', ', array_slice(array_keys($coachRatings), 0, 5)) . "\n\n";

// 2. Carica areasData (stessa logica del passaporto).
require_once(__DIR__ . '/area_mapping.php');
$radardata = \local_competencymanager\report_generator::get_radar_chart_data($userid, $courseid);
$competencies = $radardata['competencies'];

echo "Competenze dai quiz: " . count($competencies) . "\n";
echo "Primi 5 idnumber quiz: ";
$first5 = array_slice($competencies, 0, 5);
foreach ($first5 as $c) { echo ($c['idnumber'] ?? '?') . ', '; }
echo "\n\n";

// 3. Confronto: per ogni competenza quiz, cerca match in coach.
$areasData = passport_aggregate_by_area($competencies, [], '');

echo "=== CONFRONTO PER AREA ===\n\n";
foreach ($areasData as $areaKey => $area) {
    echo "--- {$areaKey}: {$area['name']} ---\n";
    $matched = 0;
    $unmatched = 0;
    $sum = 0;
    foreach ($area['competencies'] as $comp) {
        $idnum = $comp['idnumber'] ?? '';
        if (isset($coachRatings[$idnum])) {
            $r = $coachRatings[$idnum];
            $sum += $r;
            $matched++;
            echo "  MATCH: {$idnum} => rating={$r}\n";
        } else {
            $unmatched++;
            echo "  MISS:  {$idnum} => NON trovato nel coach\n";
        }
    }
    $total = $matched + $unmatched;
    $avg = $matched > 0 ? round($sum / $matched, 2) : 0;
    $pct = $matched > 0 ? round(($avg / 6) * 100, 1) : 0;
    echo "  Matched: {$matched}/{$total} | Avg: {$avg}/6 | Pct: {$pct}%\n\n";
}

// 4. Coach ratings NON matchati (presenti nel coach ma non nei quiz).
echo "=== COACH RATINGS SENZA MATCH NEI QUIZ ===\n";
$allQuizIdnums = [];
foreach ($competencies as $c) { $allQuizIdnums[] = $c['idnumber'] ?? ''; }
$orphan = 0;
foreach ($coachRatings as $idnum => $rating) {
    if (!in_array($idnum, $allQuizIdnums)) {
        if ($rating > 0) {
            echo "  {$idnum} => rating={$rating} (NON nei quiz!)\n";
        }
        $orphan++;
    }
}
echo "  Totale orfani: {$orphan} / " . count($coachRatings) . "\n";

echo '</pre>';
echo $OUTPUT->footer();
