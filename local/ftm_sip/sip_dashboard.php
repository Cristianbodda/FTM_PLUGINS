<?php
/**
 * SIP Dashboard - Coach/Secretary overview of all SIP students.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/sip_manager.php');

use local_ftm_sip\sip_manager;

require_login();

$context = context_system::instance();
require_capability('local/ftm_sip:view', $context);

$statusfilter = optional_param('status', '', PARAM_ALPHANUMEXT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_sip/sip_dashboard.php'));
$PAGE->set_title(get_string('dashboard_title', 'local_ftm_sip'));
$PAGE->set_heading(get_string('sip_manager', 'local_ftm_sip'));

// Data.
$stats = sip_manager::get_stats();
$is_manager = has_capability('local/ftm_sip:manage', $context);

// Get enrollments based on filter or all.
if ($is_manager) {
    $enrollments = sip_manager::get_all_enrollments($statusfilter ?: null);
} else {
    $enrollments = sip_manager::get_coach_enrollments($USER->id);
}

// Upcoming appointments for current user.
$my_appointments = sip_manager::get_coach_appointments($USER->id, 7);

echo $OUTPUT->header();
?>

<style>
.sip-dash { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 1200px; margin: 0 auto; }
.sip-dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.sip-dash-header h2 { margin: 0; font-size: 24px; color: #1A1A2E; }
.sip-dash-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 24px; }
.sip-dash-stat { background: white; border-radius: 8px; padding: 18px; text-align: center; border: 1px solid #DEE2E6; border-top: 4px solid #0891B2; }
.sip-dash-stat.green { border-top-color: #059669; }
.sip-dash-stat.gray { border-top-color: #6B7280; }
.sip-dash-stat.yellow { border-top-color: #F59E0B; }
.sip-dash-stat-val { font-size: 30px; font-weight: 700; color: #1A1A2E; }
.sip-dash-stat-label { font-size: 11px; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }

.sip-dash-filters { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.sip-dash-filter { padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 500; cursor: pointer; border: 1px solid #DEE2E6; background: white; color: #374151; text-decoration: none; }
.sip-dash-filter:hover { border-color: #0891B2; color: #0891B2; text-decoration: none; }
.sip-dash-filter.active { background: #0891B2; color: white; border-color: #0891B2; }

.sip-dash-appts { background: white; border-radius: 8px; padding: 18px; margin-bottom: 20px; border: 1px solid #DEE2E6; border-left: 4px solid #059669; }
.sip-dash-appts h3 { margin: 0 0 12px; font-size: 15px; color: #059669; }

.sip-dash-table { background: white; border-radius: 8px; border: 1px solid #DEE2E6; overflow: hidden; }
.sip-dash-table table { width: 100%; border-collapse: collapse; font-size: 13px; }
.sip-dash-table th { padding: 10px 14px; text-align: left; font-size: 11px; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; background: #F8F9FA; border-bottom: 2px solid #DEE2E6; }
.sip-dash-table td { padding: 10px 14px; border-bottom: 1px solid #F3F4F6; vertical-align: middle; }
.sip-dash-table tr:hover { background: #F8FFFE; }
.sip-dash-table .student-name { font-weight: 600; color: #1A1A2E; }
.sip-dash-table .student-email { font-size: 11px; color: #9CA3AF; }

.sip-status-badge { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; }
.sip-status-active { background: #D1FAE5; color: #065F46; }
.sip-status-completed { background: #DBEAFE; color: #1E40AF; }
.sip-status-suspended { background: #FEF3C7; color: #92400E; }
.sip-status-cancelled { background: #FEE2E2; color: #991B1B; }

.sip-week-badge { background: #ECFEFF; color: #155E75; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.sip-kpi-mini { font-size: 11px; color: #6B7280; }
.sip-kpi-mini strong { color: #1A1A2E; }

.sip-dash-links { display: flex; gap: 10px; margin-bottom: 20px; }
.sip-dash-link { padding: 8px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none; }
.sip-dash-link:hover { text-decoration: none; opacity: 0.9; }

@media (max-width: 768px) {
    .sip-dash-stats { grid-template-columns: repeat(2, 1fr); }
    .sip-dash-table { overflow-x: auto; }
}
</style>

<div class="sip-dash">

<!-- Header -->
<div class="sip-dash-header">
    <h2><span style="color:#0891B2;">&#128221;</span> <?php echo get_string('dashboard_title', 'local_ftm_sip'); ?></h2>
    <div class="sip-dash-links">
        <a href="<?php echo $CFG->wwwroot; ?>/local/ftm_sip/companies.php" class="sip-dash-link" style="background:#F3F4F6;color:#374151;">
            <i class="fa fa-building"></i> <?php echo get_string('company_registry', 'local_ftm_sip'); ?>
        </a>
        <a href="<?php echo $CFG->wwwroot; ?>/local/coachmanager/coach_dashboard_v2.php" class="sip-dash-link" style="background:#0891B2;color:white;">
            &#8592; Coach Dashboard
        </a>
    </div>
</div>

<!-- Stats -->
<div class="sip-dash-stats">
    <div class="sip-dash-stat">
        <div class="sip-dash-stat-val"><?php echo $stats->active; ?></div>
        <div class="sip-dash-stat-label"><?php echo get_string('active_enrollments', 'local_ftm_sip'); ?></div>
    </div>
    <div class="sip-dash-stat green">
        <div class="sip-dash-stat-val"><?php echo $stats->completed; ?></div>
        <div class="sip-dash-stat-label"><?php echo get_string('completed_enrollments', 'local_ftm_sip'); ?></div>
    </div>
    <div class="sip-dash-stat gray">
        <div class="sip-dash-stat-val"><?php echo $stats->draft; ?></div>
        <div class="sip-dash-stat-label">Bozze Piano</div>
    </div>
    <div class="sip-dash-stat yellow">
        <div class="sip-dash-stat-val"><?php echo $stats->upcoming_appointments; ?></div>
        <div class="sip-dash-stat-label"><?php echo get_string('upcoming_appointments', 'local_ftm_sip'); ?></div>
    </div>
</div>

<!-- My upcoming appointments -->
<?php if (!empty($my_appointments)): ?>
<div class="sip-dash-appts">
    <h3><i class="fa fa-calendar-check-o"></i> I Miei Prossimi Appuntamenti (7 giorni)</h3>
    <?php foreach ($my_appointments as $appt): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:6px 0;border-bottom:1px solid #E5E7EB;">
        <span style="font-weight:600;color:#0891B2;min-width:70px;"><?php echo userdate($appt->appointment_date, '%d/%m'); ?> <?php echo s($appt->time_start); ?></span>
        <span style="font-weight:500;"><?php echo fullname($appt); ?></span>
        <?php if (isset($appt->topic) && $appt->topic): ?>
        <span style="color:#6B7280;font-size:12px;">- <?php echo s($appt->topic); ?></span>
        <?php endif; ?>
        <a href="<?php echo $CFG->wwwroot; ?>/local/ftm_sip/sip_student.php?userid=<?php echo $appt->studentid; ?>&tab=calendario" style="margin-left:auto;font-size:12px;color:#0891B2;">Apri &#8594;</a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="sip-dash-filters">
    <a href="?status=" class="sip-dash-filter <?php echo !$statusfilter ? 'active' : ''; ?>">Tutti (<?php echo $stats->total; ?>)</a>
    <a href="?status=active" class="sip-dash-filter <?php echo $statusfilter === 'active' ? 'active' : ''; ?>">Attivi (<?php echo $stats->active; ?>)</a>
    <a href="?status=completed" class="sip-dash-filter <?php echo $statusfilter === 'completed' ? 'active' : ''; ?>">Completati (<?php echo $stats->completed; ?>)</a>
    <a href="?status=suspended" class="sip-dash-filter <?php echo $statusfilter === 'suspended' ? 'active' : ''; ?>">Sospesi</a>
</div>

<!-- Student table -->
<div class="sip-dash-table">
    <table>
        <thead>
            <tr>
                <th>Studente</th>
                <th>Coach</th>
                <th>Settore</th>
                <th>Settimana</th>
                <th>Fase</th>
                <th>Stato</th>
                <th>KPI</th>
                <th>Azioni</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($enrollments)): ?>
            <tr><td colspan="8" style="text-align:center;padding:30px;color:#6B7280;">Nessuno studente SIP trovato</td></tr>
        <?php else: ?>
        <?php foreach ($enrollments as $enr):
            $sip_week = ($enr->date_start > 0) ? local_ftm_sip_calculate_week($enr->date_start) : 0;
            $sip_phase = ($sip_week > 0) ? local_ftm_sip_get_phase($sip_week) : 0;
            $enr_kpi = sip_manager::get_kpi_summary($enr->id);
            $status_class = 'sip-status-' . $enr->status;
            $status_label = get_string('status_' . $enr->status, 'local_ftm_sip');
            $phase_name = ($sip_phase > 0) ? get_string('phase_' . $sip_phase, 'local_ftm_sip') : '-';
        ?>
        <tr>
            <td>
                <div class="student-name"><?php echo fullname($enr); ?></div>
                <div class="student-email"><?php echo s($enr->email); ?></div>
            </td>
            <td style="font-size:12px;">
                <?php
                if (isset($enr->coach_firstname)) {
                    echo s($enr->coach_firstname . ' ' . $enr->coach_lastname);
                } else {
                    $c = $DB->get_record('user', ['id' => $enr->coachid]);
                    echo $c ? s(fullname($c)) : '-';
                }
                ?>
            </td>
            <td><span style="background:#F3F4F6;padding:2px 8px;border-radius:4px;font-size:11px;"><?php echo s($enr->sector ?: '-'); ?></span></td>
            <td>
                <?php if ($sip_week > 0): ?>
                <span class="sip-week-badge">S.<?php echo min($sip_week, 10); ?>/10</span>
                <?php else: ?>
                <span style="color:#9CA3AF;">-</span>
                <?php endif; ?>
            </td>
            <td style="font-size:12px;"><?php echo s($phase_name); ?></td>
            <td><span class="sip-status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span></td>
            <td class="sip-kpi-mini">
                <strong><?php echo $enr_kpi->applications_total; ?></strong> cand.
                <strong><?php echo $enr_kpi->contacts_total; ?></strong> cont.
                <strong><?php echo $enr_kpi->opportunities_total; ?></strong> opp.
            </td>
            <td>
                <a href="<?php echo $CFG->wwwroot; ?>/local/ftm_sip/sip_student.php?userid=<?php echo $enr->userid; ?>"
                   style="background:#0891B2;color:white;padding:4px 12px;border-radius:4px;font-size:12px;font-weight:600;text-decoration:none;display:inline-block;">
                    Apri
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</div><!-- /sip-dash -->

<?php
echo $OUTPUT->footer();
