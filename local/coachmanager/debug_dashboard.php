<?php
require_once('../../config.php');
require_login();

echo '<pre>';
echo "User: " . fullname($USER) . " (id={$USER->id})\n\n";

$context = context_system::instance();

// Check capabilities.
$caps = [
    'local/coachmanager:view',
    'local/coachmanager:manage',
    'local/coachmanager:managejobs',
    'local/ftm_sip:view',
    'local/ftm_sip:manage',
    'local/ftm_sip:coach',
];

echo "=== Capabilities ===\n";
foreach ($caps as $cap) {
    $has = has_capability($cap, $context);
    echo "  {$cap}: " . ($has ? 'YES' : 'NO') . "\n";
}

echo "\nis_siteadmin: " . (is_siteadmin() ? 'YES' : 'NO') . "\n";

// Check the exact condition from dashboard_helper.
$is_admin = is_siteadmin($USER->id);
$is_manager = has_capability('local/coachmanager:managejobs', $context, $USER->id);
$see_all = $is_admin || $is_manager;

echo "\n=== Dashboard see_all check ===\n";
echo "is_admin: " . ($is_admin ? 'YES' : 'NO') . "\n";
echo "is_manager (managejobs): " . ($is_manager ? 'YES' : 'NO') . "\n";
echo "see_all: " . ($see_all ? 'YES' : 'NO') . "\n";

// Count students.
echo "\n=== Students query ===\n";
if ($see_all) {
    $count_coaching = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT u.id) FROM {user} u WHERE u.deleted = 0 AND EXISTS (SELECT 1 FROM {local_student_coaching} sc WHERE sc.userid = u.id AND sc.status = 'active')"
    );
    $count_cpurc = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT u.id) FROM {user} u WHERE u.deleted = 0 AND EXISTS (SELECT 1 FROM {local_ftm_cpurc_students} cs WHERE cs.userid = u.id AND (cs.status IS NULL OR cs.status != 'cancelled'))"
    );
    echo "see_all=YES: coaching students={$count_coaching}, cpurc students={$count_cpurc}\n";
} else {
    $count = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {local_student_coaching} WHERE coachid = ? AND status = 'active'",
        [$USER->id]
    );
    echo "see_all=NO: my students={$count} (coachid={$USER->id})\n";
}

// Check local_ftm_coaches.
echo "\n=== local_ftm_coaches ===\n";
$coach = $DB->get_record('local_ftm_coaches', ['userid' => $USER->id]);
if ($coach) {
    echo "Trovato: id={$coach->id}, userid={$coach->userid}\n";
} else {
    echo "NON trovato per userid={$USER->id}\n";
}

echo '</pre>';
