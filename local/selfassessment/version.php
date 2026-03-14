<?php
// ============================================
// Self Assessment - Autovalutazione Competenze
// ============================================
// Plugin per autovalutazione studenti basata
// sulla Tassonomia di Bloom (livelli 1-6)
// ============================================

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_selfassessment';
$plugin->version = 2026030300;           // YYYYMMDDXX - quiz single attempt + unlock system
$plugin->requires = 2023100900;          // Moodle 4.3+ (richiesto per nuovo hook system)
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.6.0';
