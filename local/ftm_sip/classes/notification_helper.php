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
 * Notification helper for SIP plugin.
 *
 * Handles all email + Moodle notifications:
 * - Appointment reminders (student + coach)
 * - Action deadline reminders (student)
 * - Student inactivity alerts (coach)
 * - Meeting not logged reminders (coach)
 * - Plan update notifications (student)
 * - New appointment notifications (student)
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_sip;

defined('MOODLE_INTERNAL') || die();

class notification_helper {

    /**
     * Send appointment reminder to student (1 day before).
     *
     * @param object $appointment Appointment record.
     * @param object $student User record.
     * @param object $coach User record.
     */
    public static function send_appointment_reminder($appointment, $student, $coach) {
        $data = new \stdClass();
        $data->date = userdate($appointment->appointment_date, '%d/%m/%Y');
        $data->time = $appointment->time_start;
        $data->coach = fullname($coach);
        $data->duration = $appointment->duration_minutes;
        $data->location = $appointment->location ?: '-';
        $data->topic = $appointment->topic ?: '-';

        $subject = get_string('notification_appointment_reminder', 'local_ftm_sip', $data);

        $body = '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; max-width: 600px;">';
        $body .= '<div style="background: #0891B2; color: white; padding: 16px 20px; border-radius: 8px 8px 0 0;">';
        $body .= '<h2 style="margin: 0; font-size: 18px;">&#128197; Promemoria Appuntamento SIP</h2>';
        $body .= '</div>';
        $body .= '<div style="background: white; padding: 20px; border: 1px solid #DEE2E6; border-top: none; border-radius: 0 0 8px 8px;">';
        $body .= '<p style="font-size: 15px;">Hai un appuntamento programmato:</p>';
        $body .= '<table style="width: 100%; font-size: 14px; margin: 12px 0;">';
        $body .= '<tr><td style="padding: 6px 0; color: #6B7280; width: 120px;">Data:</td><td style="font-weight: 600;">' . $data->date . '</td></tr>';
        $body .= '<tr><td style="padding: 6px 0; color: #6B7280;">Ora:</td><td style="font-weight: 600;">' . $data->time . '</td></tr>';
        $body .= '<tr><td style="padding: 6px 0; color: #6B7280;">Durata:</td><td>' . $data->duration . ' minuti</td></tr>';
        $body .= '<tr><td style="padding: 6px 0; color: #6B7280;">Coach:</td><td>' . s($data->coach) . '</td></tr>';
        if ($appointment->topic) {
            $body .= '<tr><td style="padding: 6px 0; color: #6B7280;">Argomento:</td><td>' . s($data->topic) . '</td></tr>';
        }
        if ($appointment->location) {
            $body .= '<tr><td style="padding: 6px 0; color: #6B7280;">Luogo:</td><td>' . s($data->location) . '</td></tr>';
        }
        $body .= '</table>';
        $body .= '</div></div>';

        self::send_message($coach->id, $student->id, 'appointment_reminder', $subject, $body);
    }

    /**
     * Send new appointment notification to student.
     *
     * @param object $appointment Appointment record.
     * @param object $student User record.
     * @param object $coach User record.
     */
    public static function send_appointment_created($appointment, $student, $coach) {
        $data = new \stdClass();
        $data->date = userdate($appointment->appointment_date, '%d/%m/%Y');
        $data->time = $appointment->time_start;

        $subject = 'Nuovo appuntamento SIP - ' . $data->date . ' alle ' . $data->time;

        $body = '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; max-width: 600px;">';
        $body .= '<div style="background: #059669; color: white; padding: 16px 20px; border-radius: 8px 8px 0 0;">';
        $body .= '<h2 style="margin: 0; font-size: 18px;">&#128197; Nuovo Appuntamento</h2>';
        $body .= '</div>';
        $body .= '<div style="background: white; padding: 20px; border: 1px solid #DEE2E6; border-top: none; border-radius: 0 0 8px 8px;">';
        $body .= '<p>Il tuo coach <strong>' . s(fullname($coach)) . '</strong> ha fissato un appuntamento:</p>';
        $body .= '<p style="font-size: 18px; font-weight: 700; color: #0891B2;">' . $data->date . ' alle ' . $data->time . '</p>';
        if ($appointment->topic) {
            $body .= '<p>Argomento: ' . s($appointment->topic) . '</p>';
        }
        if ($appointment->location) {
            $body .= '<p>Luogo: ' . s($appointment->location) . '</p>';
        }
        $body .= '</div></div>';

        self::send_message($coach->id, $student->id, 'appointment_created', $subject, $body);
    }

