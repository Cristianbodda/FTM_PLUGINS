<?php
// ============================================
// Self Assessment - Autovalutazione Competenze
// ============================================
// Plugin per autovalutazione studenti basata
// sulla Tassonomia di Bloom (livelli 1-6)
// ============================================

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_selfassessment';
$plugin->version = 2026011404;           // YYYYMMDDXX - skip temporaneo/permanente + db fields
$plugin->requires = 2022041900;          // Moodle 4.0+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.2.0';
