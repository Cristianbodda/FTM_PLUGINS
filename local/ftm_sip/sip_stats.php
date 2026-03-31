<?php
/**
 * SIP Aggregate Statistics Dashboard for Direction/URC staff.
 *
 * Shows aggregate SIP statistics with filters, outcome distribution,
 * activation level evolution, and coach performance.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_sip:manage', $context);

// Filters.
$filter_from = optional_param('from', '', PARAM_TEXT);
$filter_to = optional_param('to', '', PARAM_TEXT);
$filter_coach = optional_param('coach', 0, PARAM_INT);
$filter_sector = optional_param('sector', '', PARAM_ALPHANUMEXT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_sip/sip_stats.php', [
    'from' => $filter_from,
    'to' => $filter_to,
    'coach' => $filter_coach,
    'sector' => $filter_sector,
]));
$PAGE->set_title(get_string('stats_title', 'local_ftm_sip'));
$PAGE->set_heading(get_string('sip_manager', 'local_ftm_sip'));

// Default date range: last 12 months.
$now = time();
if (empty($filter_from)) {
    $ts_from = $now - (365 * 86400);
} else {
    $ts_from = strtotime($filter_from);
    if ($ts_from === false) {
        $ts_from = $now - (365 * 86400);
    }
}
if (empty($filter_to)) {
    $ts_to = $now;
} else {
    $ts_to = strtotime($filter_to . ' 23:59:59');
    if ($ts_to === false) {
        $ts_to = $now;
    }
}

$date_from_display = date('Y-m-d', $ts_from);
$date_to_display = date('Y-m-d', $ts_to);

// Build WHERE conditions.
$where_parts = ['e.date_start >= :ts_from', 'e.date_start <= :ts_to', 'u.deleted = 0'];
$params = ['ts_from' => $ts_from, 'ts_to' => $ts_to];

if ($filter_coach > 0) {
    $where_parts[] = 'e.coachid = :coachid';
    $params['coachid'] = $filter_coach;
}
if (!empty($filter_sector)) {
    $where_parts[] = 'e.sector = :sector';
    $params['sector'] = $filter_sector;
}

$where_sql = implode(' AND ', $where_parts);

// =========================================================================
// DATA QUERIES
// =========================================================================

// --- Stats Cards ---
$sql = "SELECT e.id, e.status, e.outcome, e.date_start, e.date_end_actual
        FROM {local_ftm_sip_enrollments} e
        JOIN {user} u ON u.id = e.userid
        WHERE {$where_sql}";
$enrollments = $DB->get_records_sql($sql, $params);

$total_activated = count($enrollments);
$count_completed = 0;
$count_interrupted = 0;
$count_active = 0;

// Outcome counts.
$outcome_map = [
    'hired' => 0,
    'stage' => 0,
    'tryout' => 0,
    'intermediate_earning' => 0,
    'training' => 0,
    'not_placed_activated' => 0,
    'not_suitable' => 0,
    'none' => 0,
    'interrupted' => 0,
];

foreach ($enrollments as $enr) {
    if ($enr->status === 'completed') {
        $count_completed++;
        if (!empty($enr->outcome) && isset($outcome_map[$enr->outcome])) {
            $outcome_map[$enr->outcome]++;
        } else if (!empty($enr->outcome)) {
            // Unknown outcome, count as 'none'.
            $outcome_map['none']++;
        }
    } else if ($enr->status === 'cancelled' || $enr->status === 'suspended') {
        $count_interrupted++;
        $outcome_map['interrupted']++;
    } else if ($enr->status === 'active') {
        $count_active++;
    }
}

// Tasso completamento.
$rate_completion = ($total_activated > 0) ? round(($count_completed / $total_activated) * 100, 1) : 0;

// Tasso inserimento (hired + stage + tryout / completati).
$count_inserted = $outcome_map['hired'] + $outcome_map['stage'] + $outcome_map['tryout'];
$rate_insertion = ($count_completed > 0) ? round(($count_inserted / $count_completed) * 100, 1) : 0;

// --- Outcome distribution totals for percentage ---
$outcome_total_for_percent = $count_completed + $count_interrupted;

// --- Activation Level Evolution (avg initial vs final for completed SIPs) ---
$areas = local_ftm_sip_get_activation_areas();
$area_evolution = [];

// Get all completed enrollment IDs in the period.
$completed_ids = [];
foreach ($enrollments as $enr) {
    if ($enr->status === 'completed') {
        $completed_ids[] = $enr->id;
    }
}

if (!empty($completed_ids)) {
    list($in_sql, $in_params) = $DB->get_in_or_equal($completed_ids, SQL_PARAMS_NAMED, 'cid');
    $sql = "SELECT ap.area_key,
                   AVG(ap.level_initial) AS avg_initial,
                   AVG(ap.level_current) AS avg_final,
                   COUNT(ap.id) AS cnt
            FROM {local_ftm_sip_action_plan} ap
            WHERE ap.enrollmentid {$in_sql}
            AND ap.level_initial IS NOT NULL
            AND ap.level_current IS NOT NULL
            GROUP BY ap.area_key
            ORDER BY ap.area_key";
    $area_evolution = $DB->get_records_sql($sql, $in_params);
}

// --- Coach Performance ---
$sql = "SELECT e.coachid, c.firstname, c.lastname,
               COUNT(e.id) AS total_students,
               SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
               SUM(CASE WHEN e.outcome IN ('hired', 'stage', 'tryout') THEN 1 ELSE 0 END) AS inserted_count
        FROM {local_ftm_sip_enrollments} e
        JOIN {user} u ON u.id = e.userid
        JOIN {user} c ON c.id = e.coachid
        WHERE {$where_sql}
        GROUP BY e.coachid, c.firstname, c.lastname
        ORDER BY total_students DESC";
$coach_perf = $DB->get_records_sql($sql, $params);

// Enrich with meetings/week and applications.
foreach ($coach_perf as &$cp) {
    // Get enrollment IDs for this coach.
    $coach_enr_sql = "SELECT e.id FROM {local_ftm_sip_enrollments} e
                      JOIN {user} u ON u.id = e.userid
                      WHERE {$where_sql} AND e.coachid = :cpcoachid";
    $cp_params = array_merge($params, ['cpcoachid' => $cp->coachid]);
    $coach_enr_ids = $DB->get_fieldset_sql($coach_enr_sql, $cp_params);

    $cp->avg_meetings_week = 0;
    $cp->total_applications = 0;

    if (!empty($coach_enr_ids)) {
        list($enr_in, $enr_params) = $DB->get_in_or_equal($coach_enr_ids, SQL_PARAMS_NAMED, 'cenr');

        // Total meetings.
        $total_meetings = (int) $DB->count_records_select('local_ftm_sip_meetings',
            "enrollmentid {$enr_in}", $enr_params);

        // Total weeks (sum of active weeks across students).
        $total_weeks = 0;
        foreach ($coach_enr_ids as $ceid) {
            $cenr = $DB->get_record('local_ftm_sip_enrollments', ['id' => $ceid], 'date_start, date_end_actual, status');
            if ($cenr && $cenr->date_start > 0) {
                if ($cenr->date_end_actual > 0) {
                    $w = max(1, ceil(($cenr->date_end_actual - $cenr->date_start) / (7 * 86400)));
                } else {
                    $w = local_ftm_sip_calculate_week($cenr->date_start);
                }
                $total_weeks += max(1, $w);
            }
        }
        $cp->avg_meetings_week = ($total_weeks > 0) ? round($total_meetings / $total_weeks, 1) : 0;

        // Total applications.
        $cp->total_applications = (int) $DB->count_records_select('local_ftm_sip_applications',
            "enrollmentid {$enr_in}", $enr_params);
    }
}
unset($cp);

// --- Filter dropdowns data ---
// Coaches who have SIP students.
$sql = "SELECT DISTINCT e.coachid, c.firstname, c.lastname
        FROM {local_ftm_sip_enrollments} e
        JOIN {user} c ON c.id = e.coachid AND c.deleted = 0
        ORDER BY c.lastname, c.firstname";
$available_coaches = $DB->get_records_sql($sql);

// Sectors.
$sql = "SELECT DISTINCT e.sector
        FROM {local_ftm_sip_enrollments} e
        WHERE e.sector IS NOT NULL AND e.sector != ''
        ORDER BY e.sector";
$available_sectors = $DB->get_fieldset_sql($sql);

// =========================================================================
// OUTPUT
// =========================================================================

echo $OUTPUT->header();
?>

<style>
:root {
    --sip-primary: #0891B2;
    --sip-primary-bg: #ECFEFF;
    --sip-primary-text: #155E75;
    --sip-success: #059669;
    --sip-success-bg: #D1FAE5;
    --sip-danger: #DC2626;
    --sip-danger-bg: #FEE2E2;
    --sip-warning: #F59E0B;
    --sip-warning-bg: #FEF3C7;
    --sip-blue: #2563EB;
    --sip-blue-bg: #DBEAFE;
    --sip-gray: #6B7280;
    --sip-gray-light: #F3F4F6;
    --sip-border: #DEE2E6;
    --sip-text: #1A1A2E;
    --sip-text-muted: #6B7280;
}

.sip-stats {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    max-width: 1200px;
    margin: 0 auto;
}

/* Header */
.sip-stats-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.sip-stats-header h2 {
    margin: 0;
    font-size: 24px;
    color: var(--sip-text);
}

