<?php
/**
 * Version info - Competency Manager
 * 
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_competencymanager';
$plugin->version = 2026021201;  // AGGIORNATO: Fix mapping settori numerici + selezione valutazione
$plugin->requires = 2024042200; // Moodle 4.4+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v2.6.4';    // Fix: mapping competenze numeriche (01-07) + selezione valutazione con ratings
$plugin->dependencies = [
    'qbank_competenciesbyquestion' => ANY_VERSION
];
