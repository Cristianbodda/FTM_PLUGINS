<?php
// ============================================
// FTM Scheduler - Notification Helper
// Gestisce notifiche assenze via Email e Moodle
// ============================================

namespace local_ftm_scheduler;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for sending absence notifications
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_helper {

    /**
     * Send notification when a student is marked absent
     *
     * @param object $enrollment The enrollment record with absence
     * @return bool True if notifications sent successfully
     */
    public function notify_absence($enrollment) {
        global $DB;

        // Check if already notified
        if (!empty($enrollment->absence_notified)) {
            return true;
        }

        // Get student info
        $student = $DB->get_record('user', ['id' => $enrollment->userid],
            'id, firstname, lastname, email');
        if (!$student) {
            return false;
        }

        // Get activity info
        $activity = $DB->get_record('local_ftm_activities', ['id' => $enrollment->activityid],
            'id, title, activity_date, start_time, end_time, room_id');
        if (!$activity) {
            return false;
        }

        // Get atelier info if available
        $atelier_name = '';
        if (!empty($activity->atelier_id)) {
            $atelier = $DB->get_record('local_ftm_atelier_catalog', ['id' => $activity->atelier_id], 'name');
            if ($atelier) {
                $atelier_name = $atelier->name;
            }
        }

        // Get room info
        $room_name = '';
        if (!empty($activity->room_id)) {
            $room = $DB->get_record('local_ftm_rooms', ['id' => $activity->room_id], 'name');
            if ($room) {
                $room_name = $room->name;
            }
        }

        // Build notification data
        $data = new \stdClass();
        $data->student_name = fullname($student);
        $data->student_id = $student->id;
        $data->activity_title = $activity->title;
        $data->activity_date = date('d/m/Y', $activity->activity_date);
        $data->activity_time = $activity->start_time . ' - ' . $activity->end_time;
        $data->atelier_name = $atelier_name;
        $data->room_name = $room_name;

        // Get recipients: coach and secretary
        $recipients = $this->get_notification_recipients($student->id);

        $success = true;

        foreach ($recipients as $recipient) {
            // Send Moodle notification
            $notif_success = $this->send_moodle_notification($recipient, $data);

            // Send email
            $email_success = $this->send_email_notification($recipient, $data);

            if (!$notif_success && !$email_success) {
                $success = false;
            }
        }

        // Mark as notified
        if ($success) {
            $enrollment->absence_notified = 1;
            $DB->update_record('local_ftm_enrollments', $enrollment);
        }

        return $success;
    }

    /**
     * Get recipients for absence notifications (coach + secretary)
     *
     * @param int $studentid The student ID
     * @return array Array of user objects to notify
     */
    protected function get_notification_recipients($studentid) {
        global $DB;

        $recipients = [];

        // 1. Get the coach assigned to this student
        // Check if student has coach_id in ftm_student_assignments
        $assignment = $DB->get_record('local_ftm_student_assignments',
            ['studentid' => $studentid], 'coachid');

        if ($assignment && !empty($assignment->coachid)) {
            $coach = $DB->get_record('user', ['id' => $assignment->coachid, 'deleted' => 0],
                'id, firstname, lastname, email');
            if ($coach) {
                $recipients[] = $coach;
            }
        }

        // 2. Get all users with secretary capability (local/coachmanager:viewall)
        $context = \context_system::instance();
        $secretaries = get_users_by_capability($context, 'local/coachmanager:viewall',
            'u.id, u.firstname, u.lastname, u.email', '', '', '', '', '', false);

        foreach ($secretaries as $secretary) {
            // Avoid duplicates
            $already_added = false;
            foreach ($recipients as $r) {
                if ($r->id == $secretary->id) {
                    $already_added = true;
                    break;
                }
            }
            if (!$already_added) {
                $recipients[] = $secretary;
            }
        }

        return $recipients;
    }

    /**
     * Send Moodle notification
     *
     * @param object $recipient User to notify
     * @param object $data Notification data
     * @return bool Success
     */
    protected function send_moodle_notification($recipient, $data) {
        global $USER;

        $message = new \core\message\message();
        $message->component = 'local_ftm_scheduler';
        $message->name = 'absence_notification';
        $message->userfrom = $USER;
        $message->userto = $recipient;
        $message->subject = get_string('absence_notification_subject', 'local_ftm_scheduler', $data->student_name);
        $message->fullmessage = $this->build_notification_text($data);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $this->build_notification_html($data);
        $message->smallmessage = get_string('absence_notification_small', 'local_ftm_scheduler', $data);
        $message->notification = 1;
        $message->contexturl = new \moodle_url('/local/ftm_scheduler/attendance.php');
        $message->contexturlname = get_string('attendance', 'local_ftm_scheduler');

        try {
            $messageid = message_send($message);
            return !empty($messageid);
        } catch (\Exception $e) {
            debugging('FTM Scheduler: Failed to send Moodle notification: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Send email notification
     *
     * @param object $recipient User to notify
     * @param object $data Notification data
     * @return bool Success
     */
    protected function send_email_notification($recipient, $data) {
        global $USER;

        $subject = get_string('absence_notification_subject', 'local_ftm_scheduler', $data->student_name);
        $messagetext = $this->build_notification_text($data);
        $messagehtml = $this->build_notification_html($data);

        try {
            $success = email_to_user(
                $recipient,
                $USER,
                $subject,
                $messagetext,
                $messagehtml
            );
            return $success;
        } catch (\Exception $e) {
            debugging('FTM Scheduler: Failed to send email notification: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Build plain text notification message
     *
     * @param object $data Notification data
     * @return string Plain text message
     */
    protected function build_notification_text($data) {
        $text = get_string('absence_notification_intro', 'local_ftm_scheduler') . "\n\n";
        $text .= get_string('student', 'local_ftm_scheduler') . ": " . $data->student_name . "\n";
        $text .= get_string('activity', 'local_ftm_scheduler') . ": " . $data->activity_title . "\n";
        $text .= get_string('date', 'local_ftm_scheduler') . ": " . $data->activity_date . "\n";
        $text .= get_string('time', 'local_ftm_scheduler') . ": " . $data->activity_time . "\n";

        if (!empty($data->atelier_name)) {
            $text .= get_string('atelier', 'local_ftm_scheduler') . ": " . $data->atelier_name . "\n";
        }
        if (!empty($data->room_name)) {
            $text .= get_string('room', 'local_ftm_scheduler') . ": " . $data->room_name . "\n";
        }

        $text .= "\n" . get_string('absence_notification_footer', 'local_ftm_scheduler');

        return $text;
    }

    /**
     * Build HTML notification message
     *
     * @param object $data Notification data
     * @return string HTML message
     */
    protected function build_notification_html($data) {
        $html = '<div style="font-family: Arial, sans-serif; max-width: 600px;">';
        $html .= '<div style="background: #dc3545; color: white; padding: 15px; border-radius: 8px 8px 0 0;">';
        $html .= '<h2 style="margin: 0;">' . get_string('absence_notification_title', 'local_ftm_scheduler') . '</h2>';
        $html .= '</div>';

        $html .= '<div style="background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 8px 8px;">';
        $html .= '<p style="color: #333;">' . get_string('absence_notification_intro', 'local_ftm_scheduler') . '</p>';

        $html .= '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">';
        $html .= '<tr><td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold; width: 120px;">' .
                 get_string('student', 'local_ftm_scheduler') . '</td>';
        $html .= '<td style="padding: 8px; border-bottom: 1px solid #dee2e6;">' . s($data->student_name) . '</td></tr>';

        $html .= '<tr><td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">' .
                 get_string('activity', 'local_ftm_scheduler') . '</td>';
        $html .= '<td style="padding: 8px; border-bottom: 1px solid #dee2e6;">' . s($data->activity_title) . '</td></tr>';

        $html .= '<tr><td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">' .
                 get_string('date', 'local_ftm_scheduler') . '</td>';
        $html .= '<td style="padding: 8px; border-bottom: 1px solid #dee2e6;">' . s($data->activity_date) . '</td></tr>';

        $html .= '<tr><td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">' .
                 get_string('time', 'local_ftm_scheduler') . '</td>';
        $html .= '<td style="padding: 8px; border-bottom: 1px solid #dee2e6;">' . s($data->activity_time) . '</td></tr>';

        if (!empty($data->atelier_name)) {
            $html .= '<tr><td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">' .
                     get_string('atelier', 'local_ftm_scheduler') . '</td>';
            $html .= '<td style="padding: 8px; border-bottom: 1px solid #dee2e6;">' . s($data->atelier_name) . '</td></tr>';
        }

        if (!empty($data->room_name)) {
            $html .= '<tr><td style="padding: 8px; font-weight: bold;">' .
                     get_string('room', 'local_ftm_scheduler') . '</td>';
            $html .= '<td style="padding: 8px;">' . s($data->room_name) . '</td></tr>';
        }

        $html .= '</table>';

        $html .= '<p style="color: #6c757d; font-size: 12px; margin-top: 20px;">' .
                 get_string('absence_notification_footer', 'local_ftm_scheduler') . '</p>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Send bulk absence notifications (for scheduled task)
     *
     * @return int Number of notifications sent
     */
    public function send_pending_notifications() {
        global $DB;

        // Find all absences not yet notified
        $sql = "SELECT e.*
                FROM {local_ftm_enrollments} e
                WHERE e.status = 'absent'
                AND e.absence_notified = 0";

        $pending = $DB->get_records_sql($sql);
        $count = 0;

        foreach ($pending as $enrollment) {
            if ($this->notify_absence($enrollment)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get absence statistics for a student
     *
     * @param int $studentid The student ID
     * @return object Statistics object
     */
    public function get_student_absence_stats($studentid) {
        global $DB;

        $stats = new \stdClass();

        // Total enrollments
        $stats->total = $DB->count_records('local_ftm_enrollments', ['userid' => $studentid]);

        // Attended
        $stats->attended = $DB->count_records('local_ftm_enrollments', [
            'userid' => $studentid,
            'status' => 'attended'
        ]);

        // Absent
        $stats->absent = $DB->count_records('local_ftm_enrollments', [
            'userid' => $studentid,
            'status' => 'absent'
        ]);

        // Cancelled
        $stats->cancelled = $DB->count_records('local_ftm_enrollments', [
            'userid' => $studentid,
            'status' => 'cancelled'
        ]);

        // Enrolled (pending)
        $stats->enrolled = $DB->count_records('local_ftm_enrollments', [
            'userid' => $studentid,
            'status' => 'enrolled'
        ]);

        // Calculate absence rate (excluding cancelled)
        $completed = $stats->attended + $stats->absent;
        $stats->absence_rate = $completed > 0 ? round(($stats->absent / $completed) * 100, 1) : 0;

        // Recent absences (last 30 days)
        $thirty_days_ago = time() - (30 * 24 * 60 * 60);
        $sql = "SELECT COUNT(*)
                FROM {local_ftm_enrollments} e
                JOIN {local_ftm_activities} a ON e.activityid = a.id
                WHERE e.userid = :userid
                AND e.status = 'absent'
                AND a.activity_date >= :date_limit";
        $stats->recent_absences = $DB->count_records_sql($sql, [
            'userid' => $studentid,
            'date_limit' => $thirty_days_ago
        ]);

        return $stats;
    }
}
