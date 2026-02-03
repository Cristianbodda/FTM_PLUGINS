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
 * Calendar importer class for FTM Scheduler.
 *
 * Imports activities from Excel planning file.
 * SIMPLIFIED VERSION - One activity per group, external bookings separate.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_scheduler;

defined('MOODLE_INTERNAL') || die();

/**
 * Class calendar_importer
 *
 * Excel structure (based on user's file):
 * - Column A: Date
 * - Column C: Matt/Pom
 * - Column M: Main Coach
 * - Column N: Group (GR. GRIGIO, etc.) OR Activity name (At. Canali, LABORATORIO)
 * - Column O: Secondary Coach (for Aula 3)
 * - Columns S-U: External projects (LADI, BIT, URAR)
 */
class calendar_importer {

    /** @var array Import statistics */
    private $stats = [
        'total_rows' => 0,
        'activities_created' => 0,
        'activities_updated' => 0,
        'external_bookings_created' => 0,
        'errors' => 0,
        'skipped' => 0,
    ];

    /** @var array Error messages */
    private $errors = [];

    /** @var array Coach mapping (initials => data) */
    private $coach_map = [];

    /** @var array Room mapping (name => id) */
    private $room_map = [];

    /** @var array Group color mapping */
    private $group_colors = [
        'GR. GIALLO' => 'giallo',
        'GR. GRIGIO' => 'grigio',
        'GR. ROSSO' => 'rosso',
        'GR. MARRONE' => 'marrone',
        'GR. VIOLA' => 'viola',
        'GIALLO' => 'giallo',
        'GRIGIO' => 'grigio',
        'ROSSO' => 'rosso',
        'MARRONE' => 'marrone',
        'VIOLA' => 'viola',
    ];

    /** @var array Activity types to detect in column N */
    private $activity_types = ['LABORATORIO', 'BILANCIO', 'OML', 'ATELIER', 'STAGE', 'TEST', 'AT.'];

    /** @var array External project names - these create ONLY bookings, NOT activities */
    private $external_projects = ['BIT URAR', 'BIT AI', 'URAR', 'LADI', 'EXTRA LADI', 'EXTRA-LADI', 'CORSO EXTRA'];

    /** @var array Time slots */
    private $time_slots = [
        'matt' => ['start' => '08:30', 'end' => '11:45', 'label' => 'Mattina'],
        'pom' => ['start' => '13:15', 'end' => '16:30', 'label' => 'Pomeriggio'],
    ];

    /** @var int Year for import */
    private $import_year;

    /**
     * @var array Coach-to-group tracking per week
     * Format: ['week_num' => ['COACH_INITIALS' => 'group_color']]
     * Used to assign groups to activities like LABORATORIO that don't have explicit group
     */
    private $coach_group_map = [];

    /** @var array Month names in Italian */
    private $month_names = [
        'gennaio' => 1, 'febbraio' => 2, 'marzo' => 3, 'aprile' => 4,
        'maggio' => 5, 'giugno' => 6, 'luglio' => 7, 'agosto' => 8,
        'settembre' => 9, 'ottobre' => 10, 'novembre' => 11, 'dicembre' => 12,
    ];

    /**
     * Constructor.
     */
    public function __construct($year = null) {
        $this->import_year = $year ?? date('Y');
        $this->load_coach_map();
        $this->load_room_map();
    }

    /**
     * Load coach mapping from database.
     */
    private function load_coach_map() {
        global $DB;

        if ($DB->get_manager()->table_exists('local_ftm_coaches')) {
            // Join with user table to get fullname
            $sql = "SELECT c.*, u.firstname, u.lastname
                    FROM {local_ftm_coaches} c
                    JOIN {user} u ON u.id = c.userid
                    WHERE c.active = 1";
            $coaches = $DB->get_records_sql($sql);
            foreach ($coaches as $coach) {
                $fullname = trim($coach->firstname . ' ' . $coach->lastname);
                $this->coach_map[strtoupper($coach->initials)] = [
                    'id' => $coach->id,
                    'userid' => $coach->userid,
                    'name' => $fullname ?: $coach->initials,
                    'initials' => $coach->initials,
                ];
            }
        }

        // Default coaches if table is empty
        if (empty($this->coach_map)) {
            $this->coach_map = [
                'CB' => ['initials' => 'CB', 'name' => 'Cristian Bodda'],
                'FM' => ['initials' => 'FM', 'name' => 'Fabio Marinoni'],
                'GM' => ['initials' => 'GM', 'name' => 'Graziano Margonar'],
                'RB' => ['initials' => 'RB', 'name' => 'Roberto Bravo'],
                'DB' => ['initials' => 'DB', 'name' => 'Danilo'],
                'SANDRA' => ['initials' => 'SANDRA', 'name' => 'Sandra'],
                'ALE' => ['initials' => 'ALE', 'name' => 'Alessandra'],
                'LP' => ['initials' => 'LP', 'name' => 'LP'],
                'NC' => ['initials' => 'NC', 'name' => 'NC'],
            ];
        }
    }

