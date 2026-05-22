<?php
/**
 * Admin tool: rimuove offerte duplicate da local_jobmatch_offers.
 * Duplicati = stesso titolo + azienda + città (stessa offerta su siti diversi con URL diverse).
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/jobmatchagent/admin_cleanup_offers.php'));
$PAGE->set_title('Cleanup offerte duplicate — JobMatchAgent');
$PAGE->set_heading('Cleanup offerte duplicate');
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$domain_action = optional_param('domain_action', '', PARAM_ALPHANUMEXT);
$domain_raw = optional_param('domain', '', PARAM_TEXT);
// Validate domain against allowed list to prevent misuse.
$allowed_domains = ['randstad.ch', 'randstad.com'];
$domain = in_array($domain_raw, $allowed_domains, true) ? $domain_raw : '';

echo $OUTPUT->header();

echo '<h4>Rimozione offerte duplicate</h4>';
echo '<p class="text-muted">Duplicati rilevati per: stesso <strong>titolo + azienda + città</strong> (stessa offerta pubblicata su più siti con URL diverse). Tiene il record con ID più basso.</p>';

// Find duplicates by title + company + location (case-insensitive).
// Use DB-agnostic LOWER(TRIM(...)).
$dups = $DB->get_records_sql(
    "SELECT LOWER(TRIM(title))                         AS ntitle,
            LOWER(TRIM(COALESCE(company, '')))          AS ncompany,
            LOWER(TRIM(COALESCE(location, '')))         AS nloc,
            COUNT(*)                                    AS cnt,
            MIN(id)                                     AS keep_id
       FROM {local_jobmatch_offers}
      WHERE status = 'active'
      GROUP BY LOWER(TRIM(title)),
               LOWER(TRIM(COALESCE(company, ''))),
               LOWER(TRIM(COALESCE(location, '')))
     HAVING COUNT(*) > 1
      ORDER BY COUNT(*) DESC"
);

$total_extra = 0;
foreach ($dups as $d) {
    $total_extra += ((int)$d->cnt - 1);
}

echo '<div class="alert alert-' . ($total_extra > 0 ? 'warning' : 'success') . '">';
echo '<strong>' . count($dups) . ' gruppi di duplicati</strong> — <strong>' . $total_extra . ' offerte in eccesso</strong> da rimuovere.';
echo '</div>';

if ($action === 'cleanup' && confirm_sesskey()) {
    $removed = 0;
    $removed_results = 0;

    foreach ($dups as $d) {
        // Get IDs of all duplicates to delete (all except keep_id).
        $ids_to_delete = $DB->get_fieldset_sql(
            "SELECT id FROM {local_jobmatch_offers}
              WHERE LOWER(TRIM(title))                   = :ntitle
                AND LOWER(TRIM(COALESCE(company, '')))   = :ncompany
                AND LOWER(TRIM(COALESCE(location, '')))  = :nloc
                AND id != :keep
                AND status = 'active'",
            ['ntitle' => $d->ntitle, 'ncompany' => $d->ncompany, 'nloc' => $d->nloc, 'keep' => $d->keep_id]
        );
        if (empty($ids_to_delete)) {
            continue;
        }
        list($insql, $inparams) = $DB->get_in_or_equal($ids_to_delete, SQL_PARAMS_NAMED);
        // Remove associated results first (only auto/pending — keep coach decisions).
        $removed_results += (int)$DB->count_records_select('local_jobmatch_results',
            "offer_id $insql AND status IN ('pending','ai_done','discarded')", $inparams);
        $DB->delete_records_select('local_jobmatch_results',
            "offer_id $insql AND status IN ('pending','ai_done','discarded')", $inparams);
        $DB->delete_records_select('local_jobmatch_offers', "id $insql", $inparams);
        $removed += count($ids_to_delete);
    }

    echo '<div class="alert alert-success">';
    echo '✅ Rimossi <strong>' . $removed . '</strong> offerte duplicate e <strong>' . $removed_results . '</strong> match associati.';
    echo '</div>';
    echo '<p><a href="' . (new moodle_url('/local/jobmatchagent/admin_cleanup_offers.php'))->out(false) . '" class="btn btn-outline-secondary">Ricontrolla</a> &nbsp;';
    echo '<a href="' . (new moodle_url('/local/jobmatchagent/wizard.php'))->out(false) . '" class="btn btn-primary">Torna al Wizard</a></p>';

} else {
    if ($total_extra > 0) {
        echo '<table class="table table-sm table-bordered" style="font-size:0.85rem">';
        echo '<thead><tr><th>Titolo</th><th>Azienda</th><th>Città</th><th>Copie</th></tr></thead><tbody>';
        $shown = 0;
        foreach ($dups as $d) {
            if ($shown >= 30) { break; }
            echo '<tr>';
            echo '<td>' . s($d->ntitle) . '</td>';
            echo '<td>' . s($d->ncompany ?: '—') . '</td>';
            echo '<td>' . s($d->nloc ?: '—') . '</td>';
            echo '<td><strong>' . (int)$d->cnt . 'x</strong></td>';
            echo '</tr>';
            $shown++;
        }
        if (count($dups) > 30) {
            echo '<tr><td colspan="4" class="text-muted">... e altri ' . (count($dups) - 30) . ' gruppi</td></tr>';
        }
        echo '</tbody></table>';

        $cleanurl = new moodle_url('/local/jobmatchagent/admin_cleanup_offers.php', [
            'action' => 'cleanup', 'sesskey' => sesskey(),
        ]);
        echo '<a href="' . $cleanurl->out(false) . '" class="btn btn-danger" '
            . 'onclick="return confirm(\'Rimuovere ' . $total_extra . ' offerte duplicate? I match pendenti associati verranno eliminati.\');">'
            . '🗑 Rimuovi ' . $total_extra . ' duplicati</a> &nbsp;';
    } else {
        echo '<p class="text-success">Nessun duplicato trovato — catalogo pulito.</p>';
    }
    echo '<a href="' . (new moodle_url('/local/jobmatchagent/wizard.php'))->out(false) . '" class="btn btn-outline-secondary">Torna al Wizard</a>';
}

// --- Section: bulk delete by domain (e.g. Randstad) ---
echo '<hr><h4>Rimozione offerte per dominio</h4>';
echo '<p class="text-muted">Rimuove TUTTE le offerte (e match associati pendenti) provenienti da un determinato sito.</p>';

if ($domain_action === 'delete_domain' && $domain && confirm_sesskey()) {
    $like_pattern = $DB->sql_like_escape('https://www.' . $domain) . '%';
    $like_pattern2 = $DB->sql_like_escape('https://' . $domain) . '%';

    $ids_to_delete = $DB->get_fieldset_sql(
        "SELECT id FROM {local_jobmatch_offers}
          WHERE " . $DB->sql_like('url', ':pat1') . "
             OR " . $DB->sql_like('url', ':pat2'),
        ['pat1' => $like_pattern, 'pat2' => $like_pattern2]
    );

    $del_results = 0;
    $del_offers = 0;
    if (!empty($ids_to_delete)) {
        list($insql, $inparams) = $DB->get_in_or_equal($ids_to_delete, SQL_PARAMS_NAMED);
        $del_results = (int)$DB->count_records_select('local_jobmatch_results',
            "offer_id $insql AND status IN ('pending','ai_done','discarded')", $inparams);
        $DB->delete_records_select('local_jobmatch_results',
            "offer_id $insql AND status IN ('pending','ai_done','discarded')", $inparams);
        $DB->delete_records_select('local_jobmatch_offers', "id $insql", $inparams);
        $del_offers = count($ids_to_delete);
    }
    echo '<div class="alert alert-success">';
    echo '✅ Rimossi <strong>' . $del_offers . '</strong> offerte da <em>' . s($domain) . '</em> e <strong>' . $del_results . '</strong> match associati.';
    echo '</div>';
} else {
    // Show count for each known problematic domain.
    foreach ($allowed_domains as $d) {
        $like_pattern  = $DB->sql_like_escape('https://www.' . $d) . '%';
        $like_pattern2 = $DB->sql_like_escape('https://' . $d) . '%';
        $cnt = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_jobmatch_offers}
              WHERE " . $DB->sql_like('url', ':pat1') . "
                 OR " . $DB->sql_like('url', ':pat2'),
            ['pat1' => $like_pattern, 'pat2' => $like_pattern2]
        );
        echo '<div class="d-flex align-items-center gap-3 mb-2">';
        echo '<span><strong>' . s($d) . '</strong>: <span class="badge bg-' . ($cnt > 0 ? 'danger' : 'success') . '">' . $cnt . ' offerte</span></span>';
        if ($cnt > 0) {
            $delurl = new moodle_url('/local/jobmatchagent/admin_cleanup_offers.php', [
                'domain_action' => 'delete_domain',
                'domain'        => $d,
                'sesskey'       => sesskey(),
            ]);
            echo html_writer::link($delurl->out(false), '🗑 Elimina tutte le offerte ' . s($d),
                [
                    'class'   => 'btn btn-sm btn-danger',
                    'onclick' => 'return confirm(' . json_encode('Eliminare tutte le ' . $cnt . ' offerte da ' . $d . '? Operazione non reversibile.') . ');',
                ]);
        }
        echo '</div>';
    }
}

echo $OUTPUT->footer();
