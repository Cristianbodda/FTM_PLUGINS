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
 * Plugin version and other meta-data.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_ftm_scheduler';
$plugin->version = 2026012202;  // YYYYMMDDXX - Added attendance page, notification helper, secretary dashboard
$plugin->requires = 2023100900; // Moodle 4.3+
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = 'v1.0.0';

// Dipendenze da altri plugin FTM
$plugin->dependencies = [
    'local_competencymanager' => ANY_VERSION,
];
