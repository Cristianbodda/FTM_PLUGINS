<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$userid = required_param('userid', PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/competencymanager/debug_coach_eval.php');
echo $OUTPUT->header();
echo '<pre>';

$user = $DB->get_record('user', ['id' => $userid]);
echo "Studente: " . fullname($user) . " (id=$userid)\n\n";

// 1. Check local_coach_evaluations (ALL statuses).
echo "=== local_coach_evaluations (TUTTI gli status) ===\n";
if ($DB->get_manager()->table_exists('local_coach_evaluations')) {
    $evals = $DB->get_records('local_coach_evaluations', ['studentid' => $userid]);
    if (empty($evals)) {
        echo "  NESSUN RECORD per questo studente.\n";
    } else {
        foreach ($evals as $e) {
            echo "  ID={$e->id} | coachid={$e->coachid} | status={$e->status} | date=" . userdate($e->evaluation_date ?? 0, '%d/%m/%Y') . "\n";
        }
    }
} else {
    echo "  Tabella NON ESISTE.\n";
}

// 2. Check local_coach_eval_ratings.
echo "\n=== local_coach_eval_ratings ===\n";
if ($DB->get_manager()->table_exists('local_coach_eval_ratings')) {
    $sql = "SELECT r.id, r.evaluationid, r.competencyid, r.rating, r.notes, c.idnumber, c.shortname
            FROM {local_coach_eval_ratings} r
            JOIN {competency} c ON c.id = r.competencyid
            JOIN {local_coach_evaluations} e ON e.id = r.evaluationid
            WHERE e.studentid = ?
            ORDER BY c.idnumber";
    $ratings = $DB->get_records_sql($sql, [$userid]);
    echo "  Totale ratings: " . count($ratings) . "\n";
    foreach (array_slice($ratings, 0, 10) as $r) {
        echo "  evalid={$r->evaluationid} | {$r->idnumber} | rating={$r->rating} | {$r->shortname}\n";
    }
    if (count($ratings) > 10) echo "  ... e altri " . (count($ratings) - 10) . "\n";
} else {
    echo "  Tabella NON ESISTE.\n";
}

// 3. Check local_passport_comments (maybe coach scores are here?).
echo "\n=== local_passport_comments ===\n";
if ($DB->get_manager()->table_exists('local_passport_comments')) {
    $comments = $DB->get_records('local_passport_comments', ['userid' => $userid]);
    echo "  Totale: " . count($comments) . "\n";
    foreach ($comments as $c) {
        echo "  area={$c->area_code} | comment=" . substr($c->comment ?? '', 0, 50) . "\n";
    }
} else {
    echo "  Tabella NON ESISTE.\n";
}

// 4. Check if there's a garage_config with coach eval enabled.
echo "\n=== local_garage_config ===\n";
if ($DB->get_manager()->table_exists('local_garage_config')) {
    $gc = $DB->get_records('local_garage_config', ['userid' => $userid]);
    foreach ($gc as $g) {
        echo "  courseid={$g->courseid} | sections=" . ($g->enabled_sections ?? 'null') . "\n";
    }
} else {
    echo "  Tabella NON ESISTE.\n";
}

// 5. Any other table that might store coach ratings?
echo "\n=== Altre tabelle con 'coach' o 'eval' ===\n";
$tables = $DB->get_tables();
foreach ($tables as $t) {
    if (stripos($t, 'coach') !== false || stripos($t, 'eval') !== false) {
        $count = $DB->count_records($t);
        echo "  {$t}: {$count} record\n";
    }
}

// 6. Check local_coach_eval_history — this is where inline edits are saved.
echo "\n=== local_coach_eval_history (ultimi 20 per questo studente) ===\n";
if ($DB->get_manager()->table_exists('local_coach_eval_history')) {
    // Get columns first.
    $columns = $DB->get_columns('local_coach_eval_history');
    echo "  Colonne: " . implode(', ', array_keys($columns)) . "\n\n";

    $sql = "SELECT h.*
            FROM {local_coach_eval_history} h
            JOIN {local_coach_eval_ratings} r ON r.id = h.ratingid
            JOIN {local_coach_evaluations} e ON e.id = r.evaluationid
            WHERE e.studentid = ?
            ORDER BY h.id DESC";
    $history = $DB->get_records_sql($sql, [$userid], 0, 20);
    echo "  Trovati: " . count($history) . "\n";
    foreach ($history as $h) {
        echo "  " . json_encode($h) . "\n";
    }
}

// 7. Check actual current ratings (last value per competency from history).
echo "\n=== Rating ATTUALI (ultimo valore da history per competenza) ===\n";
$sql = "SELECT r.id as ratingid, c.idnumber, r.rating as base_rating,
               (SELECT h2.new_rating FROM {local_coach_eval_history} h2 WHERE h2.ratingid = r.id ORDER BY h2.id DESC LIMIT 1) as last_history_rating
        FROM {local_coach_eval_ratings} r
        JOIN {competency} c ON c.id = r.competencyid
        JOIN {local_coach_evaluations} e ON e.id = r.evaluationid
        WHERE e.studentid = ?
        ORDER BY c.idnumber";
$current = $DB->get_records_sql($sql, [$userid]);
$non_zero = 0;
foreach ($current as $c) {
    $effective = $c->last_history_rating ?? $c->base_rating;
    if ($effective > 0) {
        $non_zero++;
        echo "  {$c->idnumber}: base={$c->base_rating} | history={$c->last_history_rating} | effective={$effective}\n";
    }
}
echo "  Con valore >0: {$non_zero} / " . count($current) . "\n";

echo '</pre>';
echo $OUTPUT->footer();
