<?php
/**
 * Version info - Competency Manager
 * 
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_competencymanager';
$plugin->version = 2026052701;  // Sblocco settore aggiuntivo via PIN coach (unlocked_by in local_student_sectors)
$plugin->requires = 2024042200; // Moodle 4.4+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v2.9.7';    // Passaporto Tecnico v5 + sblocco settore PIN coach
$plugin->dependencies = [
    'qbank_competenciesbyquestion' => ANY_VERSION
];
