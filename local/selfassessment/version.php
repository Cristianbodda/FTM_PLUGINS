<?php
// ============================================
// Self Assessment - Autovalutazione Competenze
// ============================================
// Plugin per autovalutazione studenti basata
// sulla Tassonomia di Bloom (livelli 1-6)
// ============================================

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_selfassessment';
$plugin->version = 2026012903;           // YYYYMMDDXX - nuovo sistema hook Moodle 4.3+
$plugin->requires = 2023100900;          // Moodle 4.3+ (richiesto per nuovo hook system)
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.3.1';
