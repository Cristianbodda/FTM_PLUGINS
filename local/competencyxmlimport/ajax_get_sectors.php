<?php
/**
 * AJAX - Ottieni settori di un framework
 *
 * @package    local_competencyxmlimport
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$frameworkid = required_param('frameworkid', PARAM_INT);

// Verify user has capability to use this plugin
$context = context_system::instance();
require_capability('local/competencyxmlimport:import', $context);

$competencies = $DB->get_records('competency', ['competencyframeworkid' => $frameworkid], '', 'id, idnumber');

$sectors = [];
foreach ($competencies as $c) {
    if (preg_match('/^([A-Z]+)_/', $c->idnumber, $m)) {
        $prefix = $m[1];
        if (!isset($sectors[$prefix])) {
            $sectors[$prefix] = ['name' => $prefix, 'count' => 0];
        }
        $sectors[$prefix]['count']++;
    }
}

header('Content-Type: application/json');
echo json_encode($sectors);
