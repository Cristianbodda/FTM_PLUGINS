<?php
/**
 * Version info - Competency Manager
 * 
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_competencymanager';
$plugin->version = 2026020901;  // AGGIORNATO: Valutazione Formatore - 3 nuove tabelle
$plugin->requires = 2024042200; // Moodle 4.4+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v2.4.0';    // Valutazione Formatore: sistema valutazione coach scala Bloom
$plugin->dependencies = [
    'qbank_competenciesbyquestion' => ANY_VERSION
];
