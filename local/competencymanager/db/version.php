<?php
/**
 * Version info - Competency Manager
 * 
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_competencymanager';
$plugin->version = 2025122802;  // AGGIORNATO: Era 2025122701, ora 2025122802
$plugin->requires = 2024042200; // Moodle 4.4+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v2.1.0';    // AGGIORNATO: Era v2.0.0, ora v2.1.0 (aggiunto coaching)
$plugin->dependencies = [
    'qbank_competenciesbyquestion' => ANY_VERSION
];
