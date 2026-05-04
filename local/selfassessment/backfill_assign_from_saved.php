<?php
/**
 * Backfill local_selfassessment_assign partendo da local_selfassessment.
 *
 * Prende ogni coppia (userid, competencyid) presente in local_selfassessment
 * (autovalutazioni gia' salvate, anche dal vecchio sistema) e se manca
 * il record corrispondente in local_selfassessment_assign, lo crea.
 *
 * Operazione puramente additiva: NESSUNA riga viene mai cancellata o modificata.
 * I voti Bloom in local_selfassessment non vengono toccati.
 *
 * USO:
 *   /local/selfassessment/backfill_assign_from_saved.php              (dry-run tutti)
 *   /local/selfassessment/backfill_assign_from_saved.php?userid=1253  (dry-run Risto)
 *   /local/selfassessment/backfill_assign_from_saved.php?apply=1      (applica tutti)
 *   /local/selfassessment/backfill_assign_from_saved.php?userid=1253&apply=1
 *
 * @package    local_selfassessment
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$apply = optional_param('apply', 0, PARAM_INT);
$onlyuser = optional_param('userid', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/selfassessment/backfill_assign_from_saved.php');
$PAGE->set_title('Backfill assignments da autovalutazioni salvate');
$PAGE->set_heading('Backfill assignments - ' . ($apply ? 'APPLY' : 'DRY RUN'));
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

if ($apply) {
    echo '<div class="alert alert-warning"><strong>APPLY mode:</strong> i record mancanti verranno creati.</div>';
} else {
    echo '<div class="alert alert-info"><strong>DRY RUN:</strong> nessuna modifica al DB. Aggiungi <code>&apply=1</code> per eseguire.</div>';
}

// Trova le coppie (userid, competencyid) presenti in local_selfassessment
// ma mancanti in local_selfassessment_assign.
$params = [];
$where = '';
if ($onlyuser) {
    $where = ' AND sa.userid = ?';
    $params[] = $onlyuser;
}

$sql = "SELECT sa.id AS said, sa.userid, sa.competencyid, sa.timecreated
        FROM {local_selfassessment} sa
        LEFT JOIN {local_selfassessment_assign} asg
          ON asg.userid = sa.userid AND asg.competencyid = sa.competencyid
        WHERE asg.id IS NULL $where
        ORDER BY sa.userid, sa.competencyid";

$missing = $DB->get_records_sql($sql, $params);

echo '<p>Coppie (user, competency) da inserire in <code>local_selfassessment_assign</code>: <strong>' . count($missing) . '</strong></p>';

if (empty($missing)) {
    echo '<div class="alert alert-success">Nulla da fare: tutte le autovalutazioni hanno gia\' un record di assegnazione.</div>';
    echo $OUTPUT->footer();
    die();
}

// Raggruppa per utente.
$by_user = [];
foreach ($missing as $row) {
    $by_user[$row->userid][] = $row;
}

echo '<table class="generaltable"><thead><tr>';
echo '<th>User</th><th>Da inserire</th><th>Competency IDs</th><th>Esito</th>';
echo '</tr></thead><tbody>';

$total_created = 0;
$now = time();

foreach ($by_user as $uid => $rows) {
    $u = $DB->get_record('user', ['id' => $uid], 'id, firstname, lastname, username');
    $ulabel = $u ? fullname($u) . ' (' . s($u->username) . ', id=' . $u->id . ')' : 'userid=' . $uid;

    $ids = array_map(function($r) { return $r->competencyid; }, $rows);
    $ids_label = implode(', ', $ids);

    $esito = '';
    if ($apply) {
        $inserted = 0;
        foreach ($rows as $r) {
            $exists = $DB->record_exists('local_selfassessment_assign', [
                'userid' => $r->userid,
                'competencyid' => $r->competencyid,
            ]);
            if ($exists) {
                continue;
            }
            $rec = new stdClass();
            $rec->userid = (int)$r->userid;
            $rec->competencyid = (int)$r->competencyid;
            $rec->source = 'legacy_backfill';
            $rec->sourceid = 0;
            $rec->timecreated = $r->timecreated ? (int)$r->timecreated : $now;
            try {
                $DB->insert_record('local_selfassessment_assign', $rec);
                $inserted++;
                $total_created++;
            } catch (Exception $e) {
                $esito .= '<br><small style="color:red">err comp ' . (int)$r->competencyid . ': ' . s($e->getMessage()) . '</small>';
            }
        }
        $esito = '<span style="color:green">inseriti ' . $inserted . '/' . count($rows) . '</span>' . $esito;
    } else {
        $esito = '<em>sarebbero inseriti ' . count($rows) . '</em>';
    }

    echo '<tr>';
    echo '<td>' . $ulabel . '</td>';
    echo '<td>' . count($rows) . '</td>';
    echo '<td><small>' . s($ids_label) . '</small></td>';
    echo '<td>' . $esito . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';

if ($apply) {
    echo '<div class="alert alert-success"><strong>Fatto.</strong> Totale record creati: ' . $total_created . '.</div>';
} else {
    $href = '?apply=1' . ($onlyuser ? '&userid=' . (int)$onlyuser : '');
    echo '<p><a class="btn btn-primary" href="' . $href . '">Esegui davvero</a></p>';
}

echo $OUTPUT->footer();
