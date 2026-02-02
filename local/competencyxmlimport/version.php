<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_competencyxmlimport';
$plugin->version = 2026020201;  // Quiz Export: descrizione competenza invece di nome
$plugin->requires = 2022041900;
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v1.3';
$plugin->dependencies = [
    'qbank_competenciesbyquestion' => ANY_VERSION,
];