    /**
     * Send action reminder to student (actions with approaching deadlines).
     *
     * @param object $student User record.
     * @param array $actions Array of pending action records.
     * @param object $coach User record.
     */
    public static function send_action_reminder($student, $actions, $coach) {
        $count = count($actions);
        $subject = "SIP: Hai {$count} azioni da completare";

        $body = '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; max-width: 600px;">';
        $body .= '<div style="background: #F59E0B; color: white; padding: 16px 20px; border-radius: 8px 8px 0 0;">';
        $body .= '<h2 style="margin: 0; font-size: 18px;">&#9888; Promemoria Azioni SIP</h2>';
        $body .= '</div>';
        $body .= '<div style="background: white; padding: 20px; border: 1px solid #DEE2E6; border-top: none; border-radius: 0 0 8px 8px;">';
        $body .= '<p>Hai <strong>' . $count . '</strong> azioni da completare:</p>';
        $body .= '<ul style="padding-left: 20px;">';
        foreach ($actions as $act) {
            $deadline_str = $act->deadline ? ' (scadenza: ' . userdate($act->deadline, '%d/%m') . ')' : '';
            $overdue = ($act->deadline && $act->deadline < time()) ? ' style="color: #DC2626;"' : '';
            $body .= '<li' . $overdue . '>' . s($act->description) . $deadline_str . '</li>';
        }
        $body .= '</ul>';
        $body .= '</div></div>';

        self::send_message($coach->id, $student->id, 'action_reminder', $subject, $body);
    }

    /**
     * Send student inactivity alert to coach.
     *
     * @param object $coach User record.
     * @param object $student User record.
     * @param int $days_inactive Number of days since last activity.
     */
    public static function send_inactivity_alert($coach, $student, $days_inactive) {
        $student_name = fullname($student);
        $subject = "SIP: {$student_name} inattivo da {$days_inactive} giorni";

        $body = '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; max-width: 600px;">';
        $body .= '<div style="background: #DC2626; color: white; padding: 16px 20px; border-radius: 8px 8px 0 0;">';
        $body .= '<h2 style="margin: 0; font-size: 18px;">&#128680; Avviso Inattivita Studente</h2>';
        $body .= '</div>';
        $body .= '<div style="background: white; padding: 20px; border: 1px solid #DEE2E6; border-top: none; border-radius: 0 0 8px 8px;">';
        $body .= '<p><strong>' . s($student_name) . '</strong> non registra attivita da <strong>' . $days_inactive . ' giorni</strong>.</p>';
        $body .= '<p style="color: #6B7280;">Nessuna candidatura, contatto o completamento azione rilevato nel periodo.</p>';
        $body .= '<p><a href="' . (new \moodle_url('/local/ftm_sip/sip_student.php', ['userid' => $student->id]))->out(false) . '" ';
        $body .= 'style="background: #0891B2; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block;">Apri Scheda SIP</a></p>';
        $body .= '</div></div>';

        // Send to coach (from system).
        self::send_message(get_admin()->id, $coach->id, 'student_inactivity', $subject, $body);
    }

