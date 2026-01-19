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
 * Profession to sector mapper for CPURC import.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_cpurc;

defined('MOODLE_INTERNAL') || die();

/**
 * Maps profession text to FTM sector codes.
 */
class profession_mapper {

    /**
     * Keywords for each sector.
     * Order matters: more specific keywords should come first.
     */
    const PROFESSION_KEYWORDS = [
        'METALCOSTRUZIONE' => [
            'metalcostruttore',
            'fabbro',
            'saldatore',
            'fabbro ferraio',
            'esperto saldatore',
        ],
        'MECCANICA' => [
            'polimeccanico',
            'operatore cnc',
            'tornitore',
            'meccanico di macchine',
            'meccanico',
            'cnc',
        ],
        'AUTOMOBILE' => [
            'meccatronico',
            'carrozziere verniciatore',
            'carrozziere',
            'verniciatore',
            'automobil',
            'assistente di manutenzione per automobili',
        ],
        'LOGISTICA' => [
            'impiegato in logistica',
            'magazziniere',
            'logistica',
            'ausiliario, logistica',
        ],
        'ELETTRICITA' => [
            'installatore elettrico',
            'elettricista',
            'elettric',
        ],
        'AUTOMAZIONE' => [
            'automazione',
            'plc',
            'robot',
        ],
        'CHIMFARM' => [
            'chimico',
            'farmac',
            'laboratorio',
        ],
    ];

    /**
     * Sector display names.
     */
    const SECTOR_NAMES = [
        'AUTOMOBILE' => 'Automobile',
        'MECCANICA' => 'Meccanica',
        'LOGISTICA' => 'Logistica',
        'ELETTRICITA' => 'Elettricita',
        'AUTOMAZIONE' => 'Automazione',
        'METALCOSTRUZIONE' => 'Metalcostruzione',
        'CHIMFARM' => 'Chimico-Farmaceutico',
    ];

    /**
     * Detect sector from profession text.
     *
     * @param string $professiontext The profession text from CSV.
     * @return string|null The sector code or null if not detected.
     */
    public static function detect_sector($professiontext) {
        if (empty($professiontext)) {
            return null;
        }

        $text = strtolower(trim($professiontext));

        // Check each sector's keywords.
        foreach (self::PROFESSION_KEYWORDS as $sector => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    return $sector;
                }
            }
        }

        return null;
    }

    /**
     * Get all available sectors.
     *
     * @return array Array of sector code => name.
     */
    public static function get_all_sectors() {
        return self::SECTOR_NAMES;
    }

    /**
     * Get sector display name.
     *
     * @param string $sectorcode The sector code.
     * @return string The display name or the code if not found.
     */
    public static function get_sector_name($sectorcode) {
        return self::SECTOR_NAMES[$sectorcode] ?? $sectorcode;
    }

    /**
     * Check if a sector code is valid.
     *
     * @param string $sectorcode The sector code.
     * @return bool True if valid.
     */
    public static function is_valid_sector($sectorcode) {
        return isset(self::SECTOR_NAMES[$sectorcode]);
    }
}
