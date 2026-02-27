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
 * User management for CPURC import.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_cpurc;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/user/lib.php');

/**
 * Manages user creation, enrollment, cohorts and groups.
 */
class user_manager {

    /**
     * URC cohort mapping.
     */
    const URC_COHORTS = [
        'URC Bellinzona' => 'urc_bellinzona',
        'URC Chiasso' => 'urc_chiasso',
        'URC Lugano' => 'urc_lugano',
        'URC Biasca' => 'urc_biasca',
        'URC Locarno' => 'urc_locarno',
    ];

    /**
     * Color cycle for groups.
     */
    const COLOR_CYCLE = ['giallo', 'grigio', 'rosso', 'marrone', 'viola'];

    /**
     * Generate username from first name and last name.
     * Format: cognome3 + nome3 (first 3 letters of first lastname + first 3 letters of first firstname).
     * Accents, apostrophes, spaces removed. All lowercase.
     * Example: D'Agostino Maicol -> dagmai, Müller Giovanni José -> mulgio
     *
     * @param string $firstname First name (may contain multiple names).
     * @param string $lastname Last name (may contain multiple surnames).
     * @return string Unique username.
     */
    public static function generate_username($firstname, $lastname) {
        global $DB;

        // Take only the first name/surname if multiple are present.
        $fn_first = self::extract_first_name($firstname);
        $ln_first = self::extract_first_name($lastname);

        // Clean: remove accents, apostrophes, non-alpha chars, concatenate.
        $fn = self::clean_for_username($fn_first);
        $ln = self::clean_for_username($ln_first);

        $fn = substr($fn, 0, 3);
        $ln = substr($ln, 0, 3);

        // Pad if too short.
        $fn = str_pad($fn, 3, 'x');
        $ln = str_pad($ln, 3, 'x');

        // Cognome3 + Nome3 (matches LADI template).
        $base = strtolower($ln . $fn);

        // Ensure uniqueness.
        $username = $base;
        $counter = 1;
        while ($DB->record_exists('user', ['username' => $username])) {
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Extract the first name from a potentially multi-part name.
     * Handles: "Giovanni José" -> "Giovanni", "De Rossi" -> "De Rossi" (compound kept).
     * If name starts with a short prefix (De, Di, La, Le, Lo, Von, Van, Dal, Del),
     * it's treated as part of the surname and concatenated.
     *
     * @param string $name Full name string.
     * @return string First name extracted.
     */
    private static function extract_first_name($name) {
        $name = trim($name);
        if (empty($name)) {
            return '';
        }

        $parts = preg_split('/\s+/', $name);
        if (count($parts) <= 1) {
            return $name;
        }

        // If first part is a common prefix (De, Di, La, etc.), keep it attached to the next part.
        $prefixes = ['de', 'di', 'da', 'la', 'le', 'lo', 'li', 'del', 'dal', 'van', 'von', 'el', 'al'];
        if (in_array(strtolower($parts[0]), $prefixes) && count($parts) >= 2) {
            return $parts[0] . $parts[1];
        }

        return $parts[0];
    }

    /**
     * Clean string for username generation.
     * Removes accents, apostrophes, spaces, hyphens - keeps only letters.
     *
     * @param string $str Input string.
     * @return string Cleaned lowercase string (only a-z).
     */
    private static function clean_for_username($str) {
        // Remove accents.
        $str = self::remove_accents($str);
        // Remove everything that is not a letter (apostrophes, spaces, hyphens, numbers).
        $str = preg_replace('/[^a-zA-Z]/', '', $str);
        return strtolower($str);
    }

    /**
     * Remove accents from string.
     *
     * @param string $str Input string.
     * @return string String without accents.
     */
    private static function remove_accents($str) {
        $accents = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ñ' => 'N', 'Ç' => 'C',
        ];
        return strtr($str, $accents);
    }

    /**
     * Generate password from first name.
     * Format: 123 + FirstName (first word only, capitalized) + *
     * Example: "Giovanni José" -> "123Giovanni*"
     *
     * @param string $firstname First name.
     * @return string Password.
     */
    public static function generate_password($firstname) {
        $name = self::remove_accents(trim($firstname));
        // Take only the first name if multiple.
        $parts = preg_split('/\s+/', $name);
        $name = $parts[0];
        $name = ucfirst(strtolower($name));
        return '123' . $name . '*';
    }

    /**
     * Create or find Moodle user.
     * Uses Moodle core API user_create_user() for proper event triggering and cache invalidation.
     *
     * @param array $data User data from CSV.
     * @param bool $updateexisting Update existing user if found.
     * @return object Object with userid and created flag.
     */
    public static function create_or_find_user($data, $updateexisting = false) {
        global $DB, $CFG;

        $result = new \stdClass();
        $result->created = false;
        $result->updated = false;
        $result->userid = 0;

        // Check if user exists by email (case-insensitive).
        $existinguser = $DB->get_record_select('user',
            'LOWER(email) = LOWER(:email) AND deleted = 0',
            ['email' => trim($data['email'])]
        );

        if ($existinguser) {
            $result->userid = $existinguser->id;
            if ($updateexisting) {
                // Update basic fields via Moodle API.
                $updateuser = new \stdClass();
                $updateuser->id = $existinguser->id;
                $updateuser->firstname = $data['firstname'];
                $updateuser->lastname = $data['lastname'];
                $updateuser->phone1 = $data['phone'] ?? '';
                $updateuser->phone2 = $data['mobile'] ?? '';
                user_update_user($updateuser, false);
                $result->updated = true;
            }
            return $result;
        }

        // Create new user via Moodle API.
        $password_plain = self::generate_password($data['firstname']);

        $user = new \stdClass();
        $user->username = self::generate_username($data['firstname'], $data['lastname']);
        $user->password = $password_plain;
        $user->firstname = $data['firstname'];
        $user->lastname = $data['lastname'];
        $user->email = trim($data['email']);
        $user->phone1 = $data['phone'] ?? '';
        $user->phone2 = $data['mobile'] ?? '';
        $user->auth = 'manual';
        $user->confirmed = 1;
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->lang = 'it';
        $user->timezone = 'Europe/Zurich';

        // user_create_user() handles: hashing password, setting timecreated/timemodified,
        // triggering user_created event, invalidating caches.
        $result->userid = user_create_user($user);
        $result->created = true;
        $result->username = $user->username;
        $result->password_plain = $password_plain;

        return $result;
    }

    /**
     * Enroll user in course using Moodle enrol API.
     * Properly triggers events, invalidates caches, assigns role.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return bool Success.
     */
    public static function enrol_in_course($userid, $courseid) {
        global $DB, $CFG;

        require_once($CFG->libdir . '/enrollib.php');

        // Get manual enrol plugin.
        $enrolplugin = enrol_get_plugin('manual');
        if (!$enrolplugin) {
            throw new \Exception('Manual enrol plugin not available');
        }

        // Find manual enrol instance for this course.
        $instances = enrol_get_instances($courseid, true);
        $manualinstance = null;
        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {
                $manualinstance = $instance;
                break;
            }
        }

        // Create manual enrol instance if none exists.
        if (!$manualinstance) {
            $enrolplugin->add_instance(get_course($courseid));
            $instances = enrol_get_instances($courseid, true);
            foreach ($instances as $instance) {
                if ($instance->enrol === 'manual') {
                    $manualinstance = $instance;
                    break;
                }
            }
        }

        if (!$manualinstance) {
            throw new \Exception('Cannot create manual enrol instance for course ' . $courseid);
        }

        // Check if already enrolled.
        if (is_enrolled(\context_course::instance($courseid), $userid)) {
            return true;
        }

        // Enrol user via API (handles: user_enrolments, role_assign, events, caches).
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $enrolplugin->enrol_user($manualinstance, $userid, $studentroleid);

        return true;
    }

