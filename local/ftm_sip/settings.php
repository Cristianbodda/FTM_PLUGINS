<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Plugin settings.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ftm_sip', 'Coaching Individualizzato (CI/SIP)');

    $settings->add(new admin_setting_configtext(
        'local_ftm_sip/secretariat_email',
        'Email segreteria',
        'Indirizzo email che riceve i report PDF inviati col bottone "Informa segreteria" dalla pagina studente CI.',
        'lucio.pagani@f3m.ch',
        PARAM_EMAIL
    ));

    // Dati istituzionali da includere nel PDF inviato alla segreteria.
    $settings->add(new admin_setting_heading(
        'local_ftm_sip/inst_heading',
        'Dati istituzionali (PDF segreteria)',
        'Questi dati appaiono nella testata del PDF inviato alla segreteria.'
    ));

    $settings->add(new admin_setting_configtext(
        'local_ftm_sip/inst_program_name',
        'Nome programma',
        'Testo che appare nella tabella istituzionale del PDF (es: "FTM sostegno tramite caching individuale")',
        'FTM sostegno tramite caching individuale',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_ftm_sip/inst_n_ue',
        'N. UE',
        'Numero UE FTM (default: 1234816)',
        '1234816',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_ftm_sip/inst_giorni_max',
        'Giorni di frequenza massimi',
        'Default: 35',
        '35',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_ftm_sip/inst_orari_note',
        'Nota frequenza e orari',
        'Testo standard da mostrare sotto i giorni',
        'Frequenza e orari da stabilire con organizzatore',
        PARAM_TEXT
    ));

    $ADMIN->add('localplugins', $settings);
}
