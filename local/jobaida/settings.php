<?php
/**
 * Plugin settings.
 *
 * @package    local_jobaida
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_jobaida', get_string('pluginname', 'local_jobaida'));

    // OpenAI API Key.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_jobaida/openai_apikey',
        get_string('openai_apikey', 'local_jobaida'),
        get_string('openai_apikey_desc', 'local_jobaida'),
        ''
    ));

    // OpenAI Model.
    $settings->add(new admin_setting_configselect(
        'local_jobaida/openai_model',
        get_string('openai_model', 'local_jobaida'),
        get_string('openai_model_desc', 'local_jobaida'),
        'gpt-4o',
        [
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-4-turbo' => 'GPT-4 Turbo',
        ]
    ));

    // Language for generated letters.
    $settings->add(new admin_setting_configselect(
        'local_jobaida/letter_language',
        get_string('letter_language', 'local_jobaida'),
        get_string('letter_language_desc', 'local_jobaida'),
        'it',
        [
            'it' => 'Italiano',
            'de' => 'Deutsch',
            'fr' => 'Francais',
            'en' => 'English',
        ]
    ));

    // Max tokens.
    $settings->add(new admin_setting_configtext(
        'local_jobaida/max_tokens',
        get_string('max_tokens', 'local_jobaida'),
        get_string('max_tokens_desc', 'local_jobaida'),
        '2000',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
