<?php
/**
 * FTM Job Search - Library functions.
 *
 * @package    local_ftm_jobsearch
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add navigation node to Moodle navigation.
 */
function local_ftm_jobsearch_extend_navigation(global_navigation $nav) {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $context = context_system::instance();
    if (has_capability('local/ftm_jobsearch:use', $context)
        || has_capability('local/ftm_jobsearch:manage', $context)
        || is_siteadmin()) {
        $nav->add(
            get_string('pluginname', 'local_ftm_jobsearch'),
            new moodle_url('/local/ftm_jobsearch/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'ftm_jobsearch',
            new pix_icon('i/search', '')
        );
    }
}