    /**
     * Add user to URC cohort.
     * Creates cohort if it doesn't exist.
     *
     * @param int $userid User ID.
     * @param string $urcoffice URC office name from CSV.
     * @return bool Success.
     */
    public static function add_to_cohort($userid, $urcoffice) {
        global $DB;

        if (empty($urcoffice)) {
            return false;
        }

        // Find cohort idnumber.
        $idnumber = null;
        foreach (self::URC_COHORTS as $name => $code) {
            if (stripos($urcoffice, $name) !== false || $urcoffice === $name) {
                $idnumber = $code;
                break;
            }
        }

        if (!$idnumber) {
            // Try partial match.
            $urcoffice_lower = strtolower($urcoffice);
            foreach (self::URC_COHORTS as $name => $code) {
                $name_lower = strtolower($name);
                if (strpos($urcoffice_lower, str_replace('urc ', '', $name_lower)) !== false) {
                    $idnumber = $code;
                    break;
                }
            }
        }

        if (!$idnumber) {
            return false;
        }

        // Get or create cohort.
        $cohort = $DB->get_record('cohort', ['idnumber' => $idnumber]);

        if (!$cohort) {
            // Create cohort.
            $cohort = new \stdClass();
            $cohort->contextid = \context_system::instance()->id;
            $cohort->name = array_search($idnumber, self::URC_COHORTS);
            $cohort->idnumber = $idnumber;
            $cohort->description = 'Coorte automatica per ' . $cohort->name;
            $cohort->visible = 1;
            $cohort->timecreated = time();
            $cohort->timemodified = time();
            $cohort->id = cohort_add_cohort($cohort);
        }

        // Add member if not already.
        if (!$DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $userid])) {
            cohort_add_member($cohort->id, $userid);
        }

        return true;
    }

    /**
     * Add user to color group based on start date.
     *
     * @param int $userid User ID.
     * @param int $datestart Start date timestamp.
     * @return bool Success.
     */
    public static function add_to_color_group($userid, $datestart) {
        global $DB;

        if (empty($datestart)) {
            return false;
        }

        // Calculate calendar week.
        $calendarweek = (int)date('W', $datestart);
        $year = (int)date('Y', $datestart);

        // Determine color (cycle every 5 weeks).
        $colorindex = ($calendarweek - 1) % 5;
        $color = self::COLOR_CYCLE[$colorindex];

        // Find or create group.
        $groupname = 'Gruppo ' . ucfirst($color) . ' - KW' . str_pad($calendarweek, 2, '0', STR_PAD_LEFT);

        $group = $DB->get_record('local_ftm_groups', [
            'color' => $color,
            'calendar_week' => $calendarweek,
        ]);

        if (!$group) {
            // Create group.
            $colorhex = [
                'giallo' => '#FFFF00',
                'grigio' => '#808080',
                'rosso' => '#FF0000',
                'marrone' => '#996633',
                'viola' => '#7030A0',
            ];

            $group = new \stdClass();
            $group->name = $groupname;
            $group->color = $color;
            $group->color_hex = $colorhex[$color];
            $group->entry_date = $datestart;
            $group->planned_end_date = strtotime('+6 weeks', $datestart);
            $group->calendar_week = $calendarweek;
            $group->status = 'active';
            $group->createdby = 0; // System.
            $group->timecreated = time();
            $group->timemodified = time();
            $group->id = $DB->insert_record('local_ftm_groups', $group);
        }

        // Add member if not already.
        if (!$DB->record_exists('local_ftm_group_members', ['groupid' => $group->id, 'userid' => $userid])) {
            $member = new \stdClass();
            $member->groupid = $group->id;
            $member->userid = $userid;
            $member->current_week = 1;
            $member->extended_weeks = 0;
            $member->status = 'active';
            $member->timecreated = time();
            $member->timemodified = time();
            $DB->insert_record('local_ftm_group_members', $member);
        }

        return true;
    }

    /**
     * Sync sector with sector_manager.
     *
     * @param int $userid User ID.
     * @param string $sector Sector code.
     * @param int $courseid Course ID.
     * @return bool Success.
     */
    public static function sync_sector($userid, $sector, $courseid = 0) {
        global $CFG;

        if (empty($sector)) {
            return false;
        }

        // Check if sector_manager exists.
        $sectormanagerpath = $CFG->dirroot . '/local/competencymanager/classes/sector_manager.php';
        if (!file_exists($sectormanagerpath)) {
            return false;
        }

        require_once($sectormanagerpath);

        if (class_exists('\\local_competencymanager\\sector_manager')) {
            \local_competencymanager\sector_manager::set_primary_sector($userid, $sector, $courseid);
            return true;
        }

        return false;
    }

    /**
     * Find course by name pattern.
     *
     * @param string $namepattern Course name pattern.
     * @return int|null Course ID or null.
     */
    public static function find_course($namepattern) {
        global $DB;

        $courses = $DB->get_records_select(
            'course',
            $DB->sql_like('fullname', ':pattern', false),
            ['pattern' => '%' . $DB->sql_like_escape($namepattern) . '%'],
            'id ASC',
            'id',
            0,
            1
        );

        if ($courses) {
            $course = reset($courses);
            return $course->id;
        }

        return null;
    }
}
