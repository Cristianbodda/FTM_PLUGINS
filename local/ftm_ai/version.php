<?php
/**
 * FTM AI Integration - Azure OpenAI / Copilot
 *
 * Plugin per integrare AI generativa in Moodle
 * con mascheramento automatico dei dati sensibili.
 *
 * @package    local_ftm_ai
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3+
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026012800;
$plugin->requires  = 2022041900; // Moodle 4.0+
$plugin->component = 'local_ftm_ai';
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '1.0.0-alpha';

$plugin->dependencies = [
    'local_competencymanager' => ANY_VERSION,
];
