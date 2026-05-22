<?php
/**
 * Scheduled tasks for local_ftm_jobsearch.
 *
 * @package    local_ftm_jobsearch
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname'  => '\local_ftm_jobsearch\task\scrape_jobs',
        'blocking'   => 0,
        'minute'     => '30',
        'hour'       => '2',
        'day'        => '*',
        'month'      => '*',
        'dayofweek'  => '*',
        'disabled'   => 0,
    ],
];