    /**
     * Send meeting not logged reminder to coach.
     *
     * @param object $coach User record.
     * @param object $appointment Appointment record.
     * @param object $student User record.
     */
    public static function send_meeting_not_logged($coach, $appointment, $student) {
        $student_name = fullname($student);
        $date = userdate($appointment->appointment_date, '%d/%m/%Y');
        $subject = "SIP: Incontro del {$date} con {$student_name} non registrato";

        $body = '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; max-width: 600px;">';
        $body .= '<div style="background: #6B7280; color: white; padding: 16px 20px; border-radius: 8px 8px 0 0;">';
        $body .= '<h2 style="margin: 0; font-size: 18px;">&#128221; Promemoria: Registra Incontro</h2>';
        $body .= '</div>';
        $body .= '<div style="background: white; padding: 20px; border: 1px solid #DEE2E6; border-top: none; border-radius: 0 0 8px 8px;">';
        $body .= '<p>L\'appuntamento del <strong>' . $date . '</strong> con <strong>' . s($student_name) . '</strong> risulta completato ma non hai ancora registrato le note dell\'incontro.</p>';
        $body .= '<p><a href="' . (new \moodle_url('/local/ftm_sip/sip_student.php', ['userid' => $student->id, 'tab' => 'diario']))->out(false) . '" ';
        $body .= 'style="background: #0891B2; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block;">Registra Incontro</a></p>';
        $body .= '</div></div>';

        self::send_message(get_admin()->id, $coach->id, 'meeting_not_logged', $subject, $body);
    }

    /**
     * Send plan update notification to student.
     *
     * @param object $student User record.
     * @param object $coach User record.
     */
    public static function send_plan_updated($student, $coach) {
        $subject = get_string('notification_plan_update', 'local_ftm_sip');

        $body = '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; max-width: 600px;">';
        $body .= '<div style="background: #0891B2; color: white; padding: 16px 20px; border-radius: 8px 8px 0 0;">';
        $body .= '<h2 style="margin: 0; font-size: 18px;">&#128221; Piano d\'Azione Aggiornato</h2>';
        $body .= '</div>';
        $body .= '<div style="background: white; padding: 20px; border: 1px solid #DEE2E6; border-top: none; border-radius: 0 0 8px 8px;">';
        $body .= '<p>Il tuo coach <strong>' . s(fullname($coach)) . '</strong> ha aggiornato il tuo Piano d\'Azione SIP.</p>';
        $body .= '<p><a href="' . (new \moodle_url('/local/ftm_sip/sip_my.php', ['section' => 'plan']))->out(false) . '" ';
        $body .= 'style="background: #0891B2; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block;">Vedi il mio Piano</a></p>';
        $body .= '</div></div>';

        self::send_message($coach->id, $student->id, 'plan_updated', $subject, $body);
    }

