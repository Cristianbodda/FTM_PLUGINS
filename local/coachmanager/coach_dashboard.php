<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Vecchia Dashboard Coach (v1) — DEPRECATA.
 * Tutte le richieste vengono reindirizzate alla Dashboard V2.
 *
 * @package    local_coachmanager
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

// Mantieni eventuali parametri nel redirect (es. courseid, view, etc.).
$params = [];
$courseid = optional_param('courseid', 0, PARAM_INT);
if ($courseid) {
    $params['courseid'] = $courseid;
}
$view = optional_param('view', '', PARAM_ALPHANUMEXT);
if ($view) {
    $params['view'] = $view;
}

$redirecturl = new moodle_url('/local/coachmanager/coach_dashboard_v2.php', $params);
redirect($redirecturl);
