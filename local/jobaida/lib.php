<?php
/**
 * Library functions for local_jobaida.
 *
 * @package    local_jobaida
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend navigation to add JobAIDA link.
 *
 * @param global_navigation $navigation The navigation object.
 */
function local_jobaida_extend_navigation(global_navigation $navigation) {
    global $USER, $DB;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $context = context_system::instance();

    // Show link if user has use/authorize capability, is authorized, or is siteadmin.
    $canuse = has_capability('local/jobaida:use', $context);
    $canauthorize = has_capability('local/jobaida:authorize', $context);
    $isauthorized = false;

    $dbman = $DB->get_manager();
    if ($dbman->table_exists('local_jobaida_auth')) {
        $isauthorized = $DB->record_exists('local_jobaida_auth', [
            'userid' => $USER->id,
            'active' => 1,
        ]);
    }

    if ($canuse || $canauthorize || $isauthorized || is_siteadmin()) {
        $navigation->add(
            get_string('pluginname', 'local_jobaida'),
            new moodle_url('/local/jobaida/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'jobaida',
            new pix_icon('i/edit', '')
        );
    }
}
