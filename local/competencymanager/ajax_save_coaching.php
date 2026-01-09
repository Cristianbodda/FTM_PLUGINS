<?php
/**
 * AJAX endpoint for saving coaching data
 * @package    local_competencymanager
 */
define("AJAX_SCRIPT", true);
require_once(__DIR__ . "/../../config.php");

require_login();
require_sesskey();

$studentid = required_param("studentid", PARAM_INT);
$courseid = required_param("courseid", PARAM_INT);
$field = required_param("field", PARAM_ALPHA);

$context = context_course::instance($courseid);
require_capability("local/competencymanager:managecoaching", $context);

header("Content-Type: application/json");

// Define allowed fields with their parameter types
$allowed_fields = [
    "sector" => PARAM_ALPHANUMEXT,
    "area" => PARAM_TEXT,
    "date_start" => PARAM_TEXT,
    "date_end" => PARAM_TEXT,
    "date_extended" => PARAM_TEXT,
    "current_week" => PARAM_INT,
    "status" => PARAM_ALPHANUMEXT,
    "notes" => PARAM_TEXT,
    "coachid" => PARAM_INT
];

if (!array_key_exists($field, $allowed_fields)) {
    echo json_encode(["success" => false, "error" => "Campo non consentito"]);
    exit;
}

// Get value with proper parameter type for this field
$value = required_param("value", $allowed_fields[$field]);

// Sanitize text fields
if (in_array($allowed_fields[$field], [PARAM_TEXT])) {
    $value = clean_param($value, PARAM_TEXT);
}

try {
    $existing = $DB->get_record("local_student_coaching", ["userid" => $studentid, "courseid" => $courseid]);

    // Convert date strings to timestamps
    if (in_array($field, ["date_start", "date_end", "date_extended"])) {
        $value = $value ? strtotime($value) : null;
    }

    $now = time();

    if ($existing) {
        $existing->$field = $value;
        $existing->timemodified = $now;
        $DB->update_record("local_student_coaching", $existing);
    } else {
        $r = new stdClass();
        $r->userid = $studentid;
        $r->courseid = $courseid;
        $r->coachid = $USER->id;
        $r->$field = $value;
        $r->current_week = 1;
        $r->status = "active";
        $r->timecreated = $now;
        $r->timemodified = $now;
        $DB->insert_record("local_student_coaching", $r);
    }

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
