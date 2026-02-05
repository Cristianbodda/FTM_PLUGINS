<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_competencyxmlimport';
$plugin->version = 2026020304;  // Excel Multi-Quiz Import: added debug logging for competency validation
$plugin->requires = 2022041900;
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v1.4';
$plugin->dependencies = [
    'qbank_competenciesbyquestion' => ANY_VERSION,
];
