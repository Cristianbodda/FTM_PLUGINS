<?php
define("AJAX_SCRIPT", true);
require_once(__DIR__ . "/../../config.php");
$studentid = required_param("studentid", PARAM_INT);
$courseid = required_param("courseid", PARAM_INT);
$field = required_param("field", PARAM_ALPHA);
$value = required_param("value", PARAM_RAW);
require_login();
require_sesskey();
$context = context_course::instance($courseid);
require_capability("local/competencymanager:managecoaching", $context);
header("Content-Type: application/json");
$allowed = ["sector", "area", "date_start", "date_end", "date_extended", "current_week", "status", "notes", "coachid"];
if (!in_array($field, $allowed)) { echo json_encode(["success" => false, "error" => "Campo non consentito"]); exit; }
try {
$existing = $DB->get_record("local_student_coaching", ["userid" => $studentid, "courseid" => $courseid]);
if (in_array($field, ["date_start", "date_end", "date_extended"])) { $value = $value ? strtotime($value) : null; }
$now = time();
if ($existing) { $existing->$field = $value; $existing->timemodified = $now; $DB->update_record("local_student_coaching", $existing); }
else { $r = new stdClass(); $r->userid = $studentid; $r->courseid = $courseid; $r->coachid = $GLOBALS["USER"]->id; $r->$field = $value; $r->current_week = 1; $r->status = "active"; $r->timecreated = $now; $r->timemodified = $now; $DB->insert_record("local_student_coaching", $r); }
echo json_encode(["success" => true]);
} catch (Exception $e) { echo json_encode(["success" => false, "error" => $e->getMessage()]); }
require_login();
