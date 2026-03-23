<?php
/**
 * SIP Student self-service area - the PCI sees their own SIP journey.
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
require_capability('local/ftm_sip:viewown', $context);

$userid = $USER->id;

// Get enrollment - must exist and be visible.
$enrollment = sip_manager::get_enrollment($userid);
if (!$enrollment || !$enrollment->student_visible) {
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/local/ftm_sip/sip_my.php'));
    $PAGE->set_title(get_string('student_area_title', 'local_ftm_sip'));
    $PAGE->set_heading(get_string('sip_manager', 'local_ftm_sip'));
    echo $OUTPUT->header();
    echo '<div style="text-align:center;padding:60px 20px;">';
    echo '<div style="font-size:48px;margin-bottom:12px;">&#128274;</div>';
    echo '<h3 style="color:#6B7280;">' . get_string('error_permission', 'local_ftm_sip') . '</h3>';
    echo '<p style="color:#9CA3AF;">Il tuo coach non ha ancora abilitato l\'accesso alla tua area SIP.</p>';
    echo '</div>';
    echo $OUTPUT->footer();
    die();
}

$section = optional_param('section', 'overview', PARAM_ALPHANUMEXT);

// Fetch data.
$current_week = ($enrollment->date_start > 0) ? local_ftm_sip_calculate_week($enrollment->date_start) : 0;
$current_phase = ($current_week > 0) ? local_ftm_sip_get_phase($current_week) : 0;
$kpi = sip_manager::get_kpi_summary($enrollment->id);
$next_appt = sip_manager::get_next_appointment($userid);
$pending_actions = sip_manager::get_pending_actions($enrollment->id);
$action_plan = sip_manager::get_action_plan($enrollment->id);
$areas_def = local_ftm_sip_get_activation_areas();
$scale = local_ftm_sip_get_activation_scale();
$phases_def = local_ftm_sip_get_phases();

// Coach name.
$coach = $DB->get_record('user', ['id' => $enrollment->coachid, 'deleted' => 0]);
$coach_name = $coach ? fullname($coach) : '-';

// Recent applications, contacts, opportunities (last 10 each).
$recent_apps = $DB->get_records('local_ftm_sip_applications', ['enrollmentid' => $enrollment->id], 'application_date DESC', '*', 0, 10);
$recent_contacts = $DB->get_records('local_ftm_sip_contacts', ['enrollmentid' => $enrollment->id], 'contact_date DESC', '*', 0, 10);
$recent_opps = $DB->get_records('local_ftm_sip_opportunities', ['enrollmentid' => $enrollment->id], 'opportunity_date DESC', '*', 0, 10);

// Upcoming appointments.
$appointments = $DB->get_records_select('local_ftm_sip_appointments',
    'enrollmentid = :eid AND appointment_date >= :now AND status IN (:s1, :s2)',
    ['eid' => $enrollment->id, 'now' => time(), 's1' => 'scheduled', 's2' => 'confirmed'],
    'appointment_date ASC, time_start ASC', '*', 0, 5);

$week_display = min($current_week, LOCAL_FTM_SIP_TOTAL_WEEKS);
$week_pct = ($current_week > 0) ? round(($week_display / LOCAL_FTM_SIP_TOTAL_WEEKS) * 100) : 0;

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_sip/sip_my.php'));
$PAGE->set_title(get_string('student_area_title', 'local_ftm_sip'));
$PAGE->set_heading(get_string('sip_manager', 'local_ftm_sip'));

echo $OUTPUT->header();
?>

<style>
.sip-my { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 900px; margin: 0 auto; }
.sip-my-header { background: linear-gradient(135deg, #0891B2, #06B6D4); border-radius: 12px; padding: 28px; color: white; margin-bottom: 20px; }
.sip-my-header h2 { margin: 0 0 4px; font-size: 22px; }
.sip-my-header .sub { opacity: 0.85; font-size: 13px; }
.sip-my-progress { background: rgba(255,255,255,0.25); border-radius: 10px; height: 10px; margin-top: 16px; overflow: hidden; }
.sip-my-progress-fill { background: white; height: 100%; border-radius: 10px; }
.sip-my-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-top: 16px; }
.sip-my-stat { background: rgba(255,255,255,0.15); border-radius: 8px; padding: 12px; text-align: center; }
.sip-my-stat-val { font-size: 22px; font-weight: 700; }
.sip-my-stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.85; }

.sip-my-sections { display: flex; gap: 0; border-bottom: 2px solid #DEE2E6; margin-bottom: 20px; overflow-x: auto; }
.sip-my-sec { padding: 10px 18px; font-size: 13px; font-weight: 500; color: #6B7280; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; white-space: nowrap; text-decoration: none; }
.sip-my-sec:hover { color: #0891B2; text-decoration: none; }
.sip-my-sec.active { color: #0891B2; border-bottom-color: #0891B2; font-weight: 600; }

.sip-my-card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 16px; border: 1px solid #DEE2E6; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.sip-my-card h3 { margin: 0 0 12px; font-size: 16px; color: #1A1A2E; }

.sip-my-action { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #F3F4F6; }
.sip-my-action:last-child { border-bottom: none; }
.sip-my-action-check { width: 20px; height: 20px; accent-color: #0891B2; cursor: pointer; }
.sip-my-action-text { flex: 1; font-size: 14px; color: #374151; }
.sip-my-action-deadline { font-size: 11px; padding: 2px 8px; border-radius: 10px; }
.deadline-overdue { background: #FEE2E2; color: #DC2626; }
.deadline-soon { background: #FEF3C7; color: #92400E; }
.deadline-ok { background: #F3F4F6; color: #6B7280; }

.sip-my-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
.sip-my-form-row.full { grid-template-columns: 1fr; }
.sip-my-input { width: 100%; border: 1px solid #DEE2E6; border-radius: 6px; padding: 8px 10px; font-size: 13px; font-family: inherit; }
.sip-my-input:focus { border-color: #0891B2; outline: none; box-shadow: 0 0 0 2px rgba(8,145,178,0.15); }
.sip-my-label { font-size: 12px; font-weight: 600; color: #495057; display: block; margin-bottom: 4px; }
.sip-my-btn { background: #0891B2; color: white; border: none; padding: 8px 20px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }
.sip-my-btn:hover { background: #0E7490; }

.sip-my-appt { display: flex; align-items: center; gap: 14px; padding: 12px; background: #F8FFFE; border: 1px solid #A7F3D0; border-radius: 8px; margin-bottom: 8px; }
.sip-my-appt-date { text-align: center; min-width: 50px; }
.sip-my-appt-date .day { font-size: 22px; font-weight: 700; color: #0891B2; }
.sip-my-appt-date .month { font-size: 11px; color: #6B7280; }

.sip-my-radar { text-align: center; }

.area-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.area-bar-name { width: 140px; font-size: 12px; font-weight: 500; color: #374151; text-align: right; }
.area-bar-track { flex: 1; background: #E5E7EB; border-radius: 6px; height: 20px; position: relative; overflow: hidden; }
.area-bar-fill-initial { position: absolute; top: 0; left: 0; height: 100%; background: #D1D5DB; border-radius: 6px; }
.area-bar-fill-current { position: absolute; top: 0; left: 0; height: 100%; background: #0891B2; border-radius: 6px; }
.area-bar-label { width: 40px; font-size: 12px; font-weight: 600; color: #0891B2; }

@media (max-width: 768px) {
    .sip-my-stats { grid-template-columns: repeat(2, 1fr); }
    .sip-my-form-row { grid-template-columns: 1fr; }
    .area-bar-name { width: 100px; font-size: 11px; }
}
</style>

<div class="sip-my">

<!-- HEADER -->
<div class="sip-my-header">
    <h2><?php echo get_string('student_area_title', 'local_ftm_sip'); ?></h2>
    <div class="sub">Coach: <?php echo s($coach_name); ?> &middot; <?php echo get_string('week_of_10', 'local_ftm_sip', (object)['current' => $week_display, 'total' => 10]); ?>
        <?php if ($current_phase > 0): ?> &middot; <?php echo get_string('phase_' . $current_phase, 'local_ftm_sip'); ?><?php endif; ?>
    </div>
    <div class="sip-my-progress"><div class="sip-my-progress-fill" style="width:<?php echo $week_pct; ?>%;"></div></div>
    <div class="sip-my-stats">
        <div class="sip-my-stat">
            <div class="sip-my-stat-val"><?php echo $week_display; ?>/10</div>
            <div class="sip-my-stat-label"><?php echo get_string('current_week', 'local_ftm_sip'); ?></div>
        </div>
        <div class="sip-my-stat">
            <div class="sip-my-stat-val"><?php echo count($pending_actions); ?></div>
            <div class="sip-my-stat-label">Azioni da fare</div>
        </div>
        <div class="sip-my-stat">
            <div class="sip-my-stat-val"><?php echo $kpi->applications_total; ?></div>
            <div class="sip-my-stat-label"><?php echo get_string('kpi_applications', 'local_ftm_sip'); ?></div>
        </div>
        <div class="sip-my-stat">
            <div class="sip-my-stat-val"><?php echo $kpi->contacts_total; ?></div>
            <div class="sip-my-stat-label"><?php echo get_string('kpi_company_contacts', 'local_ftm_sip'); ?></div>
        </div>
        <div class="sip-my-stat">
            <div class="sip-my-stat-val"><?php echo $kpi->opportunities_total; ?></div>
            <div class="sip-my-stat-label"><?php echo get_string('kpi_opportunities', 'local_ftm_sip'); ?></div>
        </div>
    </div>
</div>

<!-- SECTIONS -->
<div class="sip-my-sections">
    <a href="?section=overview" class="sip-my-sec <?php echo $section === 'overview' ? 'active' : ''; ?>"><i class="fa fa-home"></i> Panoramica</a>
    <a href="?section=actions" class="sip-my-sec <?php echo $section === 'actions' ? 'active' : ''; ?>"><i class="fa fa-check-square-o"></i> Le Mie Azioni</a>
    <a href="?section=search" class="sip-my-sec <?php echo $section === 'search' ? 'active' : ''; ?>"><i class="fa fa-paper-plane"></i> Ricerca Lavoro</a>
    <a href="?section=plan" class="sip-my-sec <?php echo $section === 'plan' ? 'active' : ''; ?>"><i class="fa fa-bar-chart"></i> <?php echo get_string('my_progress', 'local_ftm_sip'); ?></a>
</div>

<?php if ($section === 'overview'): ?>
<!-- ==================== PANORAMICA ==================== -->

<!-- Next appointment -->
<?php if ($next_appt): ?>
<div class="sip-my-card" style="border-left: 4px solid #059669;">
    <h3><i class="fa fa-calendar-check-o"></i> <?php echo get_string('next_appointment', 'local_ftm_sip'); ?></h3>
    <div class="sip-my-appt">
        <div class="sip-my-appt-date">
            <div class="day"><?php echo userdate($next_appt->appointment_date, '%d'); ?></div>
            <div class="month"><?php echo userdate($next_appt->appointment_date, '%b %Y'); ?></div>
        </div>
        <div>
            <div style="font-weight:600;font-size:15px;"><?php echo s($next_appt->time_start); ?> &middot; <?php echo $next_appt->duration_minutes; ?> min</div>
            <?php if (isset($next_appt->topic) && $next_appt->topic): ?>
            <div style="font-size:13px;color:#374151;margin-top:2px;"><?php echo s($next_appt->topic); ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Upcoming appointments -->
<?php if (!empty($appointments) && count($appointments) > 1): ?>
<div class="sip-my-card">
    <h3><i class="fa fa-calendar"></i> <?php echo get_string('my_appointments', 'local_ftm_sip'); ?></h3>
    <?php foreach ($appointments as $appt): if ($next_appt && $appt->id == $next_appt->id) continue; ?>
    <div style="display:flex;align-items:center;gap:10px;padding:6px 0;border-bottom:1px solid #F3F4F6;font-size:13px;">
        <span style="font-weight:600;color:#0891B2;"><?php echo userdate($appt->appointment_date, '%d/%m'); ?></span>
        <span><?php echo s($appt->time_start); ?></span>
        <?php if ($appt->topic): ?><span style="color:#6B7280;"><?php echo s($appt->topic); ?></span><?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Pending actions -->
<?php if (!empty($pending_actions)): ?>
<div class="sip-my-card" style="border-left: 4px solid #F59E0B;">
    <h3><i class="fa fa-tasks"></i> Azioni da Completare (<?php echo count($pending_actions); ?>)</h3>
    <?php foreach ($pending_actions as $act):
        $dl_class = 'deadline-ok';
        $dl_text = '';
        if ($act->deadline) {
            $dl_text = userdate($act->deadline, '%d/%m');
            if ($act->deadline < time()) $dl_class = 'deadline-overdue';
            elseif ($act->deadline < time() + 3*86400) $dl_class = 'deadline-soon';
        }
    ?>
    <div class="sip-my-action">
        <input type="checkbox" class="sip-my-action-check" onchange="completeAction(<?php echo $act->id; ?>, this)">
        <span class="sip-my-action-text"><?php echo s($act->description); ?></span>
        <?php if ($dl_text): ?>
        <span class="sip-my-action-deadline <?php echo $dl_class; ?>"><?php echo $dl_text; ?></span>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($section === 'actions'): ?>
<!-- ==================== LE MIE AZIONI ==================== -->

<div class="sip-my-card">
    <h3><i class="fa fa-check-square-o"></i> Azioni In Sospeso</h3>
    <?php if (empty($pending_actions)): ?>
    <p style="color:#6B7280;text-align:center;padding:20px;"><?php echo get_string('no_pending_actions', 'local_ftm_sip'); ?></p>
    <?php else: ?>
    <?php foreach ($pending_actions as $act):
        $dl_class = 'deadline-ok'; $dl_text = '';
        if ($act->deadline) { $dl_text = userdate($act->deadline, '%d/%m');
            if ($act->deadline < time()) $dl_class = 'deadline-overdue';
            elseif ($act->deadline < time() + 3*86400) $dl_class = 'deadline-soon'; }
    ?>
    <div class="sip-my-action" id="action-<?php echo $act->id; ?>">
        <input type="checkbox" class="sip-my-action-check" onchange="completeAction(<?php echo $act->id; ?>, this)">
        <span class="sip-my-action-text"><?php echo s($act->description); ?></span>
        <?php if ($dl_text): ?><span class="sip-my-action-deadline <?php echo $dl_class; ?>"><?php echo $dl_text; ?></span><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- All completed actions -->
<?php
$completed_actions = $DB->get_records_select('local_ftm_sip_actions',
    'enrollmentid = :eid AND status IN (:s1, :s2)',
    ['eid' => $enrollment->id, 's1' => 'completed', 's2' => 'not_done'],
    'timemodified DESC', '*', 0, 20);
if (!empty($completed_actions)): ?>
<div class="sip-my-card" style="opacity:0.8;">
    <h3 style="color:#6B7280;"><i class="fa fa-check"></i> Azioni Completate</h3>
    <?php foreach ($completed_actions as $act):
        $icon = $act->status === 'completed' ? '&#9745;' : '&#10060;';
        $color = $act->status === 'completed' ? '#059669' : '#DC2626';
    ?>
    <div style="display:flex;align-items:center;gap:8px;padding:4px 0;font-size:13px;color:<?php echo $color; ?>;">
        <span><?php echo $icon; ?></span>
        <span style="text-decoration:line-through;color:#6B7280;"><?php echo s($act->description); ?></span>
        <span style="font-size:11px;color:#9CA3AF;margin-left:auto;"><?php echo userdate($act->completed_date ?: $act->timemodified, '%d/%m'); ?></span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($section === 'search'): ?>
<!-- ==================== RICERCA LAVORO ==================== -->

<!-- Quick add forms -->
<div class="sip-my-card" style="border-left: 4px solid #0891B2;">
    <h3><i class="fa fa-paper-plane"></i> Registra Candidatura</h3>
    <div class="sip-my-form-row">
        <div><label class="sip-my-label"><?php echo get_string('company_name', 'local_ftm_sip'); ?> *</label>
            <input type="text" id="app-company" class="sip-my-input" placeholder="Nome azienda..."></div>
        <div><label class="sip-my-label">Posizione</label>
            <input type="text" id="app-position" class="sip-my-input" placeholder="Ruolo..."></div>
    </div>
    <div class="sip-my-form-row">
        <div><label class="sip-my-label">Data</label>
            <input type="date" id="app-date" class="sip-my-input" value="<?php echo date('Y-m-d'); ?>"></div>
        <div><label class="sip-my-label">Tipo</label>
            <select id="app-type" class="sip-my-input">
                <option value="targeted">Candidatura mirata</option>
                <option value="unsolicited">Autocandidatura</option>
            </select></div>
    </div>
    <div class="sip-my-form-row full">
        <div><label class="sip-my-label">Note</label>
            <input type="text" id="app-notes" class="sip-my-input" placeholder="Note opzionali..."></div>
    </div>
    <button class="sip-my-btn" onclick="addApplication()"><i class="fa fa-plus"></i> Registra</button>
</div>

<div class="sip-my-card" style="border-left: 4px solid #7C3AED;">
    <h3><i class="fa fa-phone"></i> Registra Contatto Azienda</h3>
    <div class="sip-my-form-row">
        <div><label class="sip-my-label"><?php echo get_string('company_name', 'local_ftm_sip'); ?> *</label>
            <input type="text" id="cnt-company" class="sip-my-input" placeholder="Nome azienda..."></div>
        <div><label class="sip-my-label">Tipo Contatto</label>
            <select id="cnt-type" class="sip-my-input">
                <option value="phone"><?php echo get_string('meeting_modality_phone', 'local_ftm_sip'); ?></option>
                <option value="visit">Visita</option>
                <option value="email">Email</option>
                <option value="linkedin">LinkedIn</option>
                <option value="network">Rete personale</option>
            </select></div>
    </div>
    <div class="sip-my-form-row">
        <div><label class="sip-my-label">Data</label>
            <input type="date" id="cnt-date" class="sip-my-input" value="<?php echo date('Y-m-d'); ?>"></div>
        <div><label class="sip-my-label">Persona contattata</label>
            <input type="text" id="cnt-person" class="sip-my-input" placeholder="Nome..."></div>
    </div>
    <button class="sip-my-btn" style="background:#7C3AED;" onclick="addContact()"><i class="fa fa-plus"></i> Registra</button>
</div>

<div class="sip-my-card" style="border-left: 4px solid #059669;">
    <h3><i class="fa fa-star"></i> Registra Opportunita</h3>
    <div class="sip-my-form-row">
        <div><label class="sip-my-label"><?php echo get_string('company_name', 'local_ftm_sip'); ?> *</label>
            <input type="text" id="opp-company" class="sip-my-input" placeholder="Nome azienda..."></div>
        <div><label class="sip-my-label">Tipo</label>
            <select id="opp-type" class="sip-my-input">
                <option value="interview">Colloquio</option>
                <option value="trial_day">Giorno di prova</option>
                <option value="stage">Stage</option>
                <option value="intermediate_earning">Guadagno intermedio</option>
                <option value="training">Formazione</option>
            </select></div>
    </div>
    <div class="sip-my-form-row">
        <div><label class="sip-my-label">Data</label>
            <input type="date" id="opp-date" class="sip-my-input" value="<?php echo date('Y-m-d'); ?>"></div>
        <div><label class="sip-my-label">Note</label>
            <input type="text" id="opp-notes" class="sip-my-input" placeholder="Dettagli..."></div>
    </div>
    <button class="sip-my-btn" style="background:#059669;" onclick="addOpportunity()"><i class="fa fa-plus"></i> Registra</button>
</div>

<!-- Recent entries -->
<?php if (!empty($recent_apps) || !empty($recent_contacts) || !empty($recent_opps)): ?>
<div class="sip-my-card">
    <h3><i class="fa fa-history"></i> Attivita Recenti</h3>
    <?php foreach ($recent_apps as $app): ?>
    <div style="display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:1px solid #F3F4F6;font-size:13px;">
        <span style="background:#DBEAFE;color:#2563EB;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:600;">CAND</span>
        <span style="color:#374151;"><?php echo s($app->company_name); ?></span>
        <?php if ($app->position): ?><span style="color:#6B7280;">- <?php echo s($app->position); ?></span><?php endif; ?>
        <span style="margin-left:auto;color:#9CA3AF;font-size:11px;"><?php echo userdate($app->application_date, '%d/%m'); ?></span>
    </div>
    <?php endforeach; ?>
    <?php foreach ($recent_contacts as $cnt): ?>
    <div style="display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:1px solid #F3F4F6;font-size:13px;">
        <span style="background:#EDE9FE;color:#7C3AED;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:600;">CONT</span>
        <span style="color:#374151;"><?php echo s($cnt->company_name); ?></span>
        <span style="color:#6B7280;">(<?php echo s($cnt->contact_type); ?>)</span>
        <span style="margin-left:auto;color:#9CA3AF;font-size:11px;"><?php echo userdate($cnt->contact_date, '%d/%m'); ?></span>
    </div>
    <?php endforeach; ?>
    <?php foreach ($recent_opps as $opp): ?>
    <div style="display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:1px solid #F3F4F6;font-size:13px;">
        <span style="background:#D1FAE5;color:#059669;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:600;">OPP</span>
        <span style="color:#374151;"><?php echo s($opp->company_name); ?></span>
        <span style="color:#6B7280;">(<?php echo s($opp->opportunity_type); ?>)</span>
        <span style="margin-left:auto;color:#9CA3AF;font-size:11px;"><?php echo userdate($opp->opportunity_date, '%d/%m'); ?></span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($section === 'plan'): ?>
<!-- ==================== I MIEI PROGRESSI ==================== -->

<div class="sip-my-card">
    <h3><i class="fa fa-bar-chart"></i> <?php echo get_string('report_area_radar', 'local_ftm_sip'); ?></h3>
    <!-- Horizontal bar chart for activation levels -->
    <?php foreach ($areas_def as $area_key => $area_def):
        $plan = $action_plan[$area_key] ?? null;
        $li = ($plan && $plan->level_initial !== null) ? (int)$plan->level_initial : 0;
        $lc = ($plan && $plan->level_current !== null) ? (int)$plan->level_current : 0;
        $wi = ($li / 6) * 100;
        $wc = ($lc / 6) * 100;
    ?>
    <div class="area-bar">
        <div class="area-bar-name"><?php echo get_string($area_def['name'], 'local_ftm_sip'); ?></div>
        <div class="area-bar-track">
            <div class="area-bar-fill-initial" style="width:<?php echo $wi; ?>%;"></div>
            <div class="area-bar-fill-current" style="width:<?php echo $wc; ?>%;"></div>
        </div>
        <div class="area-bar-label"><?php echo $lc; ?>/6</div>
    </div>
    <?php endforeach; ?>
    <div style="display:flex;gap:16px;justify-content:center;margin-top:12px;font-size:11px;color:#6B7280;">
        <span><span style="display:inline-block;width:12px;height:12px;background:#D1D5DB;border-radius:2px;vertical-align:middle;"></span> Iniziale</span>
        <span><span style="display:inline-block;width:12px;height:12px;background:#0891B2;border-radius:2px;vertical-align:middle;"></span> Attuale</span>
    </div>
</div>

<!-- Roadmap overview -->
<div class="sip-my-card">
    <h3><i class="fa fa-road"></i> Roadmap</h3>
    <?php foreach ($phases_def as $pnum => $pdef):
        $pclass = 'color:#6B7280;'; $icon = '&#9675;';
        if ($pnum < $current_phase) { $pclass = 'color:#059669;'; $icon = '&#9679;'; }
        elseif ($pnum == $current_phase) { $pclass = 'color:#0891B2;font-weight:700;'; $icon = '&#9654;'; }
    ?>
    <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #F3F4F6;<?php echo $pclass; ?>">
        <span style="font-size:16px;"><?php echo $icon; ?></span>
        <span style="font-weight:600;"><?php echo get_string($pdef['name'], 'local_ftm_sip'); ?></span>
        <span style="font-size:12px;opacity:0.7;">Sett. <?php echo $pdef['weeks']; ?></span>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

</div><!-- /sip-my -->

<script>
var sesskey = '<?php echo sesskey(); ?>';
var ajaxUrl = '<?php echo $CFG->wwwroot; ?>/local/ftm_sip/ajax_student_kpi.php';

function sipPost(action, data, onSuccess) {
    var fd = new FormData();
    fd.append('action', action);
    fd.append('sesskey', sesskey);
    for (var k in data) fd.append(k, data[k]);
    fetch(ajaxUrl, { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) { if (onSuccess) onSuccess(result); else location.reload(); }
        else alert(result.message || 'Errore');
    })
    .catch(function() { alert('Errore di connessione'); });
}

function completeAction(actionid, checkbox) {
    sipPost('complete_action', { actionid: actionid }, function() {
        var el = document.getElementById('action-' + actionid);
        if (el) { el.style.opacity = '0.4'; el.querySelector('.sip-my-action-text').style.textDecoration = 'line-through'; }
        checkbox.disabled = true;
    });
}

function addApplication() {
    var company = document.getElementById('app-company').value.trim();
    if (!company) { alert('Inserisci il nome dell\'azienda'); return; }
    sipPost('add_application', {
        company_name: company,
        position: document.getElementById('app-position').value,
        application_date: document.getElementById('app-date').value,
        application_type: document.getElementById('app-type').value,
        notes: document.getElementById('app-notes').value
    });
}

function addContact() {
    var company = document.getElementById('cnt-company').value.trim();
    if (!company) { alert('Inserisci il nome dell\'azienda'); return; }
    sipPost('add_contact', {
        company_name: company,
        contact_type: document.getElementById('cnt-type').value,
        contact_date: document.getElementById('cnt-date').value,
        contact_person: document.getElementById('cnt-person').value
    });
}

function addOpportunity() {
    var company = document.getElementById('opp-company').value.trim();
    if (!company) { alert('Inserisci il nome dell\'azienda'); return; }
    sipPost('add_opportunity', {
        company_name: company,
        opportunity_type: document.getElementById('opp-type').value,
        opportunity_date: document.getElementById('opp-date').value,
        notes: document.getElementById('opp-notes').value
    });
}
</script>

<?php
echo $OUTPUT->footer();
