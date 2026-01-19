<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_competencyxmlimport';
$plugin->version = 2026011601;
$plugin->requires = 2022041900;
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v1.2';
$plugin->dependencies = [
    'qbank_competenciesbyquestion' => ANY_VERSION,
];