    /**
     * Core message sending via Moodle message API.
     *
     * @param int $fromid Sender user ID.
     * @param int $toid Recipient user ID.
     * @param string $provider Message provider name.
     * @param string $subject Subject line.
     * @param string $body HTML body.
     */
    private static function send_message($fromid, $toid, $provider, $subject, $body) {
        $message = new \core\message\message();
        $message->component = 'local_ftm_sip';
        $message->name = $provider;
        $message->userfrom = \core_user::get_user($fromid);
        $message->userto = \core_user::get_user($toid);
        $message->subject = $subject;
        $message->fullmessage = strip_tags($body);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $body;
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->contexturl = (new \moodle_url('/local/ftm_sip/sip_my.php'))->out(false);
        $message->contexturlname = get_string('sip_manager', 'local_ftm_sip');

        try {
            message_send($message);
        } catch (\Exception $e) {
            debugging('SIP notification error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // =========================================================================
    // SCHEDULED TASK METHODS (called by cron)
    // =========================================================================

    /**
     * Process all scheduled reminders. Called by scheduled task.
     */
    public static function process_reminders() {
        global $DB;

        $now = time();
        $tomorrow = $now + 86400;

        // 1. Appointment reminders (1 day before, not yet sent).
        $sql = "SELECT a.*, e.userid, e.coachid
                FROM {local_ftm_sip_appointments} a
                JOIN {local_ftm_sip_enrollments} e ON e.id = a.enrollmentid
                WHERE a.appointment_date BETWEEN :now AND :tomorrow
                AND a.status IN ('scheduled', 'confirmed')
                AND a.reminder_sent = 0";
        $appts = $DB->get_records_sql($sql, ['now' => $now, 'tomorrow' => $tomorrow]);

        foreach ($appts as $appt) {
            $student = $DB->get_record('user', ['id' => $appt->userid]);
            $coach = $DB->get_record('user', ['id' => $appt->coachid]);
            if ($student && $coach) {
                self::send_appointment_reminder($appt, $student, $coach);
                $DB->set_field('local_ftm_sip_appointments', 'reminder_sent', 1, ['id' => $appt->id]);
            }
        }

        // 2. Action deadline reminders (2 days before deadline, for active students).
        $deadline_threshold = $now + (2 * 86400);
        $sql = "SELECT act.*, e.userid, e.coachid
                FROM {local_ftm_sip_actions} act
                JOIN {local_ftm_sip_enrollments} e ON e.id = act.enrollmentid
                WHERE act.status IN ('pending', 'in_progress')
                AND act.deadline IS NOT NULL
                AND act.deadline BETWEEN :now AND :threshold
                AND e.status = 'active'
                AND e.student_visible = 1";
        $actions_due = $DB->get_records_sql($sql, ['now' => $now, 'threshold' => $deadline_threshold]);

        // Group by student.
        $by_student = [];
        foreach ($actions_due as $act) {
            $by_student[$act->userid][] = $act;
        }
        foreach ($by_student as $uid => $acts) {
            $student = $DB->get_record('user', ['id' => $uid]);
            $coach = $DB->get_record('user', ['id' => $acts[0]->coachid]);
            if ($student && $coach) {
                self::send_action_reminder($student, $acts, $coach);
            }
        }

        // 3. Student inactivity alerts (no activity for 7+ days).
        $inactivity_threshold = $now - (7 * 86400);
        $sql = "SELECT e.id, e.userid, e.coachid
                FROM {local_ftm_sip_enrollments} e
                WHERE e.status = 'active'
                AND e.student_visible = 1";
        $enrollments = $DB->get_records_sql($sql);

        foreach ($enrollments as $enr) {
            // Check last activity: application, contact, opportunity, or action completion.
            $last_activity = 0;

            $tables = [
                'local_ftm_sip_applications' => 'timecreated',
                'local_ftm_sip_contacts' => 'timecreated',
                'local_ftm_sip_opportunities' => 'timecreated',
            ];
            foreach ($tables as $table => $field) {
                $max = $DB->get_field_sql(
                    "SELECT MAX({$field}) FROM {{$table}} WHERE enrollmentid = ? AND addedby = ?",
                    [$enr->id, $enr->userid]
                );
                if ($max && $max > $last_activity) {
                    $last_activity = (int)$max;
                }
            }

            // Also check action completions by student.
            $max_action = $DB->get_field_sql(
                "SELECT MAX(completed_date) FROM {local_ftm_sip_actions}
                 WHERE enrollmentid = ? AND completed_by = ? AND status = 'completed'",
                [$enr->id, $enr->userid]
            );
            if ($max_action && $max_action > $last_activity) {
                $last_activity = (int)$max_action;
            }

            if ($last_activity > 0 && $last_activity < $inactivity_threshold) {
                $days = floor(($now - $last_activity) / 86400);
                // Only send once per week (check if we sent one in last 6 days).
                $recent = $DB->get_record_sql(
                    "SELECT id FROM {notifications}
                     WHERE useridto = ? AND component = 'local_ftm_sip'
                     AND eventtype = 'student_inactivity' AND timecreated > ?
                     LIMIT 1",
                    [$enr->coachid, $now - (6 * 86400)]
                );
                if (!$recent) {
                    $student = $DB->get_record('user', ['id' => $enr->userid]);
                    $coach = $DB->get_record('user', ['id' => $enr->coachid]);
                    if ($student && $coach) {
                        self::send_inactivity_alert($coach, $student, $days);
                    }
                }
            }
        }

        // 4. Meeting not logged reminders (appointment completed 24h ago, no meeting linked).
        $completed_threshold = $now - 86400;
        $sql = "SELECT a.*, e.userid, e.coachid
                FROM {local_ftm_sip_appointments} a
                JOIN {local_ftm_sip_enrollments} e ON e.id = a.enrollmentid
                WHERE a.status = 'completed'
                AND a.meetingid IS NULL
                AND a.reminder_coach_sent = 0
                AND a.timemodified < :threshold";
        $unlogged = $DB->get_records_sql($sql, ['threshold' => $completed_threshold]);

        foreach ($unlogged as $appt) {
            $student = $DB->get_record('user', ['id' => $appt->userid]);
            $coach = $DB->get_record('user', ['id' => $appt->coachid]);
            if ($student && $coach) {
                self::send_meeting_not_logged($coach, $appt, $student);
                $DB->set_field('local_ftm_sip_appointments', 'reminder_coach_sent', 1, ['id' => $appt->id]);
            }
        }

        // 5. Weekly meeting frequency alert (no meeting this week, Thursday or later).
        $day_of_week = (int) date('N'); // 1=Monday .. 7=Sunday.
        if ($day_of_week >= 4) {
            // Calculate current week boundaries (Monday 00:00 to Sunday 23:59).
            $monday = strtotime('monday this week', $now);
            $sunday = strtotime('sunday this week 23:59:59', $now);

            $sql = "SELECT e.id, e.userid, e.coachid
                    FROM {local_ftm_sip_enrollments} e
                    WHERE e.status = 'active'
                    AND e.date_start > 0";
            $active_enrollments = $DB->get_records_sql($sql);

            foreach ($active_enrollments as $aenr) {
                // Count meetings this week.
                $meeting_count = $DB->count_records_select(
                    'local_ftm_sip_meetings',
                    'enrollmentid = :eid AND meeting_date >= :monday AND meeting_date <= :sunday',
                    ['eid' => $aenr->id, 'monday' => $monday, 'sunday' => $sunday]
                );

                if ($meeting_count == 0) {
                    // Check if we already sent this alert this week.
                    $recent_alert = $DB->get_record_sql(
                        "SELECT id FROM {notifications}
                         WHERE useridto = :coachid
                         AND component = 'local_ftm_sip'
                         AND eventtype = 'meeting_frequency_alert'
                         AND timecreated >= :monday
                         LIMIT 1",
                        ['coachid' => $aenr->coachid, 'monday' => $monday]
                    );

                    if (!$recent_alert) {
                        $student = $DB->get_record('user', ['id' => $aenr->userid]);
                        $coach = $DB->get_record('user', ['id' => $aenr->coachid]);
                        if ($student && $coach) {
                            self::send_meeting_frequency_alert($coach, $student);
                        }
                    }
                }
            }
        }
    }

    /**
     * Send weekly meeting frequency alert to coach.
     *
     * Notifies coach when no meeting has been logged for an active SIP student
     * in the current week (sent on Thursday or later).
     *
     * @param object $coach User record.
     * @param object $student User record.
     */
    public static function send_meeting_frequency_alert($coach, $student) {
        $student_name = fullname($student);
        $week_label = date('W/Y');
        $subject = "SIP: Nessun incontro registrato questa settimana con {$student_name}";

        $body = '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; max-width: 600px;">';
        $body .= '<div style="background: #F59E0B; color: white; padding: 16px 20px; border-radius: 8px 8px 0 0;">';
        $body .= '<h2 style="margin: 0; font-size: 18px;">&#9888; Frequenza Incontri SIP</h2>';
        $body .= '</div>';
        $body .= '<div style="background: white; padding: 20px; border: 1px solid #DEE2E6; border-top: none; border-radius: 0 0 8px 8px;">';
        $body .= '<p>Nessun incontro registrato questa settimana (KW ' . s($week_label) . ') per lo studente <strong>' . s($student_name) . '</strong>.</p>';
        $body .= '<p style="color: #6B7280;">Si raccomanda almeno un incontro a settimana per ogni studente SIP attivo.</p>';
        $body .= '<p><a href="' . (new \moodle_url('/local/ftm_sip/sip_student.php', ['userid' => $student->id, 'tab' => 'diario']))->out(false) . '" ';
        $body .= 'style="background: #0891B2; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block;">Registra Incontro</a></p>';
        $body .= '</div></div>';

        self::send_message(get_admin()->id, $coach->id, 'meeting_frequency_alert', $subject, $body);
    }
}
