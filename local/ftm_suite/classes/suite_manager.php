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
 * Suite Manager class for checking FTM plugin installation states
 *
 * @package    local_ftm_suite
 * @copyright  2026 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_suite;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages FTM Suite plugin status checks
 */
class suite_manager {

    /** Plugin status: installed and up to date */
    const STATUS_INSTALLED = 'installed';

    /** Plugin status: not installed */
    const STATUS_MISSING = 'missing';

    /** Plugin status: installed but version is too old */
    const STATUS_OUTDATED = 'outdated';

    /**
     * @var array List of all FTM plugins with their required versions
     */
    private static $ftm_plugins = [
        'qbank_competenciesbyquestion' => [
            'version' => 2026010901,
            'tier' => 1,
            'description' => 'plugin_qbank_competenciesbyquestion',
        ],
        'local_competencymanager' => [
            'version' => 2026010901,
            'tier' => 2,
            'description' => 'plugin_local_competencymanager',
        ],
        'local_coachmanager' => [
            'version' => 2025122303,
            'tier' => 3,
            'description' => 'plugin_local_coachmanager',
        ],
        'local_labeval' => [
            'version' => 2024123001,
            'tier' => 4,
            'description' => 'plugin_local_labeval',
        ],
        'local_selfassessment' => [
            'version' => 2025122402,
            'tier' => 5,
            'description' => 'plugin_local_selfassessment',
        ],
        'local_competencyreport' => [
            'version' => 2025120501,
            'tier' => 5,
            'description' => 'plugin_local_competencyreport',
        ],
        'local_competencyxmlimport' => [
            'version' => 2026010901,
            'tier' => 5,
            'description' => 'plugin_local_competencyxmlimport',
        ],
        'local_ftm_hub' => [
            'version' => 2026010902,
            'tier' => 6,
            'description' => 'plugin_local_ftm_hub',
        ],
        'block_ftm_tools' => [
            'version' => 2026010902,
            'tier' => 6,
            'description' => 'plugin_block_ftm_tools',
        ],
    ];

    /**
     * Get the list of all FTM plugins
     *
     * @return array Array of plugin components with their metadata
     */
    public static function get_ftm_plugins(): array {
        return self::$ftm_plugins;
    }

    /**
     * Check the installation status of a single plugin
     *
     * @param string $component Plugin component name (e.g., 'local_competencymanager')
     * @param int $required_version Minimum required version
     * @return array Status information with keys: status, installed_version, required_version
     */
    public static function check_plugin_status(string $component, int $required_version): array {
        $pluginman = \core_plugin_manager::instance();
        $plugininfo = $pluginman->get_plugin_info($component);

        if ($plugininfo === null) {
            return [
                'status' => self::STATUS_MISSING,
                'installed_version' => null,
                'required_version' => $required_version,
            ];
        }

        $installed_version = $plugininfo->versiondb ?? $plugininfo->versiondisk ?? 0;

        if ($installed_version < $required_version) {
            return [
                'status' => self::STATUS_OUTDATED,
                'installed_version' => $installed_version,
                'required_version' => $required_version,
            ];
        }

        return [
            'status' => self::STATUS_INSTALLED,
            'installed_version' => $installed_version,
            'required_version' => $required_version,
        ];
    }

    /**
     * Get the installation status of all FTM plugins
     *
     * @return array Array of plugin statuses indexed by component name
     */
    public static function get_all_plugin_statuses(): array {
        $statuses = [];

        foreach (self::$ftm_plugins as $component => $info) {
            $status = self::check_plugin_status($component, $info['version']);
            $status['tier'] = $info['tier'];
            $status['description'] = $info['description'];
            $statuses[$component] = $status;
        }

        return $statuses;
    }

    /**
     * Get a summary of installation status
     *
     * @return array Summary with keys: total, installed, missing, outdated
     */
    public static function get_summary(): array {
        $statuses = self::get_all_plugin_statuses();

        $summary = [
            'total' => count($statuses),
            'installed' => 0,
            'missing' => 0,
            'outdated' => 0,
        ];

        foreach ($statuses as $status) {
            switch ($status['status']) {
                case self::STATUS_INSTALLED:
                    $summary['installed']++;
                    break;
                case self::STATUS_MISSING:
                    $summary['missing']++;
                    break;
                case self::STATUS_OUTDATED:
                    $summary['outdated']++;
                    break;
            }
        }

        return $summary;
    }

    /**
     * Check if all plugins are installed and up to date
     *
     * @return bool True if all plugins are installed and meet version requirements
     */
    public static function is_suite_complete(): bool {
        $summary = self::get_summary();
        return ($summary['missing'] === 0 && $summary['outdated'] === 0);
    }

    /**
     * Format a version number for display
     *
     * @param int|null $version Version number in YYYYMMDDXX format
     * @return string Formatted version string
     */
    public static function format_version(?int $version): string {
        if ($version === null) {
            return '-';
        }

        // Convert YYYYMMDDXX to YYYY-MM-DD (XX)
        $str = (string)$version;
        if (strlen($str) === 10) {
            $year = substr($str, 0, 4);
            $month = substr($str, 4, 2);
            $day = substr($str, 6, 2);
            $build = substr($str, 8, 2);
            return "{$year}-{$month}-{$day} ({$build})";
        }

        return (string)$version;
    }

    /**
     * Get CSS class for a status
     *
     * @param string $status Status constant
     * @return string CSS class name
     */
    public static function get_status_class(string $status): string {
        switch ($status) {
            case self::STATUS_INSTALLED:
                return 'badge badge-success bg-success';
            case self::STATUS_MISSING:
                return 'badge badge-danger bg-danger';
            case self::STATUS_OUTDATED:
                return 'badge badge-warning bg-warning text-dark';
            default:
                return 'badge badge-secondary bg-secondary';
        }
    }
}
