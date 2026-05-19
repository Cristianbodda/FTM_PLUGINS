<?php
/**
 * Plugin settings for Competency Manager.
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_competencymanager', get_string('pluginname', 'local_competencymanager'));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_competencymanager/openai_apikey',
        get_string('openai_apikey', 'local_competencymanager'),
        get_string('openai_apikey_desc', 'local_competencymanager'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_competencymanager/passport_threshold',
        get_string('passport_threshold', 'local_competencymanager'),
        get_string('passport_threshold_desc', 'local_competencymanager'),
        '60',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_competencymanager/passport_style_examples',
        get_string('passport_style_examples', 'local_competencymanager'),
        get_string('passport_style_examples_desc', 'local_competencymanager'),
        '',
        PARAM_RAW
    ));

    $ADMIN->add('localplugins', $settings);
}
