<?php
/**
 * Version info - FTM Test Suite
 * 
 * Plugin per test completi pre-produzione del sistema FTM
 * 
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_ftm_testsuite';
$plugin->version = 2026010201;
$plugin->requires = 2024042200; // Moodle 4.4+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v1.0.0';
$plugin->dependencies = [
    'local_competencymanager' => ANY_VERSION,
    'local_selfassessment' => ANY_VERSION,
    'local_labeval' => ANY_VERSION,
    'local_coachmanager' => ANY_VERSION,
    'qbank_competenciesbyquestion' => ANY_VERSION
];