    /**
     * Load room mapping from database.
     */
    private function load_room_map() {
        global $DB;

        if ($DB->get_manager()->table_exists('local_ftm_rooms')) {
            $rooms = $DB->get_records('local_ftm_rooms');
            foreach ($rooms as $room) {
                $this->room_map[strtoupper($room->name)] = $room->id;
                if (!empty($room->shortname)) {
                    $this->room_map[strtoupper($room->shortname)] = $room->id;
                }
            }
        }

        if (empty($this->room_map)) {
            $this->room_map = [
                'AULA 1' => 1,
                'AULA 2' => 2,
                'AULA 3' => 3,
                'AULA1' => 1,
                'AULA2' => 2,
                'AULA3' => 3,
            ];
        }
    }

    /**
     * Get list of sheets from Excel file.
     */
    public function get_sheets($filepath) {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filepath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filepath);
            return $spreadsheet->getSheetNames();
        } catch (\Exception $e) {
            $this->errors[] = 'Error reading file: ' . $e->getMessage();
            return [];
        }
    }

    /**
     * Preview Excel file content.
     */
    public function preview_file($filepath, $sheetname = null, $maxrows = 50) {
        $result = [
            'sheets' => [],
            'preview' => [],
            'detected_year' => $this->import_year,
        ];

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filepath);
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($filepath);

            $result['sheets'] = $spreadsheet->getSheetNames();

            $sheet = $sheetname ? $spreadsheet->getSheetByName($sheetname) : $spreadsheet->getActiveSheet();
            if (!$sheet) {
                $sheet = $spreadsheet->getSheet(0);
            }

            $result['sheet_name'] = $sheet->getTitle();
            $result['preview'] = $this->parse_sheet($sheet, $maxrows);
            $result['total_activities'] = count($result['preview']);

        } catch (\Exception $e) {
            $this->errors[] = 'Error reading file: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Parse a sheet - SIMPLIFIED: one main activity + external bookings per row.
     *
     * IMPROVED: Two-pass parsing
     * Pass 1: Collect all coach-group associations per week
     * Pass 2: Parse activities, using coach-group map for activities without explicit group
     */
    private function parse_sheet($sheet, $maxrows = 0) {
        $activities = [];
        $currentDate = null;
        $count = 0;

        // Detect month from sheet name
        $sheetName = strtolower($sheet->getTitle());
        $sheetMonth = null;
        foreach ($this->month_names as $name => $num) {
            if (strpos($sheetName, $name) !== false) {
                $sheetMonth = $num;
                break;
            }
        }

        $highestRow = $sheet->getHighestRow();

        // Reset coach-group map
        $this->coach_group_map = [];

        // PASS 1: Build coach-group associations per week
        $tempDate = null;
        for ($row = 1; $row <= $highestRow; $row++) {
            $cellA = $sheet->getCell('A' . $row);
            $dateValue = $cellA->getCalculatedValue();
            if ($this->is_excel_date($dateValue)) {
                $tempDate = $this->excel_date_to_timestamp($dateValue, $sheetMonth);
            }

            $cellC = trim((string)$sheet->getCell('C' . $row)->getCalculatedValue());
            $slot = $this->detect_time_slot($cellC);

            if ($slot && $tempDate) {
                $this->collect_coach_group_associations($sheet, $row, $tempDate);
            }
        }

        // PASS 2: Parse activities with coach-group map available
        $currentDate = null;
        for ($row = 1; $row <= $highestRow; $row++) {
            if ($maxrows > 0 && $count >= $maxrows) {
                break;
            }

            // Get date from column A
            $cellA = $sheet->getCell('A' . $row);
            $dateValue = $cellA->getCalculatedValue();
            if ($this->is_excel_date($dateValue)) {
                $currentDate = $this->excel_date_to_timestamp($dateValue, $sheetMonth);
            }

            // Check if column C has Matt or Pom
            $cellC = trim((string)$sheet->getCell('C' . $row)->getCalculatedValue());
            $slot = $this->detect_time_slot($cellC);

            if ($slot && $currentDate) {
                // Parse ALL rooms for this row
                $roomActivities = $this->parse_row_all_rooms($sheet, $row, $currentDate, $slot);
                foreach ($roomActivities as $activity) {
                    $activities[] = $activity;
                    $count++;
                    if ($maxrows > 0 && $count >= $maxrows) {
                        break 2; // Exit both loops
                    }
                }
            }
        }

        return $activities;
    }

    /**
     * Collect coach-group associations from a row.
     * This is called in Pass 1 to build the coach_group_map.
     *
     * CORRECT column mapping (verified from debug output):
     * - M = Coach (GM, FM, RB, CB)
     * - N = Group/Activity (GR. GRIGIO, LABORATORIO, etc.)
     */
    private function collect_coach_group_associations($sheet, $row, $dateTimestamp) {
        // Get week number for this date
        $weekNum = date('W', $dateTimestamp);
        $yearWeek = date('Y', $dateTimestamp) . '-W' . $weekNum;

        // Initialize week if not exists
        if (!isset($this->coach_group_map[$yearWeek])) {
            $this->coach_group_map[$yearWeek] = [];
        }

        // Read coach from column M and group from column N
        $colM = strtoupper(trim((string)$sheet->getCell('M' . $row)->getCalculatedValue() ?? ''));
        $colN = strtoupper(trim((string)$sheet->getCell('N' . $row)->getCalculatedValue() ?? ''));

        // Find coach from column M
        $coach = null;
        if (isset($this->coach_map[$colM])) {
            $coach = $colM;
        }

        // Find group from column N
        $groupColor = null;
        foreach ($this->group_colors as $pattern => $color) {
            if (strpos($colN, $pattern) !== false) {
                $groupColor = $color;
                break;
            }
        }

        // If we found both coach and group, store the association
        if ($coach && $groupColor) {
            $this->coach_group_map[$yearWeek][$coach] = $groupColor;
        }
    }

    /**
     * Parse row and create MULTIPLE activities (one per room with content).
     *
     * CORRECT Column mapping (verified with user):
     * - K: Aula 1 Coach (e.g., DB)
     * - L: Aula 1 Activity - cell COLOR = group, BLACK = external (activity in COMMENT)
     * - M: Aula 2 Coach (e.g., GM, FM, RB)
     * - N: Aula 2 Activity - cell COLOR = group, or text (GR. GRIGIO, At. Canali)
     * - O: Aula 3 Coach (e.g., RB) - activity info in COMMENT
     * - P: Aula 3 Activity - cell COLOR indicates group/external
     *
     * @return array Array of activities (one per room with content)
     */
    private function parse_row_simple($sheet, $row, $dateTimestamp, $slot) {
        $slotInfo = $this->time_slots[$slot];
        $dateInfo = getdate($dateTimestamp);

        // Build timestamps
        $timeParts = explode(':', $slotInfo['start']);
        $timestampStart = mktime((int)$timeParts[0], (int)$timeParts[1], 0,
            $dateInfo['mon'], $dateInfo['mday'], $dateInfo['year']);

        $timeParts = explode(':', $slotInfo['end']);
        $timestampEnd = mktime((int)$timeParts[0], (int)$timeParts[1], 0,
            $dateInfo['mon'], $dateInfo['mday'], $dateInfo['year']);

        $activities = [];

        // Base activity data
        $baseData = [
            'date' => [
                'day' => $dateInfo['mday'],
                'month' => $dateInfo['mon'],
                'year' => $dateInfo['year'],
                'timestamp' => $dateTimestamp,
            ],
            'slot' => $slot,
            'slot_label' => $slotInfo['label'],
            'time_start' => $slotInfo['start'],
            'time_end' => $slotInfo['end'],
            'timestamp_start' => $timestampStart,
            'timestamp_end' => $timestampEnd,
        ];

        // Parse each room
        // AULA 1: Coach in K, Activity/Color in L
        $aula1 = $this->parse_room_data($sheet, $row, 'K', 'L', 1, $baseData, $dateTimestamp);
        if ($aula1) {
            $activities[] = $aula1;
        }

        // AULA 2: Coach in M, Activity/Color in N
        $aula2 = $this->parse_room_data($sheet, $row, 'M', 'N', 2, $baseData, $dateTimestamp);
        if ($aula2) {
            $activities[] = $aula2;
        }

        // AULA 3: Coach in O (with comment), Color in P
        $aula3 = $this->parse_room_data($sheet, $row, 'O', 'P', 3, $baseData, $dateTimestamp);
        if ($aula3) {
            $activities[] = $aula3;
        }

        // Return first activity for backwards compatibility, or null
        // The full list is available via parse_row_all_rooms()
        return !empty($activities) ? $activities[0] : null;
    }

    /**
     * Parse all rooms and return array of activities.
     */
    private function parse_row_all_rooms($sheet, $row, $dateTimestamp, $slot) {
        $slotInfo = $this->time_slots[$slot];
        $dateInfo = getdate($dateTimestamp);

        $timeParts = explode(':', $slotInfo['start']);
        $timestampStart = mktime((int)$timeParts[0], (int)$timeParts[1], 0,
            $dateInfo['mon'], $dateInfo['mday'], $dateInfo['year']);

        $timeParts = explode(':', $slotInfo['end']);
        $timestampEnd = mktime((int)$timeParts[0], (int)$timeParts[1], 0,
            $dateInfo['mon'], $dateInfo['mday'], $dateInfo['year']);

        $activities = [];

        $baseData = [
            'date' => [
                'day' => $dateInfo['mday'],
                'month' => $dateInfo['mon'],
                'year' => $dateInfo['year'],
                'timestamp' => $dateTimestamp,
            ],
            'slot' => $slot,
            'slot_label' => $slotInfo['label'],
            'time_start' => $slotInfo['start'],
            'time_end' => $slotInfo['end'],
            'timestamp_start' => $timestampStart,
            'timestamp_end' => $timestampEnd,
        ];

        // AULA 1: K + L
        $aula1 = $this->parse_room_data($sheet, $row, 'K', 'L', 1, $baseData, $dateTimestamp);
        if ($aula1) $activities[] = $aula1;

        // AULA 2: M + N
        $aula2 = $this->parse_room_data($sheet, $row, 'M', 'N', 2, $baseData, $dateTimestamp);
        if ($aula2) $activities[] = $aula2;

        // AULA 3: O + P
        $aula3 = $this->parse_room_data($sheet, $row, 'O', 'P', 3, $baseData, $dateTimestamp);
        if ($aula3) $activities[] = $aula3;

        return $activities;
    }

    /**
     * Parse data for a single room.
     *
     * @param object $sheet Excel sheet
     * @param int $row Row number
     * @param string $coachCol Column for coach (K, M, O)
     * @param string $activityCol Column for activity/color (L, N, P)
     * @param int $roomNum Room number (1, 2, 3)
     * @param array $baseData Base activity data
     * @param int $dateTimestamp Date timestamp
     * @return array|null Activity data or null if no content
     */
    private function parse_room_data($sheet, $row, $coachCol, $activityCol, $roomNum, $baseData, $dateTimestamp) {
        // Read coach from coach column
        $coachValue = strtoupper(trim((string)$sheet->getCell($coachCol . $row)->getCalculatedValue() ?? ''));

        // If no coach, this room has no activity
        if (empty($coachValue) || !isset($this->coach_map[$coachValue])) {
            return null;
        }

        $coach = $coachValue;

        // Read activity column value and color
        $activityCell = $sheet->getCell($activityCol . $row);
        $activityValue = trim((string)$activityCell->getCalculatedValue() ?? '');
        $activityValueUpper = strtoupper($activityValue);

        // Get cell background color
        $cellColor = $this->get_cell_background_color($sheet, $activityCol . $row);

        // Get comment/note from coach cell (for Aula 3) or activity cell
        $commentCoach = $this->get_cell_comment($sheet, $coachCol . $row);
        $commentActivity = $this->get_cell_comment($sheet, $activityCol . $row);

        // Determine if this is an external project (black cell)
        $isExternal = $this->is_black_color($cellColor);

        // Determine group color from cell background
        $groupColor = null;
        $groupLabel = null;

        if (!$isExternal) {
            $groupColor = $this->color_to_group($cellColor);
            if ($groupColor) {
                $colorNames = [
                    'giallo' => 'GIALLO', 'grigio' => 'GRIGIO', 'rosso' => 'ROSSO',
                    'marrone' => 'MARRONE', 'viola' => 'VIOLA',
                ];
                $groupLabel = 'GR. ' . ($colorNames[$groupColor] ?? strtoupper($groupColor));
            }
        }

        // Also check text content for group names (fallback)
        if (!$groupColor && !$isExternal) {
            foreach ($this->group_colors as $pattern => $color) {
                if (strpos($activityValueUpper, $pattern) !== false) {
                    $groupColor = $color;
                    $groupLabel = $activityValue;
                    break;
                }
            }
        }

        // If still no group, try to infer from coach-group map
        if (!$groupColor && !$isExternal) {
            $weekNum = date('W', $dateTimestamp);
            $yearWeek = date('Y', $dateTimestamp) . '-W' . $weekNum;

            if ($coach && isset($this->coach_group_map[$yearWeek][$coach])) {
                $groupColor = $this->coach_group_map[$yearWeek][$coach];
                $colorNames = [
                    'giallo' => 'GIALLO', 'grigio' => 'GRIGIO', 'rosso' => 'ROSSO',
                    'marrone' => 'MARRONE', 'viola' => 'VIOLA',
                ];
                $groupLabel = 'GR. ' . ($colorNames[$groupColor] ?? strtoupper($groupColor));
            }
        }

        // Determine activity name
        $activityName = null;

        // For external projects, get activity from comment
        if ($isExternal) {
            // Check comment on coach cell first (Aula 3 style)
            if (!empty($commentCoach)) {
                $activityName = $commentCoach;
            } elseif (!empty($commentActivity)) {
                $activityName = $commentActivity;
            } else {
                $activityName = 'Attività Esterna';
            }
        } else {
            // Check if activity value contains known activity type
            foreach ($this->activity_types as $type) {
                if (strpos($activityValueUpper, $type) !== false) {
                    $activityName = $activityValue;
                    break;
                }
            }

            // Check comments for activity
            if (!$activityName && !empty($commentCoach)) {
                foreach ($this->activity_types as $type) {
                    if (stripos($commentCoach, $type) !== false) {
                        $activityName = $commentCoach;
                        break;
                    }
                }
            }
            if (!$activityName && !empty($commentActivity)) {
                foreach ($this->activity_types as $type) {
                    if (stripos($commentActivity, $type) !== false) {
                        $activityName = $commentActivity;
                        break;
                    }
                }
            }
        }

        // Build activity data
        $activity = array_merge($baseData, [
            'main_coach' => $coach,
            'group_color' => $groupColor,
            'group_label' => $groupLabel,
            'activity_name' => $activityName,
            'room_id' => $roomNum,
            'room_name' => 'Aula ' . $roomNum,
            'is_external' => $isExternal,
            'external_projects' => $isExternal ? [$activityName ?: 'LADI'] : [],
            'cell_color_hex' => $cellColor,

            // Preview-compatible keys
            'coaches' => [['initials' => $coach, 'name' => $this->coach_map[$coach]['name'] ?? $coach, 'room' => 'Aula ' . $roomNum]],
            'groups' => $groupLabel ? [['color' => $groupColor, 'label' => $groupLabel]] : [],
            'activities' => $activityName ? [['label' => $activityName, 'room' => 'Aula ' . $roomNum]] : [],
        ]);

        return $activity;
    }

    /**
     * Get cell background color as hex string.
     */
    private function get_cell_background_color($sheet, $cellRef) {
        try {
            $style = $sheet->getStyle($cellRef);
            $fill = $style->getFill();
            $fillType = $fill->getFillType();

            if ($fillType === \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID) {
                $color = $fill->getStartColor();
                $rgb = $color->getRGB();
                if (!empty($rgb) && $rgb !== '000000') {
                    return '#' . $rgb;
                }
                // Check if it's explicitly black
                $argb = $color->getARGB();
                if ($argb === 'FF000000' || $rgb === '000000') {
                    return '#000000';
                }
            }
        } catch (\Exception $e) {
            // Ignore errors
        }
        return null;
    }

    /**
     * Get cell comment/note text.
     */
    private function get_cell_comment($sheet, $cellRef) {
        try {
            $comment = $sheet->getComment($cellRef);
            if ($comment && $comment->getText()) {
                return trim($comment->getText()->getPlainText());
            }
        } catch (\Exception $e) {
            // Ignore errors
        }
        return '';
    }

    /**
     * Check if color is black (external project indicator).
     */
    private function is_black_color($hexColor) {
        if (empty($hexColor)) {
            return false;
        }
        $hex = ltrim($hexColor, '#');

        // Check for pure black or very dark colors
        if ($hex === '000000' || $hex === '000' || strtolower($hex) === '000000') {
            return true;
        }

        // Parse RGB and check if very dark
        if (strlen($hex) === 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));

            // Consider very dark colors as black (threshold: 30)
            return ($r < 30 && $g < 30 && $b < 30);
        }

        return false;
    }

    /**
     * Convert cell color to group color name.
     */
    private function color_to_group($hexColor) {
        if (empty($hexColor)) {
            return null;
        }

        $hex = strtoupper(ltrim($hexColor, '#'));

        // Map of known Excel colors to group colors
        // These may need adjustment based on actual Excel colors used
        $colorMap = [
            // Yellow variations
            'FFFF00' => 'giallo',
            'FFFF99' => 'giallo',
            'FFFFCC' => 'giallo',
            'FFF200' => 'giallo',
            'FFE600' => 'giallo',

            // Gray variations
            '808080' => 'grigio',
            'A6A6A6' => 'grigio',
            'BFBFBF' => 'grigio',
            'D9D9D9' => 'grigio',
            'C0C0C0' => 'grigio',
            '969696' => 'grigio',

            // Red variations
            'FF0000' => 'rosso',
            'FF6666' => 'rosso',
            'FF9999' => 'rosso',
            'FF3333' => 'rosso',
            'CC0000' => 'rosso',

            // Brown variations
            '996633' => 'marrone',
            'CC9966' => 'marrone',
            '993300' => 'marrone',
            'C65911' => 'marrone',
            'BF8F00' => 'marrone',
            '806000' => 'marrone',

            // Purple/Violet variations
            '7030A0' => 'viola',
            '9933FF' => 'viola',
            'CC99FF' => 'viola',
            '660099' => 'viola',
            '9900CC' => 'viola',
            '8064A2' => 'viola',

            // Green (for Aula 3 activities with coach)
            '00FF00' => 'verde',
            '92D050' => 'verde',
            '00B050' => 'verde',
            '00B0F0' => 'verde', // Light blue sometimes used
        ];

        if (isset($colorMap[$hex])) {
            return $colorMap[$hex];
        }

        // Try to match by color family (heuristic)
        if (strlen($hex) === 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));

            // Yellow: high R and G, low B
            if ($r > 200 && $g > 200 && $b < 150) {
                return 'giallo';
            }

            // Gray: R, G, B are similar and medium
            if (abs($r - $g) < 30 && abs($g - $b) < 30 && $r > 100 && $r < 220) {
                return 'grigio';
            }

            // Red: high R, low G and B
            if ($r > 180 && $g < 150 && $b < 150) {
                return 'rosso';
            }

            // Brown: medium R, lower G and B
            if ($r > 150 && $r < 230 && $g > 80 && $g < 180 && $b < 120) {
                return 'marrone';
            }

            // Purple: medium-high R and B, lower G
            if ($r > 80 && $b > 100 && $g < $r && $g < $b) {
                return 'viola';
            }
        }

        return null;
    }

    /**
     * Check if value is an Excel date.
     */
    private function is_excel_date($value) {
        if (!is_numeric($value)) {
            return false;
        }
        $num = (float)$value;
        return $num > 40000 && $num < 70000;
    }

    /**
     * Convert Excel serial date to Unix timestamp.
     */
    private function excel_date_to_timestamp($excelDate, $expectedMonth = null) {
        try {
            $dateTime = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($excelDate);
            $year = (int)$dateTime->format('Y');
            $month = (int)$dateTime->format('n');
            $day = (int)$dateTime->format('j');

            if ($this->import_year && $year !== $this->import_year) {
                $year = $this->import_year;
            }

            return mktime(12, 0, 0, $month, $day, $year);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Detect time slot from cell value.
     */
    private function detect_time_slot($value) {
        $value = strtolower(trim($value));
        if (strpos($value, 'matt') !== false || $value === 'm') {
            return 'matt';
        }
        if (strpos($value, 'pom') !== false || $value === 'p') {
            return 'pom';
        }
        return null;
    }

    /**
     * Import activities from Excel file.
     */
    public function import_file($filepath, $options = []) {
        global $DB;

        $defaults = [
            'sheets' => [],
            'update_existing' => false,
            'dry_run' => false,
        ];
        $options = array_merge($defaults, $options);

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filepath);
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($filepath);

            $sheetNames = $spreadsheet->getSheetNames();
            if (!empty($options['sheets'])) {
                $sheetNames = array_intersect($sheetNames, $options['sheets']);
            }

            foreach ($sheetNames as $sheetName) {
                $sheet = $spreadsheet->getSheetByName($sheetName);
                if (!$sheet) {
                    continue;
                }

                $activities = $this->parse_sheet($sheet);
                $this->stats['total_rows'] += count($activities);

                foreach ($activities as $activityData) {
                    if ($options['dry_run']) {
                        if (!empty($activityData['is_external'])) {
                            $this->stats['external_bookings_created']++;
                        } else {
                            $this->stats['activities_created']++;
                        }
                        continue;
                    }

                    try {
                        // Create activity (handles both regular and external)
                        $result = $this->create_main_activity($activityData, $options);

                        if ($result === 'created') {
                            if (!empty($activityData['is_external'])) {
                                $this->stats['external_bookings_created']++;
                            } else {
                                $this->stats['activities_created']++;
                            }
                        } else if ($result === 'updated') {
                            $this->stats['activities_updated']++;
                        } else {
                            $this->stats['skipped']++;
                        }

                    } catch (\Exception $e) {
                        $this->stats['errors']++;
                        $this->errors[] = sprintf(
                            'Error importing %s %s Aula %s: %s',
                            date('d/m/Y', $activityData['timestamp_start']),
                            $activityData['slot_label'] ?? '',
                            $activityData['room_id'] ?? '?',
                            $e->getMessage()
                        );
                    }
                }
            }

        } catch (\Exception $e) {
            $this->errors[] = 'Error reading file: ' . $e->getMessage();
            $this->stats['errors']++;
        }

        return [
            'success' => empty($this->errors) || $this->stats['activities_created'] > 0,
            'stats' => $this->stats,
            'errors' => $this->errors,
        ];
    }

    /**
     * Create activity for a room.
     * Handles both regular activities (groups) and external projects.
     */
    private function create_main_activity($data, $options) {
        global $DB;

        $roomid = $data['room_id'] ?? 2;

        // If this is an external project, create external booking instead
        if (!empty($data['is_external'])) {
            return $this->create_external_booking_from_data($data, $options);
        }

        // Check if activity already exists for this date/slot/room
        $existing = $DB->get_record('local_ftm_activities', [
            'date_start' => $data['timestamp_start'],
            'roomid' => $roomid,
        ]);

        if ($existing && !$options['update_existing']) {
            return 'skipped';
        }

        // Generate activity name
        $name = $this->generate_simple_name($data);

        // Get or create group
        $groupid = null;
        if (!empty($data['group_color'])) {
            $groupid = $this->get_or_create_group($data['group_color'], $data['timestamp_start']);
        }

        // Get teacher ID from coach
        $teacherid = null;
        if (!empty($data['main_coach']) && isset($this->coach_map[$data['main_coach']]['userid'])) {
            $teacherid = $this->coach_map[$data['main_coach']]['userid'];
        }

        // Determine type
        $type = 'general';
        if (!empty($data['group_color'])) {
            $type = 'group';
        } elseif (!empty($data['activity_name'])) {
            $actUpper = strtoupper($data['activity_name']);
            if (strpos($actUpper, 'LABORATORIO') !== false) $type = 'lab';
            elseif (strpos($actUpper, 'ATELIER') !== false) $type = 'atelier';
            elseif (strpos($actUpper, 'BILANCIO') !== false) $type = 'bilancio';
            elseif (strpos($actUpper, 'OML') !== false) $type = 'oml';
        }

        $record = new \stdClass();
        $record->name = $name;
        $record->type = $type;
        $record->groupid = $groupid;
        $record->roomid = $roomid;
        $record->teacherid = $teacherid;
        $record->date_start = $data['timestamp_start'];
        $record->date_end = $data['timestamp_end'];
        $record->max_participants = 10;
        $record->timemodified = time();

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_ftm_activities', $record);
            return 'updated';
        } else {
            $record->timecreated = time();
            $DB->insert_record('local_ftm_activities', $record);
            return 'created';
        }
    }

    /**
     * Create external booking from activity data.
     */
    private function create_external_booking_from_data($data, $options) {
        global $DB, $USER;

        $roomid = $data['room_id'] ?? 3;

        // Determine project name from activity name or default
        $projectName = 'LADI';
        if (!empty($data['activity_name'])) {
            $actUpper = strtoupper($data['activity_name']);
            if (strpos($actUpper, 'BIT') !== false) {
                $projectName = strpos($actUpper, 'URAR') !== false ? 'BIT URAR' : 'BIT AI';
            } elseif (strpos($actUpper, 'URAR') !== false) {
                $projectName = 'URAR';
            } elseif (strpos($actUpper, 'LADI') !== false || strpos($actUpper, 'EXTRA') !== false) {
                $projectName = 'LADI';
            } else {
                $projectName = $data['activity_name'];
            }
        }

        // Check if booking already exists
        $existing = $DB->get_record('local_ftm_external_bookings', [
            'date_start' => $data['timestamp_start'],
            'roomid' => $roomid,
        ]);

        if ($existing && !$options['update_existing']) {
            return 'skipped';
        }

        $record = new \stdClass();
        $record->project_name = $projectName;
        $record->roomid = $roomid;
        $record->date_start = $data['timestamp_start'];
        $record->date_end = $data['timestamp_end'];
        $record->responsible = $data['main_coach'] ?? '';
        $record->notes = 'Importato da Excel - ' . ($data['slot_label'] ?? '') . ' - Aula ' . $roomid;
        $record->timecreated = time();
        $record->createdby = $USER->id ?? 0;

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_ftm_external_bookings', $record);
            return 'updated';
        } else {
            $DB->insert_record('local_ftm_external_bookings', $record);
            return 'created';
        }
    }

    /**
     * Generate activity name with room, coach, and time info.
     */
    private function generate_simple_name($data) {
        $parts = [];

        // Room name
        if (!empty($data['room_name'])) {
            $parts[] = $data['room_name'];
        } elseif (!empty($data['room_id'])) {
            $parts[] = 'Aula ' . $data['room_id'];
        }

        // Priority: Activity name > Group label
        if (!empty($data['activity_name'])) {
            $parts[] = $data['activity_name'];
        } elseif (!empty($data['group_label'])) {
            $parts[] = $data['group_label'];
        }

        // Add coach
        if (!empty($data['main_coach'])) {
            $parts[] = $data['main_coach'];
        }

        // Slot and date
        $parts[] = $data['slot_label'] ?? '';
        $parts[] = date('d/m', $data['timestamp_start']);

        return implode(' - ', array_filter($parts));
    }

    /**
     * Create external booking (LADI, BIT, etc.) - NO activity!
     */
    private function create_external_booking_simple($data, $projectName, $options) {
        global $DB, $USER;

        // Check if booking already exists
        $existing = $DB->get_record('local_ftm_external_bookings', [
            'date_start' => $data['timestamp_start'],
            'project_name' => $projectName,
        ]);

        if ($existing && !$options['update_existing']) {
            return 'skipped';
        }

        // External bookings use secondary coach if available, otherwise empty
        $responsible = '';
        if (!empty($data['secondary_coach'])) {
            $responsible = $data['secondary_coach'];
        }

        $record = new \stdClass();
        $record->project_name = $projectName;
        $record->roomid = 3; // External projects typically in Aula 3 or separate
        $record->date_start = $data['timestamp_start'];
        $record->date_end = $data['timestamp_end'];
        $record->responsible = $responsible;
        $record->notes = 'Importato da Excel';
        $record->timecreated = time();
        $record->createdby = $USER->id ?? 0;

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_ftm_external_bookings', $record);
            return 'updated';
        } else {
            $DB->insert_record('local_ftm_external_bookings', $record);
            return 'created';
        }
    }

    /** @var array Color hex mapping */
    private $color_hex_map = [
        'giallo' => '#FFFF00',
        'grigio' => '#808080',
        'rosso' => '#FF0000',
        'marrone' => '#996633',
        'viola' => '#7030A0',
    ];

    /**
     * Get or create a group by color.
     */
    private function get_or_create_group($color, $entryDate) {
        global $DB, $USER;

        $color = strtolower($color);

        $group = $DB->get_record('local_ftm_groups', ['color' => $color, 'status' => 'active']);
        if ($group) {
            return $group->id;
        }

        $group = $DB->get_record('local_ftm_groups', ['color' => $color]);
        if ($group) {
            $group->status = 'active';
            $group->timemodified = time();
            $DB->update_record('local_ftm_groups', $group);
            return $group->id;
        }

        $colorNames = [
            'giallo' => 'Giallo', 'grigio' => 'Grigio', 'rosso' => 'Rosso',
            'marrone' => 'Marrone', 'viola' => 'Viola',
        ];

        $week = date('W', $entryDate);
        $colorName = $colorNames[$color] ?? ucfirst($color);
        $colorHex = $this->color_hex_map[$color] ?? '#CCCCCC';

        $newGroup = new \stdClass();
        $newGroup->name = "Gruppo $colorName - KW" . str_pad($week, 2, '0', STR_PAD_LEFT);
        $newGroup->color = $color;
        $newGroup->color_hex = $colorHex;
        $newGroup->entry_date = $entryDate;
        $newGroup->planned_end_date = $entryDate + (6 * 7 * 86400);
        $newGroup->calendar_week = (int)$week;
        $newGroup->status = 'active';
        $newGroup->timecreated = time();
        $newGroup->timemodified = time();
        $newGroup->createdby = $USER->id ?? 0;

        return $DB->insert_record('local_ftm_groups', $newGroup);
    }

    /**
     * Get import statistics.
     */
    public function get_stats() {
        return $this->stats;
    }

    /**
     * Get error messages.
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Get coach mapping.
     */
    public function get_coach_map() {
        return $this->coach_map;
    }

    /**
     * Get room mapping.
     */
    public function get_room_map() {
        return $this->room_map;
    }
}
