<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * FTM Suite installation status dashboard
 *
 * Displays the installation status of all 9 FTM plugins.
 *
 * @package    local_ftm_suite
 * @copyright  2026 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Require login and admin capability.
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

// Page setup.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_suite/index.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pagetitle', 'local_ftm_suite'));
$PAGE->set_heading(get_string('pageheading', 'local_ftm_suite'));

// Get plugin statuses.
$statuses = \local_ftm_suite\suite_manager::get_all_plugin_statuses();
$summary = \local_ftm_suite\suite_manager::get_summary();

// Output page.
echo $OUTPUT->header();

// Description.
echo html_writer::tag('p', get_string('description', 'local_ftm_suite'), ['class' => 'lead']);

// Summary cards.
echo html_writer::start_div('row mb-4');

// Total plugins card.
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('total_plugins', 'local_ftm_suite'), ['class' => 'card-title']);
echo html_writer::tag('p', $summary['total'], ['class' => 'card-text display-4']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Installed card.
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center bg-success text-white');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('installed_plugins', 'local_ftm_suite'), ['class' => 'card-title']);
echo html_writer::tag('p', $summary['installed'], ['class' => 'card-text display-4']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Missing card.
echo html_writer::start_div('col-md-3');
$missingclass = $summary['missing'] > 0 ? 'bg-danger text-white' : 'bg-light';
echo html_writer::start_div('card text-center ' . $missingclass);
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('missing_plugins', 'local_ftm_suite'), ['class' => 'card-title']);
echo html_writer::tag('p', $summary['missing'], ['class' => 'card-text display-4']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Outdated card.
echo html_writer::start_div('col-md-3');
$outdatedclass = $summary['outdated'] > 0 ? 'bg-warning text-dark' : 'bg-light';
echo html_writer::start_div('card text-center ' . $outdatedclass);
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('outdated_plugins', 'local_ftm_suite'), ['class' => 'card-title']);
echo html_writer::tag('p', $summary['outdated'], ['class' => 'card-text display-4']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // End row.

// Status message.
if (\local_ftm_suite\suite_manager::is_suite_complete()) {
    echo $OUTPUT->notification(get_string('all_installed', 'local_ftm_suite'), 'success');
} else {
    echo $OUTPUT->notification(get_string('some_missing', 'local_ftm_suite'), 'warning');
    echo html_writer::tag('p', get_string('install_instructions', 'local_ftm_suite'), ['class' => 'alert alert-info']);
}

// Plugin status table.
$table = new html_table();
$table->head = [
    get_string('plugin', 'local_ftm_suite'),
    get_string('component', 'local_ftm_suite'),
    get_string('requiredversion', 'local_ftm_suite'),
    get_string('installedversion', 'local_ftm_suite'),
    get_string('status', 'local_ftm_suite'),
];
$table->attributes['class'] = 'table table-striped table-hover generaltable';

foreach ($statuses as $component => $info) {
    $pluginname = get_string($info['description'], 'local_ftm_suite');
    $statuslabel = get_string('status_' . $info['status'], 'local_ftm_suite');
    $statusbadge = html_writer::tag('span', $statuslabel, [
        'class' => \local_ftm_suite\suite_manager::get_status_class($info['status']),
    ]);

    $row = new html_table_row([
        $pluginname,
        html_writer::tag('code', $component),
        \local_ftm_suite\suite_manager::format_version($info['required_version']),
        \local_ftm_suite\suite_manager::format_version($info['installed_version']),
        $statusbadge,
    ]);

    // Highlight missing/outdated rows.
    if ($info['status'] === \local_ftm_suite\suite_manager::STATUS_MISSING) {
        $row->attributes['class'] = 'table-danger';
    } else if ($info['status'] === \local_ftm_suite\suite_manager::STATUS_OUTDATED) {
        $row->attributes['class'] = 'table-warning';
    }

    $table->data[] = $row;
}

echo html_writer::table($table);

// Links to related pages.
echo html_writer::start_div('mt-4');
echo html_writer::tag('h4', 'Quick Links', ['class' => 'mb-3']);
echo html_writer::start_tag('ul', ['class' => 'list-group']);

// FTM Hub link.
echo html_writer::start_tag('li', ['class' => 'list-group-item']);
echo html_writer::link(
    new moodle_url('/local/ftm_hub/index.php'),
    'FTM Hub',
    ['class' => 'btn btn-outline-primary btn-sm me-2']
);
echo ' - Central navigation hub for FTM tools';
echo html_writer::end_tag('li');

// System Check link.
echo html_writer::start_tag('li', ['class' => 'list-group-item']);
echo html_writer::link(
    new moodle_url('/local/competencymanager/system_check.php'),
    'System Check',
    ['class' => 'btn btn-outline-primary btn-sm me-2']
);
echo ' - Diagnostic tool for FTM system';
echo html_writer::end_tag('li');

// Plugin management link.
echo html_writer::start_tag('li', ['class' => 'list-group-item']);
echo html_writer::link(
    new moodle_url('/admin/plugins.php'),
    'Plugin Management',
    ['class' => 'btn btn-outline-secondary btn-sm me-2']
);
echo ' - Moodle plugin overview';
echo html_writer::end_tag('li');

echo html_writer::end_tag('ul');
echo html_writer::end_div();

echo $OUTPUT->footer();
