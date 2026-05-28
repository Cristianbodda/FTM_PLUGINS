<?php
/**
 * Elenco passaporti tecnici approvati — accesso manager/superiore
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/competencymanager:viewallevaluations', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/passport_approved_list.php'));
$PAGE->set_title(get_string('passport_approved_list', 'local_competencymanager'));
$PAGE->set_heading(get_string('passport_approved_list', 'local_competencymanager'));
$PAGE->set_pagelayout('report');

// -------------------------------------------------------------------------
// Query principale: passaporti approvati
// -------------------------------------------------------------------------
$sql = "SELECT
            pc.id,
            pc.userid,
            pc.courseid,
            pc.coachid AS approved_by_coachid,
            pc.timecreated AS approved_at,
            pc.comment AS approval_json,
            u.firstname,
            u.lastname,
            u.email,
            coach_u.firstname AS coach_firstname,
            coach_u.lastname AS coach_lastname,
            ss.sector AS primary_sector,
            fg.color AS group_color,
            (SELECT COUNT(*)
               FROM {local_passport_comments} pc2
              WHERE pc2.userid = pc.userid
                AND pc2.area_code NOT LIKE '%__ORIG'
                AND pc2.area_code NOT IN ('__PASSPORT_APPROVED__', 'FINAL_NOTE')
                AND pc2.comment IS NOT NULL
                AND pc2.comment != '') AS areas_compiled
        FROM {local_passport_comments} pc
        JOIN {user} u ON u.id = pc.userid AND u.deleted = 0
        LEFT JOIN {user} coach_u ON coach_u.id = pc.coachid
        LEFT JOIN {local_student_sectors} ss ON ss.userid = pc.userid AND ss.is_primary = 1
        LEFT JOIN {local_student_coaching} sc ON sc.userid = pc.userid
        LEFT JOIN {local_ftm_group_members} gm ON gm.userid = pc.userid
        LEFT JOIN {local_ftm_groups} fg ON fg.id = gm.groupid
       WHERE pc.area_code = '__PASSPORT_APPROVED__'
       ORDER BY pc.timecreated DESC";

$records = $DB->get_records_sql($sql);

// -------------------------------------------------------------------------
// CI attivi
// -------------------------------------------------------------------------
$ci_active_userids = [];
if ($DB->get_manager()->table_exists('local_ftm_sip_enrollments')) {
    $rows = $DB->get_records_select('local_ftm_sip_enrollments', "status = 'active'", [], '', 'userid');
    $ci_active_userids = array_keys($rows);
}

// -------------------------------------------------------------------------
// Statistiche
// -------------------------------------------------------------------------
$total_approved = count($records);

$month_start = mktime(0, 0, 0, date('n'), 1);
$this_month = 0;
$coach_counts = [];
foreach ($records as $r) {
    if ($r->approved_at >= $month_start) {
        $this_month++;
    }
    $coach_label = trim($r->coach_firstname . ' ' . $r->coach_lastname);
    if ($coach_label !== '') {
        $coach_counts[$coach_label] = ($coach_counts[$coach_label] ?? 0) + 1;
    }
}

$top_coach = '—';
if (!empty($coach_counts)) {
    arsort($coach_counts);
    $top_coach = array_key_first($coach_counts);
}

// -------------------------------------------------------------------------
// Mappatura colori settori
// -------------------------------------------------------------------------
$sector_colors = [
    'MECCANICA'         => '#6c757d',
    'AUTOMAZIONE'       => '#7030A0',
    'AUTOM'             => '#7030A0',
    'AUTOMAZ'           => '#7030A0',
    'METALCOSTRUZIONE'  => '#455a64',
    'METAL'             => '#455a64',
    'ELETTRICITA'       => '#e67e22',
    'ELETTR'            => '#e67e22',
    'ELETT'             => '#e67e22',
    'AUTOMOBILE'        => '#3498db',
    'AUTOVEICOLO'       => '#3498db',
    'LOGISTICA'         => '#27ae60',
    'LOG'               => '#27ae60',
    'CHIMFARM'          => '#8e44ad',
    'CHIM'              => '#8e44ad',
    'CHIMICA'           => '#8e44ad',
    'FARMACEUTICA'      => '#8e44ad',
    'GENERICO'          => '#999999',
];

// -------------------------------------------------------------------------
// Output
// -------------------------------------------------------------------------
echo $OUTPUT->header();

?>
<style>
.passval-list-table { width:100%; border-collapse:collapse; font-size:13px; }
.passval-list-table th { background:#f8f9fa; padding:10px 12px; text-align:left; border-bottom:2px solid #dee2e6; font-weight:600; }
.passval-list-table td { padding:9px 12px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
.passval-list-table tr:hover { background:#f8f9fa; }
.sector-badge { display:inline-block; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; color:white; }
.group-badge { display:inline-block; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:700; }
.ci-badge { background:#0891B2; color:white; padding:2px 8px; border-radius:12px; font-size:11px; display:inline-block; }
.btn-passport { background:#16a34a; color:white; padding:4px 10px; border-radius:4px; font-size:12px; text-decoration:none; margin-right:4px; display:inline-block; }
.btn-passport:hover { background:#15803d; color:white; text-decoration:none; }
.btn-report { background:#0066cc; color:white; padding:4px 10px; border-radius:4px; font-size:12px; text-decoration:none; display:inline-block; }
.btn-report:hover { background:#0052a3; color:white; text-decoration:none; }
.stats-bar { display:flex; gap:20px; margin-bottom:20px; flex-wrap:wrap; }
.stat-card { background:#f8f9fa; border:1px solid #dee2e6; border-radius:8px; padding:12px 20px; text-align:center; min-width:120px; }
.stat-number { font-size:28px; font-weight:700; color:#0066cc; }
.stat-label { font-size:12px; color:#6c757d; margin-top:2px; }
.passval-empty { text-align:center; padding:60px 20px; color:#6c757d; }
.passval-empty-icon { font-size:48px; margin-bottom:12px; }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <h2 style="margin:0; color:#0066cc;">&#128220; Passaporti Tecnici Approvati</h2>
    <a href="<?php echo $CFG->wwwroot; ?>/local/coachmanager/coach_dashboard_v2.php" class="btn btn-secondary btn-sm">&larr; Dashboard Coach</a>
</div>

<!-- Stats bar -->
<div class="stats-bar">
    <div class="stat-card">
        <div class="stat-number"><?php echo $total_approved; ?></div>
        <div class="stat-label">Totale approvati</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $this_month; ?></div>
        <div class="stat-label">Questo mese</div>
    </div>
    <div class="stat-card" style="min-width:180px;">
        <div class="stat-number" style="font-size:16px; padding-top:6px;"><?php echo s($top_coach); ?></div>
        <div class="stat-label">Coach pi&ugrave; attivo</div>
    </div>
</div>

<?php if (empty($records)): ?>
<div class="passval-empty">
    <div class="passval-empty-icon">&#128220;</div>
    <p><?php echo get_string('passval_no_passports', 'local_competencymanager'); ?></p>
</div>
<?php else: ?>

<div style="overflow-x:auto;">
<table class="passval-list-table">
    <thead>
        <tr>
            <th>Studente</th>
            <th>Settore</th>
            <th>Gruppo</th>
            <th>Coach</th>
            <th>CI</th>
            <th><?php echo get_string('passval_approved_at', 'local_competencymanager'); ?></th>
            <th><?php echo get_string('passval_areas_compiled', 'local_competencymanager'); ?></th>
            <th>Azioni</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($records as $record):

        // Determina settore: preferisce dal JSON, fallback a primary_sector della query
        $sector = $record->primary_sector ?? '';
        if (!empty($record->approval_json)) {
            $approval = @json_decode($record->approval_json);
            if (!empty($approval->sector)) {
                $sector = $approval->sector;
            }
        }
        $sector_upper = strtoupper(trim($sector));
        $sector_color = $sector_colors[$sector_upper] ?? '#6c757d';

        // Colore gruppo
        $group_color_map = [
            'giallo'  => ['bg' => '#FFFF00', 'text' => '#000000'],
            'grigio'  => ['bg' => '#808080', 'text' => '#ffffff'],
            'rosso'   => ['bg' => '#FF0000', 'text' => '#ffffff'],
            'marrone' => ['bg' => '#996633', 'text' => '#ffffff'],
            'viola'   => ['bg' => '#7030A0', 'text' => '#ffffff'],
        ];
        $gc = strtolower($record->group_color ?? '');
        $gc_info = $group_color_map[$gc] ?? null;

        // Courseid (dalla riga del record)
        $courseid_link = (int)($record->courseid ?? 0);

        // CI attivo?
        $ci_active = in_array($record->userid, $ci_active_userids);

    ?>
        <tr>
            <td>
                <a href="<?php echo $CFG->wwwroot; ?>/local/competencymanager/technical_passport.php?userid=<?php echo (int)$record->userid; ?>&amp;courseid=<?php echo $courseid_link; ?>" style="font-weight:600; color:#0066cc;">
                    <?php echo s(fullname($record)); ?>
                </a>
                <?php if (!empty($record->email)): ?>
                <div style="font-size:11px; color:#6c757d;"><?php echo s($record->email); ?></div>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!empty($sector_upper)): ?>
                <span class="sector-badge" style="background:<?php echo s($sector_color); ?>;"><?php echo s($sector_upper); ?></span>
                <?php else: ?>
                <span style="color:#aaa;">—</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($gc_info !== null): ?>
                <span class="group-badge" style="background:<?php echo s($gc_info['bg']); ?>; color:<?php echo s($gc_info['text']); ?>;">
                    <?php echo s(ucfirst($gc)); ?>
                </span>
                <?php else: ?>
                <span style="color:#aaa;">—</span>
                <?php endif; ?>
            </td>
            <td>
                <?php
                $coach_name = trim($record->coach_firstname . ' ' . $record->coach_lastname);
                echo s($coach_name ?: '—');
                ?>
            </td>
            <td>
                <?php if ($ci_active): ?>
                <span class="ci-badge">CI Attivo</span>
                <?php else: ?>
                <span style="color:#aaa;">—</span>
                <?php endif; ?>
            </td>
            <td style="white-space:nowrap;">
                <?php echo userdate($record->approved_at, get_string('strftimedate', 'langconfig')); ?>
            </td>
            <td style="text-align:center;">
                <?php echo (int)$record->areas_compiled; ?>
            </td>
            <td style="white-space:nowrap;">
                <a href="<?php echo $CFG->wwwroot; ?>/local/competencymanager/technical_passport.php?userid=<?php echo (int)$record->userid; ?>&amp;courseid=<?php echo $courseid_link; ?>" class="btn-passport" title="Apri Passaporto Tecnico">&#128220; Passaporto</a>
                <a href="<?php echo $CFG->wwwroot; ?>/local/competencymanager/student_report.php?userid=<?php echo (int)$record->userid; ?>&amp;courseid=<?php echo $courseid_link; ?>" class="btn-report" title="Apri Student Report">&#128202; Report</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php endif; ?>

<?php
echo $OUTPUT->footer();
