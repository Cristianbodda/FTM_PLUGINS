<?php
/**
 * Settings for local_selfassessment.
 *
 * @package    local_selfassessment
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_selfassessment', get_string('pluginname', 'local_selfassessment'));

    // Toggle popup autovalutazione (default OFF per sicurezza durante setup produzione).
    $settings->add(new admin_setting_configcheckbox(
        'local_selfassessment/popup_enabled',
        get_string('setting_popup_enabled', 'local_selfassessment'),
        get_string('setting_popup_enabled_desc', 'local_selfassessment'),
        0
    ));

    $ADMIN->add('localplugins', $settings);
}
