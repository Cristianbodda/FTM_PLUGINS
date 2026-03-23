<?php
/**
 * Company Registry - shared company list for SIP.
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

$can_edit = has_capability('local/ftm_sip:edit', $context);
$search = optional_param('search', '', PARAM_TEXT);
$sectorfilter = optional_param('sector', '', PARAM_TEXT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_sip/companies.php'));
$PAGE->set_title(get_string('company_registry_title', 'local_ftm_sip'));
$PAGE->set_heading(get_string('sip_manager', 'local_ftm_sip'));

// Fetch companies.
$params = [];
$where = '1=1';
if (!empty($search)) {
    $where .= ' AND c.name_normalized LIKE :search';
    $params['search'] = '%' . $DB->sql_like_escape(strtolower($search)) . '%';
}
if (!empty($sectorfilter)) {
    $where .= ' AND c.sector = :sector';
    $params['sector'] = $sectorfilter;
}

$companies = $DB->get_records_sql(
    "SELECT c.*,
            (SELECT COUNT(*) FROM {local_ftm_sip_applications} a WHERE a.companyid = c.id) AS app_count,
            (SELECT COUNT(*) FROM {local_ftm_sip_contacts} ct WHERE ct.companyid = c.id) AS contact_count,
            (SELECT COUNT(*) FROM {local_ftm_sip_opportunities} o WHERE o.companyid = c.id) AS opp_count
     FROM {local_ftm_sip_companies} c
     WHERE {$where}
     ORDER BY c.interaction_count DESC, c.name ASC",
    $params
);

$total = count($companies);

// Available sectors.
$sectors = ['AUTOMOBILE', 'AUTOMAZIONE', 'CHIMFARM', 'ELETTRICITA', 'LOGISTICA', 'MECCANICA', 'METALCOSTRUZIONE'];

echo $OUTPUT->header();
?>

<style>
.comp-page { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 1100px; margin: 0 auto; }
.comp-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
.comp-header h2 { margin: 0; font-size: 22px; color: #1A1A2E; }
.comp-filters { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }
.comp-filters input, .comp-filters select { border: 1px solid #DEE2E6; border-radius: 6px; padding: 8px 12px; font-size: 13px; }
.comp-filters input { min-width: 250px; }
.comp-table { background: white; border-radius: 8px; border: 1px solid #DEE2E6; overflow: hidden; }
.comp-table table { width: 100%; border-collapse: collapse; font-size: 13px; }
.comp-table th { padding: 10px 12px; text-align: left; font-size: 11px; color: #6B7280; text-transform: uppercase; background: #F8F9FA; border-bottom: 2px solid #DEE2E6; }
.comp-table td { padding: 10px 12px; border-bottom: 1px solid #F3F4F6; }
.comp-table tr:hover { background: #F8FFFE; }
.comp-name { font-weight: 600; color: #1A1A2E; }
.comp-sector { background: #F3F4F6; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; }
.comp-count { text-align: center; font-weight: 600; }
.comp-status { padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; }

#addCompanyForm { display: none; background: white; border: 1px solid #0891B2; border-radius: 8px; padding: 20px; margin-bottom: 16px; }
#addCompanyForm h4 { margin: 0 0 12px; color: #0891B2; }

@media (max-width: 768px) { .comp-table { overflow-x: auto; } .comp-filters input { min-width: 180px; } }
</style>

<div class="comp-page">

<div class="comp-header">
    <h2><i class="fa fa-building"></i> <?php echo get_string('company_registry_title', 'local_ftm_sip'); ?> (<?php echo $total; ?>)</h2>
    <div style="display:flex;gap:8px;">
        <?php if ($can_edit): ?>
        <button onclick="document.getElementById('addCompanyForm').style.display = document.getElementById('addCompanyForm').style.display === 'none' ? 'block' : 'none';"
                style="background:#0891B2;color:white;border:none;padding:8px 18px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">
            <i class="fa fa-plus"></i> <?php echo get_string('new_company', 'local_ftm_sip'); ?>
        </button>
        <?php endif; ?>
        <a href="<?php echo $CFG->wwwroot; ?>/local/ftm_sip/sip_dashboard.php"
           style="background:#F3F4F6;color:#374151;padding:8px 18px;border-radius:6px;font-size:13px;font-weight:500;text-decoration:none;display:flex;align-items:center;">
            &#8592; Dashboard SIP
        </a>
    </div>
</div>

<!-- Add company form -->
<?php if ($can_edit): ?>
<div id="addCompanyForm">
    <h4><i class="fa fa-plus"></i> <?php echo get_string('new_company', 'local_ftm_sip'); ?></h4>
    <form method="post" action="<?php echo $CFG->wwwroot; ?>/local/ftm_sip/ajax_company.php">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="action" value="create">
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:10px;margin-bottom:12px;">
            <div><label style="font-size:11px;font-weight:600;display:block;">Nome *</label>
                <input type="text" name="name" required style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:13px;" placeholder="Nome azienda"></div>
            <div><label style="font-size:11px;font-weight:600;display:block;">Settore</label>
                <select name="sector" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:13px;">
                    <option value="">-</option>
                    <?php foreach ($sectors as $s): ?>
                    <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label style="font-size:11px;font-weight:600;display:block;">Citta</label>
                <input type="text" name="city" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:13px;"></div>
            <div><label style="font-size:11px;font-weight:600;display:block;">Persona contatto</label>
                <input type="text" name="contact_person" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:13px;"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;margin-bottom:12px;">
            <div><label style="font-size:11px;font-weight:600;display:block;">Telefono</label>
                <input type="text" name="phone" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:13px;"></div>
            <div><label style="font-size:11px;font-weight:600;display:block;">Email</label>
                <input type="text" name="email" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:13px;"></div>
            <div><label style="font-size:11px;font-weight:600;display:block;">Sito Web</label>
                <input type="text" name="website" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:13px;"></div>
            <div><label style="font-size:11px;font-weight:600;display:block;">Note</label>
                <input type="text" name="notes" style="width:100%;border:1px solid #DEE2E6;border-radius:4px;padding:6px;font-size:13px;"></div>
        </div>
        <button type="submit" style="background:#0891B2;color:white;border:none;padding:8px 20px;border-radius:6px;font-weight:600;cursor:pointer;"><?php echo get_string('save', 'local_ftm_sip'); ?></button>
    </form>
</div>
<?php endif; ?>

<!-- Filters -->
<form method="get" class="comp-filters">
    <input type="text" name="search" value="<?php echo s($search); ?>" placeholder="<?php echo get_string('search_companies', 'local_ftm_sip'); ?>">
    <select name="sector" onchange="this.form.submit()">
        <option value="">Tutti i settori</option>
        <?php foreach ($sectors as $s): ?>
        <option value="<?php echo $s; ?>" <?php echo $sectorfilter === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" style="background:#0891B2;color:white;border:none;padding:8px 16px;border-radius:6px;font-size:13px;cursor:pointer;">
        <i class="fa fa-search"></i> <?php echo get_string('search', 'local_ftm_sip'); ?>
    </button>
    <?php if ($search || $sectorfilter): ?>
    <a href="?" style="color:#6B7280;font-size:13px;"><?php echo get_string('reset_filters', 'local_ftm_sip'); ?></a>
    <?php endif; ?>
</form>

<!-- Company table -->
<div class="comp-table">
    <table>
        <thead>
            <tr>
                <th>Azienda</th>
                <th>Settore</th>
                <th>Citta</th>
                <th>Contatto</th>
                <th>Tel / Email</th>
                <th style="text-align:center;">Cand.</th>
                <th style="text-align:center;">Cont.</th>
                <th style="text-align:center;">Opp.</th>
                <th>Stato</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($companies)): ?>
            <tr><td colspan="9" style="text-align:center;padding:30px;color:#6B7280;"><?php echo get_string('no_companies', 'local_ftm_sip'); ?></td></tr>
        <?php else: ?>
        <?php foreach ($companies as $comp):
            $status_colors = ['prospect' => '#6B7280', 'contacted' => '#2563EB', 'interested' => '#F59E0B', 'collaborating' => '#059669', 'inactive' => '#9CA3AF'];
            $status_bg = ['prospect' => '#F3F4F6', 'contacted' => '#DBEAFE', 'interested' => '#FEF3C7', 'collaborating' => '#D1FAE5', 'inactive' => '#F3F4F6'];
        ?>
        <tr>
            <td class="comp-name"><?php echo s($comp->name); ?></td>
            <td><?php if ($comp->sector): ?><span class="comp-sector"><?php echo s($comp->sector); ?></span><?php else: ?>-<?php endif; ?></td>
            <td style="color:#6B7280;"><?php echo s($comp->city ?: '-'); ?></td>
            <td style="font-size:12px;"><?php echo s($comp->contact_person ?: '-'); ?></td>
            <td style="font-size:11px;color:#6B7280;">
                <?php if ($comp->phone): ?><?php echo s($comp->phone); ?><br><?php endif; ?>
                <?php if ($comp->email): ?><?php echo s($comp->email); ?><?php endif; ?>
                <?php if (!$comp->phone && !$comp->email): ?>-<?php endif; ?>
            </td>
            <td class="comp-count" style="color:#2563EB;"><?php echo $comp->app_count; ?></td>
            <td class="comp-count" style="color:#7C3AED;"><?php echo $comp->contact_count; ?></td>
            <td class="comp-count" style="color:#059669;"><?php echo $comp->opp_count; ?></td>
            <td>
                <?php if ($can_edit): ?>
                <select onchange="updateCompanyStatus(<?php echo $comp->id; ?>, this.value)" style="border:1px solid #DEE2E6;border-radius:4px;padding:2px;font-size:11px;">
                    <?php foreach (['prospect', 'contacted', 'interested', 'collaborating', 'inactive'] as $st): ?>
                    <option value="<?php echo $st; ?>" <?php echo $comp->status === $st ? 'selected' : ''; ?>
                            style="color:<?php echo $status_colors[$st]; ?>;">
                        <?php echo get_string('company_status_' . $st, 'local_ftm_sip'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <span class="comp-status" style="background:<?php echo $status_bg[$comp->status] ?? '#F3F4F6'; ?>;color:<?php echo $status_colors[$comp->status] ?? '#6B7280'; ?>;">
                    <?php echo get_string('company_status_' . $comp->status, 'local_ftm_sip'); ?>
                </span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</div>

<script>
function updateCompanyStatus(companyid, status) {
    var fd = new FormData();
    fd.append('action', 'update_status');
    fd.append('companyid', companyid);
    fd.append('status', status);
    fd.append('sesskey', '<?php echo sesskey(); ?>');
    fetch('<?php echo $CFG->wwwroot; ?>/local/ftm_sip/ajax_company.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) { if (!data.success) alert(data.message || 'Errore'); })
    .catch(function() { alert('Errore'); });
}
</script>

<?php
echo $OUTPUT->footer();
