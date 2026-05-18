<?php
/**
 * FTM Job Search - Plugin settings.
 *
 * @package    local_ftm_jobsearch
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ftm_jobsearch', get_string('pluginname', 'local_ftm_jobsearch'));

    // API key: reusa quella di JobAIDA se presente, altrimenti campo proprio.
    $settings->add(new admin_setting_configtext(
        'local_ftm_jobsearch/openai_apikey',
        get_string('setting_apikey', 'local_ftm_jobsearch'),
        get_string('setting_apikey_desc', 'local_ftm_jobsearch'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'local_ftm_jobsearch/openai_model',
        get_string('setting_model', 'local_ftm_jobsearch'),
        get_string('setting_model_desc', 'local_ftm_jobsearch'),
        'gpt-4o-mini',
        ['gpt-4o-mini' => 'GPT-4o Mini (economico)', 'gpt-4o' => 'GPT-4o (migliore)']
    ));

    $settings->add(new admin_setting_configtext(
        'local_ftm_jobsearch/cache_hours',
        get_string('setting_cache', 'local_ftm_jobsearch'),
        get_string('setting_cache_desc', 'local_ftm_jobsearch'),
        '24'
    ));

    $settings->add(new admin_setting_configtext(
        'local_ftm_jobsearch/max_offer_age_days',
        'Età massima offerte (giorni)',
        'Escludi offerte con data di pubblicazione precedente a questo numero di giorni. Default: 90.',
        '90',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
