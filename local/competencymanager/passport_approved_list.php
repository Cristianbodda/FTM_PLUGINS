<?php
/**
 * Elenco passaporti tecnici — Approvati, Bozze, Tutti — accesso manager/superiore
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
$PAGE->set_title('Passaporti Tecnici');
$PAGE->set_heading('Passaporti Tecnici');
$PAGE->set_pagelayout('report');

// -------------------------------------------------------------------------
// Query 1: Passaporti APPROVATI (area_code = '__PASSPORT_APPROVED__')
// -------------------------------------------------------------------------
$sql_approved = "SELECT
        pc.id,
        pc.userid,
        pc.courseid,
        pc.coachid AS display_coachid,
        pc.timecreated AS display_date,
        pc.comment AS approval_json,
        u.firstname, u.lastname, u.email,
        coach_u.firstname AS coach_firstname,
        coach_u.lastname  AS coach_lastname,
        ss.sector AS primary_sector,
        fg.color  AS group_color,
        (SELECT COUNT(*)
           FROM {local_passport_comments} pc2
          WHERE pc2.userid = pc.userid
            AND pc2.area_code NOT LIKE '%\\_\\_ORIG'
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

$approved_records = $DB->get_records_sql($sql_approved);

// Userid degli approvati (per escluderli dalle bozze)
$approved_userids = array_keys(array_column((array)$approved_records, null, 'userid'));

// -------------------------------------------------------------------------
// Query 2: Passaporti BOZZA
// Studenti con almeno un commento salvato ma senza approvazione formale.
// Coach mostrato = coach assegnato (local_student_coaching), non chi ha scritto.
// -------------------------------------------------------------------------
$sql_bozze = "SELECT
        u.id AS userid,
        0 AS courseid,
        sc.coachid AS display_coachid,
        MAX(pc.timemodified) AS display_date,
        NULL AS approval_json,
        u.firstname, u.lastname, u.email,
        coach_u.firstname AS coach_firstname,
        coach_u.lastname  AS coach_lastname,
        ss.sector AS primary_sector,
        fg.color  AS group_color,
        (SELECT COUNT(*)
           FROM {local_passport_comments} pc2
          WHERE pc2.userid = u.id
            AND pc2.area_code NOT LIKE '%\\_\\_ORIG'
            AND pc2.area_code NOT IN ('__PASSPORT_APPROVED__', 'FINAL_NOTE')
            AND pc2.comment IS NOT NULL
            AND pc2.comment != '') AS areas_compiled
    FROM {user} u
    JOIN {local_passport_comments} pc ON pc.userid = u.id
         AND pc.area_code NOT LIKE '%\\_\\_ORIG'
         AND pc.area_code NOT IN ('__PASSPORT_APPROVED__', 'FINAL_NOTE')
         AND pc.comment IS NOT NULL AND pc.comment != ''
    LEFT JOIN {local_student_coaching} sc ON sc.userid = u.id
    LEFT JOIN {user} coach_u ON coach_u.id = sc.coachid
    LEFT JOIN {local_student_sectors} ss ON ss.userid = u.id AND ss.is_primary = 1
    LEFT JOIN {local_ftm_group_members} gm ON gm.userid = u.id
    LEFT JOIN {local_ftm_groups} fg ON fg.id = gm.groupid
   WHERE u.deleted = 0
     AND NOT EXISTS (
         SELECT 1 FROM {local_passport_comments} pa
          WHERE pa.userid = u.id AND pa.area_code = '__PASSPORT_APPROVED__'
     )
   GROUP BY u.id, u.firstname, u.lastname, u.email,
            sc.coachid, coach_u.firstname, coach_u.lastname,
            ss.sector, fg.color
   ORDER BY display_date DESC";

$bozze_records = $DB->get_records_sql($sql_bozze);

// -------------------------------------------------------------------------
// Lista "Tutti" = approvati + bozze, ordinati per data desc
// -------------------------------------------------------------------------
$all_records = array_merge(array_values((array)$approved_records), array_values((array)$bozze_records));
usort($all_records, function($a, $b) { return ($b->display_date ?? 0) - ($a->display_date ?? 0); });

// -------------------------------------------------------------------------
// CI attivi (batch)
// -------------------------------------------------------------------------
$ci_active_userids = [];
if ($DB->get_manager()->table_exists('local_ftm_sip_enrollments')) {
    $rows = $DB->get_records_select('local_ftm_sip_enrollments', "status = 'active'", [], '', 'userid');
    $ci_active_userids = array_keys($rows);
}

// -------------------------------------------------------------------------
// Statistiche
// -------------------------------------------------------------------------
$total_approved = count($approved_records);
$total_bozze    = count($bozze_records);

$month_start    = mktime(0, 0, 0, date('n'), 1);
$this_month     = 0;
$coach_counts   = [];
foreach ($approved_records as $r) {
    if (($r->display_date ?? 0) >= $month_start) {
        $this_month++;
    }
    $cn = trim(($r->coach_firstname ?? '') . ' ' . ($r->coach_lastname ?? ''));
    if ($cn !== '') {
        $coach_counts[$cn] = ($coach_counts[$cn] ?? 0) + 1;
    }
}
$top_coach = '—';
if (!empty($coach_counts)) {
    arsort($coach_counts);
    $top_coach = array_key_first($coach_counts);
}

// -------------------------------------------------------------------------
// Mappe colori
// -------------------------------------------------------------------------
$sector_colors = [
    'MECCANICA'        => '#6c757d',
    'AUTOMAZIONE'      => '#7030A0',
    'AUTOM'            => '#7030A0',
    'AUTOMAZ'          => '#7030A0',
    'METALCOSTRUZIONE' => '#455a64',
    'METAL'            => '#455a64',
    'ELETTRICITA'      => '#e67e22',
    'ELETTR'           => '#e67e22',
    'ELETT'            => '#e67e22',
    'AUTOMOBILE'       => '#3498db',
    'AUTOVEICOLO'      => '#3498db',
    'LOGISTICA'        => '#27ae60',
    'LOG'              => '#27ae60',
    'CHIMFARM'         => '#8e44ad',
    'CHIM'             => '#8e44ad',
    'CHIMICA'          => '#8e44ad',
    'FARMACEUTICA'     => '#8e44ad',
    'GENERICO'         => '#999999',
];

$group_color_map = [
    'giallo'  => ['bg' => '#FFFF00', 'text' => '#000000'],
    'grigio'  => ['bg' => '#808080', 'text' => '#ffffff'],
    'rosso'   => ['bg' => '#FF0000', 'text' => '#ffffff'],
    'marrone' => ['bg' => '#996633', 'text' => '#ffffff'],
    'viola'   => ['bg' => '#7030A0', 'text' => '#ffffff'],
];

// -------------------------------------------------------------------------
// Helper: render tabella righe
// $rows = array di record, $show_stato = bool (colonna Stato per tab Tutti)
// -------------------------------------------------------------------------
function passval_render_table($rows, $show_stato, $approved_userids_set,
                               $ci_active_userids, $sector_colors, $group_color_map, $CFG) {
    if (empty($rows)) {
        echo '<div class="passval-empty"><div class="passval-empty-icon">&#128220;</div>';
        echo '<p>Nessun passaporto in questa categoria.</p></div>';
        return;
    }
    echo '<div style="overflow-x:auto;"><table class="passval-list-table"><thead><tr>';
    echo '<th>Studente</th>';
    if ($show_stato) echo '<th>Stato</th>';
    echo '<th>Settore</th><th>Gruppo</th><th>Coach</th><th>CI</th>';
    echo '<th>' . ($show_stato ? 'Ultima attività' : 'Data') . '</th>';
    echo '<th>Aree</th><th>Azioni</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $record) {
        $is_approved = isset($approved_userids_set[(int)$record->userid]);

        // Settore
        $sector = $record->primary_sector ?? '';
        if ($is_approved && !empty($record->approval_json)) {
            $approval = @json_decode($record->approval_json);
            if (!empty($approval->sector)) {
                $sector = $approval->sector;
            }
        }
        $sector_upper = strtoupper(trim($sector));
        $sector_color = $sector_colors[$sector_upper] ?? '#6c757d';

        // Gruppo
        $gc      = strtolower($record->group_color ?? '');
        $gc_info = $group_color_map[$gc] ?? null;

        // Courseid per link
        $courseid_link = (int)($record->courseid ?? 0);

        // CI
        $ci_active = in_array((int)$record->userid, $ci_active_userids);

        // Coach
        $coach_name = trim(($record->coach_firstname ?? '') . ' ' . ($record->coach_lastname ?? ''));

        echo '<tr>';

        // Studente
        echo '<td>';
        echo '<a href="' . $CFG->wwwroot . '/local/competencymanager/technical_passport.php?userid='
            . (int)$record->userid . '&amp;courseid=' . $courseid_link
            . '" style="font-weight:600;color:#0066cc;">' . s(fullname($record)) . '</a>';
        if (!empty($record->email)) {
            echo '<div style="font-size:11px;color:#6c757d;">' . s($record->email) . '</div>';
        }
        echo '</td>';

        // Stato (solo tab Tutti)
        if ($show_stato) {
            if ($is_approved) {
                echo '<td><span class="stato-badge stato-approved">&#10003; Approvato</span></td>';
            } else {
                echo '<td><span class="stato-badge stato-bozza">&#9998; Bozza</span></td>';
            }
        }

        // Settore
        echo '<td>';
        if (!empty($sector_upper)) {
            echo '<span class="sector-badge" style="background:' . s($sector_color) . ';">'
                . s($sector_upper) . '</span>';
        } else {
            echo '<span style="color:#aaa;">—</span>';
        }
        echo '</td>';

        // Gruppo
        echo '<td>';
        if ($gc_info !== null) {
            echo '<span class="group-badge" style="background:' . s($gc_info['bg'])
                . ';color:' . s($gc_info['text']) . ';">' . s(ucfirst($gc)) . '</span>';
        } else {
            echo '<span style="color:#aaa;">—</span>';
        }
        echo '</td>';

        // Coach
        echo '<td>' . s($coach_name ?: '—') . '</td>';

        // CI
        echo '<td>';
        echo $ci_active
            ? '<span class="ci-badge">CI Attivo</span>'
            : '<span style="color:#aaa;">—</span>';
        echo '</td>';

        // Data
        echo '<td style="white-space:nowrap;">';
        $ts = (int)($record->display_date ?? 0);
        echo $ts > 0 ? userdate($ts, get_string('strftimedate', 'langconfig')) : '—';
        echo '</td>';

        // Aree
        echo '<td style="text-align:center;">' . (int)($record->areas_compiled ?? 0) . '</td>';

        // Azioni
        echo '<td style="white-space:nowrap;">';
        echo '<a href="' . $CFG->wwwroot . '/local/competencymanager/technical_passport.php?userid='
            . (int)$record->userid . '&amp;courseid=' . $courseid_link
            . '" class="btn-passport" title="Apri Passaporto Tecnico">&#128220; Passaporto</a>';
        echo '<a href="' . $CFG->wwwroot . '/local/competencymanager/student_report.php?userid='
            . (int)$record->userid . '&amp;courseid=' . $courseid_link
            . '" class="btn-report" title="Apri Student Report">&#128202; Report</a>';
        echo '</td>';

        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

// Set approvati indicizzato per userid per lookup O(1)
$approved_set = array_fill_keys(array_map(fn($r) => (int)$r->userid, (array)$approved_records), true);

// =========================================================================
// OUTPUT
// =========================================================================
echo $OUTPUT->header();
?>
<style>
.passval-list-table { width:100%; border-collapse:collapse; font-size:13px; }
.passval-list-table th { background:#f8f9fa; padding:10px 12px; text-align:left; border-bottom:2px solid #dee2e6; font-weight:600; white-space:nowrap; }
.passval-list-table td { padding:9px 12px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
.passval-list-table tr:hover td { background:#f8f9fa; }
.sector-badge  { display:inline-block; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; color:white; }
.group-badge   { display:inline-block; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:700; }
.ci-badge      { background:#0891B2; color:white; padding:2px 8px; border-radius:12px; font-size:11px; display:inline-block; }
.stato-badge   { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
.stato-approved { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
.stato-bozza    { background:#ffedd5; color:#9a3412; border:1px solid #fdba74; }
.btn-passport  { background:#16a34a; color:white; padding:4px 10px; border-radius:4px; font-size:12px; text-decoration:none; margin-right:4px; display:inline-block; }
.btn-passport:hover { background:#15803d; color:white; text-decoration:none; }
.btn-report    { background:#0066cc; color:white; padding:4px 10px; border-radius:4px; font-size:12px; text-decoration:none; display:inline-block; }
.btn-report:hover  { background:#0052a3; color:white; text-decoration:none; }
.stats-bar     { display:flex; gap:16px; margin-bottom:24px; flex-wrap:wrap; }
.stat-card     { background:#f8f9fa; border:1px solid #dee2e6; border-radius:8px; padding:12px 20px; text-align:center; min-width:120px; }
.stat-number   { font-size:28px; font-weight:700; color:#0066cc; }
.stat-label    { font-size:12px; color:#6c757d; margin-top:2px; }
.stat-card.bozza .stat-number { color:#ea580c; }
.passval-empty { text-align:center; padding:60px 20px; color:#6c757d; }
.passval-empty-icon { font-size:48px; margin-bottom:12px; }
.nav-tabs .nav-link      { color:#495057; }
.nav-tabs .nav-link.active { color:#0066cc; font-weight:600; }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <h2 style="margin:0; color:#0066cc;">&#128220; Passaporti Tecnici</h2>
    <a href="<?php echo $CFG->wwwroot; ?>/local/coachmanager/coach_dashboard_v2.php"
       class="btn btn-secondary btn-sm">&larr; Dashboard Coach</a>
</div>

<!-- Stats bar -->
<div class="stats-bar">
    <div class="stat-card">
        <div class="stat-number"><?php echo $total_approved; ?></div>
        <div class="stat-label">&#10003; Approvati</div>
    </div>
    <div class="stat-card bozza">
        <div class="stat-number"><?php echo $total_bozze; ?></div>
        <div class="stat-label">&#9998; Bozze</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $total_approved + $total_bozze; ?></div>
        <div class="stat-label">&#128220; Totale</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $this_month; ?></div>
        <div class="stat-label">Approvati questo mese</div>
    </div>
    <div class="stat-card" style="min-width:180px;">
        <div class="stat-number" style="font-size:16px; padding-top:6px;"><?php echo s($top_coach); ?></div>
        <div class="stat-label">Coach pi&ugrave; attivo</div>
    </div>
</div>

<!-- Tabs Bootstrap 4 -->
<ul class="nav nav-tabs mb-3" id="passvalTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="tab-approved" data-toggle="tab" href="#pane-approved" role="tab">
            &#10003; Approvati <span class="badge badge-success ml-1"><?php echo $total_approved; ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-bozze" data-toggle="tab" href="#pane-bozze" role="tab">
            &#9998; Bozze <span class="badge badge-warning ml-1"><?php echo $total_bozze; ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-tutti" data-toggle="tab" href="#pane-tutti" role="tab">
            &#128220; Tutti <span class="badge badge-secondary ml-1"><?php echo $total_approved + $total_bozze; ?></span>
        </a>
    </li>
</ul>

<div class="tab-content" id="passvalTabsContent">

    <!-- TAB APPROVATI -->
    <div class="tab-pane fade show active" id="pane-approved" role="tabpanel">
        <?php passval_render_table(
            array_values((array)$approved_records),
            false,
            $approved_set,
            $ci_active_userids,
            $sector_colors,
            $group_color_map,
            $CFG
        ); ?>
    </div>

    <!-- TAB BOZZE -->
    <div class="tab-pane fade" id="pane-bozze" role="tabpanel">
        <?php passval_render_table(
            array_values((array)$bozze_records),
            false,
            $approved_set,
            $ci_active_userids,
            $sector_colors,
            $group_color_map,
            $CFG
        ); ?>
    </div>

    <!-- TAB TUTTI -->
    <div class="tab-pane fade" id="pane-tutti" role="tabpanel">
        <?php passval_render_table(
            $all_records,
            true,
            $approved_set,
            $ci_active_userids,
            $sector_colors,
            $group_color_map,
            $CFG
        ); ?>
    </div>

</div>

<?php
echo $OUTPUT->footer();
