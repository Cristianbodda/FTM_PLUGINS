<?php
/**
 * Debug: Check why "I miei studenti" is empty
 *
 * @package    local_coachmanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/coachmanager:view', $context);

header('Content-Type: text/html; charset=utf-8');

echo "<html><body style='font-family: Arial, sans-serif; padding: 30px; max-width: 900px; margin: 0 auto;'>";
echo "<h2>Debug: I Miei Studenti</h2>";

// 1. Current user info
echo "<h3>1. Utente corrente</h3>";
echo "<p><strong>User ID:</strong> {$USER->id}</p>";
echo "<p><strong>Nome:</strong> " . fullname($USER) . "</p>";
echo "<p><strong>Username:</strong> {$USER->username}</p>";
echo "<p><strong>Is siteadmin:</strong> " . (is_siteadmin() ? 'SI' : 'NO') . "</p>";

// 2. Check local_student_coaching table
echo "<h3>2. Tabella local_student_coaching</h3>";

$table_exists = $DB->get_manager()->table_exists('local_student_coaching');
echo "<p><strong>Tabella esiste:</strong> " . ($table_exists ? 'SI' : 'NO') . "</p>";

if ($table_exists) {
    // Total records
    $total = $DB->count_records('local_student_coaching');
    echo "<p><strong>Record totali:</strong> {$total}</p>";

    // Records for this coach
    $my_records = $DB->get_records('local_student_coaching', ['coachid' => $USER->id]);
    echo "<p><strong>Record per coach ID {$USER->id}:</strong> " . count($my_records) . "</p>";

    // Active records for this coach
    $my_active = $DB->get_records('local_student_coaching', ['coachid' => $USER->id, 'status' => 'active']);
    echo "<p><strong>Record ATTIVI per coach ID {$USER->id}:</strong> " . count($my_active) . "</p>";

    // Show all records for this coach
    if (!empty($my_records)) {
        echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse: collapse;'>";
        echo "<tr style='background:#f0f0f0;'><th>ID</th><th>userid</th><th>coachid</th><th>courseid</th><th>status</th><th>Student Name</th></tr>";
        foreach ($my_records as $rec) {
            $student = $DB->get_record('user', ['id' => $rec->userid], 'id, firstname, lastname');
            $name = $student ? fullname($student) : 'USER NOT FOUND';
            $color = ($rec->status === 'active') ? '#e8f5e9' : '#ffebee';
            echo "<tr style='background:{$color};'>";
            echo "<td>{$rec->id}</td><td>{$rec->userid}</td><td>{$rec->coachid}</td><td>{$rec->courseid}</td><td>{$rec->status}</td><td>{$name}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Show ALL coaches with counts
    echo "<h3>3. Tutti i Coach nella tabella</h3>";
    $coaches = $DB->get_records_sql("
        SELECT sc.coachid, u.firstname, u.lastname, u.username,
               COUNT(*) as total,
               SUM(CASE WHEN sc.status = 'active' THEN 1 ELSE 0 END) as active_count
        FROM {local_student_coaching} sc
        JOIN {user} u ON u.id = sc.coachid
        GROUP BY sc.coachid, u.firstname, u.lastname, u.username
        ORDER BY u.lastname
    ");

    if (!empty($coaches)) {
        echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse: collapse;'>";
        echo "<tr style='background:#f0f0f0;'><th>Coach ID</th><th>Nome</th><th>Username</th><th>Totali</th><th>Attivi</th><th>Sei tu?</th></tr>";
        foreach ($coaches as $coach) {
            $is_me = ($coach->coachid == $USER->id) ? '<strong style="color:green;">SI</strong>' : 'no';
            echo "<tr>";
            echo "<td>{$coach->coachid}</td><td>" . s($coach->firstname . ' ' . $coach->lastname) . "</td><td>" . s($coach->username) . "</td><td>{$coach->total}</td><td>{$coach->active_count}</td><td>{$is_me}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'><strong>NESSUN coach trovato nella tabella!</strong></p>";
    }

    // Show first 10 records regardless
    echo "<h3>4. Primi 10 record della tabella</h3>";
    $first10 = $DB->get_records_sql("SELECT * FROM {local_student_coaching} ORDER BY id LIMIT 10");
    if (!empty($first10)) {
        echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse: collapse;'>";
        echo "<tr style='background:#f0f0f0;'><th>ID</th><th>userid</th><th>coachid</th><th>courseid</th><th>status</th></tr>";
        foreach ($first10 as $rec) {
            echo "<tr><td>{$rec->id}</td><td>{$rec->userid}</td><td>{$rec->coachid}</td><td>{$rec->courseid}</td><td>{$rec->status}</td></tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p style='color:red;'><strong>La tabella local_student_coaching NON ESISTE!</strong></p>";
}

// 5. Check dashboard_helper class
echo "<h3>5. Test dashboard_helper</h3>";
try {
    require_once('classes/dashboard_helper.php');
    $dashboard = new \local_coachmanager\dashboard_helper($USER->id);
    $students = $dashboard->get_my_students();
    echo "<p><strong>get_my_students() restituisce:</strong> " . count($students) . " studenti</p>";

    if (!empty($students)) {
        echo "<ul>";
        foreach ($students as $s) {
            echo "<li>" . fullname($s) . " (ID: {$s->id})</li>";
        }
        echo "</ul>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red;'><strong>ERRORE:</strong> " . s($e->getMessage()) . "</p>";
    echo "<pre>" . s($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";
echo "<p style='color:red; font-weight:bold;'>CANCELLA debug_students.php DAL SERVER DOPO L'USO!</p>";
echo "</body></html>";