/* Filters */
.sip-stats-filters {
    background: white;
    border-radius: 8px;
    padding: 16px 20px;
    margin-bottom: 20px;
    border: 1px solid var(--sip-border);
    display: flex;
    align-items: flex-end;
    gap: 14px;
    flex-wrap: wrap;
}
.sip-stats-filters .filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.sip-stats-filters label {
    font-size: 11px;
    color: var(--sip-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}
.sip-stats-filters input[type="date"],
.sip-stats-filters select {
    padding: 6px 10px;
    border: 1px solid var(--sip-border);
    border-radius: 6px;
    font-size: 13px;
    color: var(--sip-text);
    background: white;
}
.sip-stats-filters .filter-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}
.sip-stats-filters .btn-apply {
    padding: 6px 18px;
    background: var(--sip-primary);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}
.sip-stats-filters .btn-apply:hover { opacity: 0.9; }
.sip-stats-filters .btn-reset {
    font-size: 12px;
    color: var(--sip-text-muted);
    text-decoration: none;
}
.sip-stats-filters .btn-reset:hover { color: var(--sip-danger); }

/* Stats Cards */
.sip-stats-cards {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
    margin-bottom: 24px;
}
.sip-stat-card {
    background: white;
    border-radius: 8px;
    padding: 18px;
    text-align: center;
    border: 1px solid var(--sip-border);
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.sip-stat-card .stat-top-border {
    width: 40px;
    height: 4px;
    border-radius: 2px;
    margin: 0 auto 12px;
}
.sip-stat-card .stat-value {
    font-size: 30px;
    font-weight: 700;
    color: var(--sip-text);
    line-height: 1;
}
.sip-stat-card .stat-sub {
    font-size: 12px;
    color: var(--sip-text-muted);
    margin-top: 2px;
}
.sip-stat-card .stat-label {
    font-size: 11px;
    color: var(--sip-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 8px;
}

/* Sections */
.sip-stats-section {
    background: white;
    border-radius: 8px;
    border: 1px solid var(--sip-border);
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.sip-stats-section h3 {
    margin: 0 0 16px;
    font-size: 16px;
    color: var(--sip-text);
    padding-bottom: 10px;
    border-bottom: 1px solid var(--sip-border);
}

/* Tables */
.sip-stats-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.sip-stats-table th {
    padding: 10px 14px;
    text-align: left;
    font-size: 11px;
    color: var(--sip-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: var(--sip-gray-light);
    border-bottom: 2px solid var(--sip-border);
}
.sip-stats-table td {
    padding: 10px 14px;
    border-bottom: 1px solid #F3F4F6;
    vertical-align: middle;
}
.sip-stats-table tr:hover { background: #F8FFFE; }

/* Outcome bar */
.outcome-bar-container {
    background: var(--sip-gray-light);
    border-radius: 4px;
    height: 20px;
    width: 100%;
    max-width: 200px;
    overflow: hidden;
}
.outcome-bar {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
    min-width: 2px;
}

/* Delta badges */
.delta-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}
.delta-positive { background: var(--sip-success-bg); color: #065F46; }
.delta-negative { background: var(--sip-danger-bg); color: #991B1B; }
.delta-neutral { background: var(--sip-gray-light); color: var(--sip-gray); }

/* Back link */
.sip-stats-back {
    padding: 8px 18px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    background: var(--sip-gray-light);
    color: #374151;
}
.sip-stats-back:hover { text-decoration: none; opacity: 0.9; }

/* Responsive */
@media (max-width: 768px) {
    .sip-stats-cards { grid-template-columns: repeat(2, 1fr); }
    .sip-stats-filters { flex-direction: column; align-items: stretch; }
    .sip-stats-table { font-size: 12px; }
    .sip-stats-section { overflow-x: auto; }
}
</style>

<div class="sip-stats">

<!-- Header -->
<div class="sip-stats-header">
    <h2><span style="color:var(--sip-primary);">&#128202;</span> <?php echo get_string('stats_title', 'local_ftm_sip'); ?></h2>
    <a href="<?php echo $CFG->wwwroot; ?>/local/ftm_sip/sip_dashboard.php" class="sip-stats-back">
        &#8592; <?php echo get_string('dashboard', 'local_ftm_sip'); ?>
    </a>
</div>

<!-- Filters -->
<form method="get" action="sip_stats.php" class="sip-stats-filters">
    <div class="filter-group">
        <label>Da</label>
        <input type="date" name="from" value="<?php echo s($date_from_display); ?>">
    </div>
    <div class="filter-group">
        <label>A</label>
        <input type="date" name="to" value="<?php echo s($date_to_display); ?>">
    </div>
    <div class="filter-group">
        <label>Coach</label>
        <select name="coach">
            <option value="0">-- Tutti --</option>
            <?php foreach ($available_coaches as $ac): ?>
            <option value="<?php echo $ac->coachid; ?>" <?php echo ($filter_coach == $ac->coachid) ? 'selected' : ''; ?>>
                <?php echo s(fullname($ac)); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <label>Settore</label>
        <select name="sector">
            <option value="">-- Tutti --</option>
            <?php foreach ($available_sectors as $sec): ?>
            <option value="<?php echo s($sec); ?>" <?php echo ($filter_sector === $sec) ? 'selected' : ''; ?>>
                <?php echo s($sec); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn-apply">Applica</button>
        <a href="sip_stats.php" class="btn-reset">Reset</a>
    </div>
</form>

<!-- Stats Cards -->
<div class="sip-stats-cards">
    <div class="sip-stat-card">
        <div class="stat-top-border" style="background:var(--sip-primary);"></div>
        <div class="stat-value"><?php echo $total_activated; ?></div>
        <div class="stat-label">CI Attivati</div>
    </div>
    <div class="sip-stat-card">
        <div class="stat-top-border" style="background:var(--sip-success);"></div>
        <div class="stat-value"><?php echo $count_completed; ?></div>
        <div class="stat-sub"><?php echo ($total_activated > 0) ? round(($count_completed / $total_activated) * 100, 0) . '%' : '0%'; ?></div>
        <div class="stat-label">Completati</div>
    </div>
    <div class="sip-stat-card">
        <div class="stat-top-border" style="background:var(--sip-danger);"></div>
        <div class="stat-value"><?php echo $count_interrupted; ?></div>
        <div class="stat-sub"><?php echo ($total_activated > 0) ? round(($count_interrupted / $total_activated) * 100, 0) . '%' : '0%'; ?></div>
        <div class="stat-label">Interrotti</div>
    </div>
    <div class="sip-stat-card">
        <div class="stat-top-border" style="background:var(--sip-blue);"></div>
        <div class="stat-value"><?php echo $rate_completion; ?>%</div>
        <div class="stat-label">Tasso Completamento</div>
    </div>
    <div class="sip-stat-card">
        <div class="stat-top-border" style="background:var(--sip-warning);"></div>
        <div class="stat-value"><?php echo $rate_insertion; ?>%</div>
        <div class="stat-label">Tasso Inserimento</div>
    </div>
</div>

<!-- Outcome Distribution -->
<div class="sip-stats-section">
    <h3>Distribuzione Esiti</h3>
    <?php
    $outcome_labels = [
        'hired' => ['Assunti', '#059669'],
        'stage' => ['Stage', '#2563EB'],
        'tryout' => ['Tryout', '#7C3AED'],
        'intermediate_earning' => ['Guadagno Intermedio', '#D97706'],
        'training' => ['Formazione', '#0891B2'],
        'none' => ['Non Collocati', '#6B7280'],
        'interrupted' => ['Interrotti', '#DC2626'],
    ];
    ?>
    <table class="sip-stats-table">
        <thead>
            <tr>
                <th>Esito</th>
                <th>N</th>
                <th>%</th>
                <th style="width:220px;"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($outcome_labels as $key => $info):
            $count = $outcome_map[$key];
            $pct = ($outcome_total_for_percent > 0) ? round(($count / $outcome_total_for_percent) * 100, 1) : 0;
        ?>
        <tr>
            <td>
                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?php echo $info[1]; ?>;margin-right:8px;vertical-align:middle;"></span>
                <?php echo s($info[0]); ?>
            </td>
            <td style="font-weight:600;"><?php echo $count; ?></td>
            <td><?php echo $pct; ?>%</td>
            <td>
                <div class="outcome-bar-container">
                    <div class="outcome-bar" style="width:<?php echo $pct; ?>%;background:<?php echo $info[1]; ?>;"></div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Activation Level Evolution -->
<div class="sip-stats-section">
    <h3>Evoluzione Media Livelli di Attivazione (SIP completati)</h3>
    <?php if (empty($area_evolution)): ?>
    <p style="color:var(--sip-text-muted);text-align:center;padding:20px;">
        Nessun SIP completato nel periodo selezionato.
    </p>
    <?php else: ?>
    <table class="sip-stats-table">
        <thead>
            <tr>
                <th>Area</th>
                <th style="text-align:center;">Media Iniziale</th>
                <th style="text-align:center;">Media Finale</th>
                <th style="text-align:center;">Delta</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($areas as $area_key => $area_def):
            $evo = null;
            foreach ($area_evolution as $ae) {
                if ($ae->area_key === $area_key) {
                    $evo = $ae;
                    break;
                }
            }
            $avg_init = ($evo !== null) ? round((float)$evo->avg_initial, 1) : '-';
            $avg_final = ($evo !== null) ? round((float)$evo->avg_final, 1) : '-';
            $delta = ($evo !== null) ? round((float)$evo->avg_final - (float)$evo->avg_initial, 1) : null;
            $delta_class = 'delta-neutral';
            $delta_prefix = '';
            if ($delta !== null) {
                if ($delta > 0) {
                    $delta_class = 'delta-positive';
                    $delta_prefix = '+';
                } else if ($delta < 0) {
                    $delta_class = 'delta-negative';
                }
            }
        ?>
        <tr>
            <td>
                <i class="fa <?php echo s($area_def['icon']); ?>" style="color:<?php echo s($area_def['color']); ?>;margin-right:8px;"></i>
                <?php echo get_string($area_def['name'], 'local_ftm_sip'); ?>
            </td>
            <td style="text-align:center;font-weight:600;"><?php echo $avg_init; ?></td>
            <td style="text-align:center;font-weight:600;"><?php echo $avg_final; ?></td>
            <td style="text-align:center;">
                <?php if ($delta !== null): ?>
                <span class="delta-badge <?php echo $delta_class; ?>"><?php echo $delta_prefix . $delta; ?></span>
                <?php else: ?>
                <span style="color:var(--sip-text-muted);">-</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Coach Performance -->
<div class="sip-stats-section">
    <h3>Performance Coach</h3>
    <?php if (empty($coach_perf)): ?>
    <p style="color:var(--sip-text-muted);text-align:center;padding:20px;">
        Nessun dato coach nel periodo selezionato.
    </p>
    <?php else: ?>
    <table class="sip-stats-table">
        <thead>
            <tr>
                <th>Coach</th>
                <th style="text-align:center;">N Studenti</th>
                <th style="text-align:center;">Completati</th>
                <th style="text-align:center;">Inseriti</th>
                <th style="text-align:center;">Media Incontri/Sett.</th>
                <th style="text-align:center;">Candidature Tot.</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($coach_perf as $cp): ?>
        <tr>
            <td style="font-weight:600;"><?php echo s(fullname($cp)); ?></td>
            <td style="text-align:center;"><?php echo (int)$cp->total_students; ?></td>
            <td style="text-align:center;"><?php echo (int)$cp->completed_count; ?></td>
            <td style="text-align:center;">
                <?php
                $inserted = (int)$cp->inserted_count;
                echo $inserted;
                if ((int)$cp->completed_count > 0) {
                    $pct = round(($inserted / (int)$cp->completed_count) * 100, 0);
                    echo ' <span style="color:var(--sip-text-muted);font-size:11px;">(' . $pct . '%)</span>';
                }
                ?>
            </td>
            <td style="text-align:center;">
                <span style="font-weight:600;"><?php echo $cp->avg_meetings_week; ?></span>
            </td>
            <td style="text-align:center;"><?php echo (int)$cp->total_applications; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

</div><!-- /sip-stats -->

<?php
echo $OUTPUT->footer();
