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
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_jobmatchagent', get_string('pluginname', 'local_jobmatchagent'));

    // Score threshold below which deterministic matches are discarded (no AI call).
    $settings->add(new admin_setting_configtext(
        'local_jobmatchagent/score_threshold',
        get_string('score_threshold', 'local_jobmatchagent'),
        get_string('score_threshold_desc', 'local_jobmatchagent'),
        '10',
        PARAM_INT
    ));

    // AI veto threshold: below this AI %, the AI score caps the global (CV mismatch).
    $settings->add(new admin_setting_configtext(
        'local_jobmatchagent/ai_veto_threshold',
        'Soglia veto AI (CV non compatibile)',
        'Se l\'AI valuta il match CV-offerta sotto questa soglia, lo score globale viene plafonato al valore AI (override su settore FTM, distanza, ecc.). Default 20%.',
        '20',
        PARAM_INT
    ));

    // Visible threshold: in the wizard, only show matches with AI score >= this.
    $settings->add(new admin_setting_configtext(
        'local_jobmatchagent/visible_threshold',
        'Soglia visibilita (wizard)',
        'Nel wizard mostriamo solo opportunita con score AI sopra questa soglia. Le altre vanno in "bassa compatibilita" (collassabile). Default 50%.',
        '50',
        PARAM_INT
    ));

    // OpenAI API key (own — falls back to jobaida).
    $settings->add(new admin_setting_configpasswordunmask(
        'local_jobmatchagent/openai_apikey',
        'OpenAI API key (opzionale)',
        'Se vuoto, usa quella di JobAIDA (fallback automatico).',
        ''
    ));

    // OpenAI model used for matching (reuses local_jobaida API key).
    $settings->add(new admin_setting_configselect(
        'local_jobmatchagent/openai_model',
        get_string('openai_model', 'local_jobmatchagent'),
        get_string('openai_model_desc', 'local_jobmatchagent'),
        'gpt-4o-mini',
        [
            'gpt-4o-mini' => 'GPT-4o Mini (economico, raccomandato)',
            'gpt-4o' => 'GPT-4o (qualita superiore, costoso)',
        ]
    ));

    // Monthly budget cap in EUR (soft limit — logged, not enforced).
    $settings->add(new admin_setting_configtext(
        'local_jobmatchagent/budget_eur_month',
        get_string('budget_eur_month', 'local_jobmatchagent'),
        get_string('budget_eur_month_desc', 'local_jobmatchagent'),
        '10',
        PARAM_INT
    ));

    // Default scoring weights (must sum to 100).
    $settings->add(new admin_setting_heading(
        'local_jobmatchagent/weights_heading',
        get_string('weights_heading', 'local_jobmatchagent'),
        get_string('weights_heading_desc', 'local_jobmatchagent')
    ));

    foreach ([
        'weight_sector' => 35,
        'weight_experience' => 25,
        'weight_distance' => 15,
        'weight_schedule' => 15,
        'weight_size' => 10,
    ] as $key => $default) {
        $settings->add(new admin_setting_configtext(
            'local_jobmatchagent/' . $key,
            get_string($key, 'local_jobmatchagent'),
            '',
            (string) $default,
            PARAM_INT
        ));
    }

    $ADMIN->add('localplugins', $settings);
}
