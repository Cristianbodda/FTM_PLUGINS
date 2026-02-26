<?php
/**
 * Import produzione studenti FTM.
 *
 * Parsa l'Excel CPURC, deduplica per email, mostra anteprima,
 * e importa: CPURC data, iscrizione corso, gruppo colore, coach, settore.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_cpurc/lib.php');

require_login();

if (!is_siteadmin()) {
    throw new moodle_exception('nopermissions', 'error', '', 'Solo amministratori del sito');
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_cpurc/import_production.php'));
$PAGE->set_title('Import Produzione Studenti');
$PAGE->set_heading('Import Produzione Studenti FTM');

// Cerca il corso R.comp per nome (l'id puo' variare tra server).
$COURSE_RCOMP_ID = \local_ftm_cpurc\user_manager::find_course('R.comp');
if (!$COURSE_RCOMP_ID) {
    // Fallback: prova altri pattern.
    $COURSE_RCOMP_ID = \local_ftm_cpurc\user_manager::find_course('Rcomp');
}
if (!$COURSE_RCOMP_ID) {
    $COURSE_RCOMP_ID = \local_ftm_cpurc\user_manager::find_course('competenz');
}

// Mapping data inizio -> gruppo colore.
$GROUP_MAP = [
    '19.01.2026' => 'giallo',
    '02.02.2026' => 'grigio',
    '16.02.2026' => 'rosso',
    '02.03.2026' => 'marrone',
    '16.03.2026' => 'viola',
    '30.03.2026' => 'giallo',
    '13.04.2026' => 'grigio',
    '27.04.2026' => 'rosso',
];

// Mapping formatore -> coach (cerca in DB).
$COACH_NAMES = [
    'cristian bodda'    => null,
    'fabio marinoni'    => null,
    'graziano margonar' => null,
    'roberto bravo'     => null,
];

// Pre-carica coach userid.
foreach ($COACH_NAMES as $name => &$uid) {
    $parts = explode(' ', $name);
    $firstname = $parts[0];
    $lastname = $parts[1] ?? '';
    $coach = $DB->get_record_select('user',
        'LOWER(firstname) = :fn AND LOWER(lastname) = :ln AND deleted = 0',
        ['fn' => $firstname, 'ln' => $lastname],
        'id',
        IGNORE_MULTIPLE
    );
    if ($coach) {
        $uid = $coach->id;
    }
}
unset($uid);

// Colori hex per gruppi.
$COLOR_HEX = [
    'giallo'  => '#FFFF00',
    'grigio'  => '#808080',
    'rosso'   => '#FF0000',
    'marrone' => '#996633',
    'viola'   => '#7030A0',
];

$action = optional_param('action', '', PARAM_ALPHANUMEXT);

// ============================================================
// STEP 2: Conferma import
// ============================================================
if ($action === 'confirm' && confirm_sesskey()) {
    $rows = $SESSION->cpurc_import_preview ?? [];
    unset($SESSION->cpurc_import_preview);

    if (empty($rows)) {
        redirect(
            new moodle_url('/local/ftm_cpurc/import_production.php'),
            'Nessun dato da importare.',
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $importer = new \local_ftm_cpurc\csv_importer();
    $results = [];

    foreach ($rows as $row) {
        $res = [
            'name' => $row['firstname'] . ' ' . $row['lastname'],
            'email' => $row['email'],
            'status_import' => $row['_status'] ?? 'active',
            'errors' => [],
            'actions' => [],
        ];

        // 1. Cerca utente Moodle per email.
        $email = strtolower(trim($row['email']));
        $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);

        if (!$user) {
            $res['errors'][] = 'Utente non trovato in Moodle per email: ' . s($email);
            $results[] = $res;
            continue;
        }

        $userid = $user->id;
        $res['actions'][] = 'Utente Moodle trovato (id=' . $userid . ')';

        // 2. Salva record CPURC.
        $cpurc_record = new stdClass();
        $cpurc_record->userid = $userid;
        $cpurc_fields = [
            'cpurc_id', 'gender', 'title', 'address_street', 'address_cap', 'address_city',
            'birthdate', 'civil_status', 'avs_number', 'nationality', 'permit', 'iban',
            'phone', 'mobile', 'personal_number', 'measure', 'trainer', 'signal_date',
            'date_start', 'date_end_planned', 'date_end_actual', 'occupation_grade',
            'urc_office', 'urc_consultant', 'status', 'exit_reason', 'company', 'observations',
            'absence_x', 'absence_o', 'absence_a', 'absence_b', 'absence_c', 'absence_d',
            'absence_e', 'absence_f', 'absence_g', 'absence_h', 'absence_i', 'absence_total',
            'interviews', 'stages_count', 'stage_days', 'last_profession', 'priority',
            'financier', 'unemployment_fund', 'framework_start', 'framework_end',
            'framework_allowance', 'framework_art59d', 'stage_start', 'stage_end',
            'stage_responsible', 'stage_company_name', 'stage_company_cap', 'stage_company_city',
            'stage_company_street', 'stage_contact_name', 'stage_contact_phone',
            'stage_contact_email', 'stage_percentage', 'stage_function', 'conclusion_date',
            'conclusion_type', 'conclusion_reason', 'sector_detected',
        ];
        foreach ($cpurc_fields as $f) {
            if (isset($row[$f])) {
                $cpurc_record->$f = $row[$f];
            }
        }

        // Status mapping.
        $rawstatus = strtolower(trim($row['status'] ?? ''));
        if (strpos($rawstatus, 'aperto') !== false || empty($rawstatus)) {
            $cpurc_record->status = 'active';
        } else {
            $cpurc_record->status = 'closed';
        }

        $cpurc_record->import_batch = 'production_' . date('Ymd');
        $cpurc_record->timemodified = time();

        $existing_cpurc = $DB->get_record('local_ftm_cpurc_students', ['userid' => $userid]);
        if ($existing_cpurc) {
            $cpurc_record->id = $existing_cpurc->id;
            $DB->update_record('local_ftm_cpurc_students', $cpurc_record);
            $res['actions'][] = 'CPURC aggiornato';
        } else {
            $cpurc_record->timecreated = time();
            $DB->insert_record('local_ftm_cpurc_students', $cpurc_record);
            $res['actions'][] = 'CPURC creato';
        }

        // 3. Rileva settore e sincronizza.
        $sector = \local_ftm_cpurc\profession_mapper::detect_sector($row['last_profession'] ?? '');
        if ($sector) {
            \local_ftm_cpurc\user_manager::sync_sector($userid, $sector, $COURSE_RCOMP_ID ?: 0);
            $res['actions'][] = 'Settore: ' . $sector;
        }

        // Solo per studenti ATTIVI (Aperto).
        if ($cpurc_record->status === 'active') {

            // 4. Iscrivi al corso R.comp.
            if ($COURSE_RCOMP_ID) {
                \local_ftm_cpurc\user_manager::enrol_in_course($userid, $COURSE_RCOMP_ID);
                $res['actions'][] = 'Iscritto a corso R.comp (id=' . $COURSE_RCOMP_ID . ')';
            } else {
                $res['actions'][] = 'SKIP iscrizione: corso R.comp non trovato';
            }

            // 5. Assegna gruppo colore.
            $color = $row['_group_color'] ?? '';
            if ($color) {
                $date_start_ts = $row['date_start'] ?? 0;
                $kw = !empty($date_start_ts) ? (int)date('W', $date_start_ts) : 0;

                $group = $DB->get_record('local_ftm_groups', [
                    'color' => $color,
                    'calendar_week' => $kw,
                ]);

                if (!$group) {
                    $group = new stdClass();
                    $group->name = 'Gruppo ' . ucfirst($color) . ' - KW' . str_pad($kw, 2, '0', STR_PAD_LEFT);
                    $group->color = $color;
                    $group->color_hex = $COLOR_HEX[$color] ?? '#000000';
                    $group->entry_date = $date_start_ts;
                    $group->planned_end_date = $date_start_ts ? strtotime('+6 weeks', $date_start_ts) : 0;
                    $group->calendar_week = $kw;
                    $group->status = 'active';
                    $group->createdby = 0;
                    $group->timecreated = time();
                    $group->timemodified = time();
                    $group->id = $DB->insert_record('local_ftm_groups', $group);
                }

                if (!$DB->record_exists('local_ftm_group_members', ['groupid' => $group->id, 'userid' => $userid])) {
                    $member = new stdClass();
                    $member->groupid = $group->id;
                    $member->userid = $userid;
                    $member->current_week = \local_ftm_cpurc\cpurc_manager::calculate_week_number($date_start_ts);
                    $member->extended_weeks = 0;
                    $member->status = 'active';
                    $member->timecreated = time();
                    $member->timemodified = time();
                    $DB->insert_record('local_ftm_group_members', $member);
                }
                $res['actions'][] = 'Gruppo: ' . ucfirst($color) . ' KW' . str_pad($kw, 2, '0', STR_PAD_LEFT);
            }

            // 6. Assegna coach.
            $coach_userid = $row['_coach_userid'] ?? 0;
            if ($coach_userid) {
                \local_ftm_cpurc\cpurc_manager::assign_coach($userid, $coach_userid, $COURSE_RCOMP_ID);
                $res['actions'][] = 'Coach assegnato (id=' . $coach_userid . ')';
            }
        } else {
            $res['actions'][] = 'Studente chiuso - skip iscrizione/gruppo/coach';
        }

        $results[] = $res;
    }

    // Log import.
    $log = new stdClass();
    $log->batch_id = 'production_' . date('Ymd_His');
    $log->filename = 'import_production';
    $log->total_rows = count($rows);
    $log->imported_count = count(array_filter($results, function($r) { return empty($r['errors']); }));
    $log->updated_count = 0;
    $log->error_count = count(array_filter($results, function($r) { return !empty($r['errors']); }));
    $log->errors_json = json_encode(array_filter(array_map(function($r) {
        return !empty($r['errors']) ? ['name' => $r['name'], 'errors' => $r['errors']] : null;
    }, $results)));
    $log->importedby = $USER->id;
    $log->timecreated = time();
    $DB->insert_record('local_ftm_cpurc_imports', $log);

    // Mostra risultati.
    echo $OUTPUT->header();
    echo '<h3>Risultato Import Produzione</h3>';

    $ok_count = count(array_filter($results, function($r) { return empty($r['errors']); }));
    $err_count = count($results) - $ok_count;

    echo '<div class="alert alert-info">';
    echo '<strong>Totale:</strong> ' . count($results) . ' studenti | ';
    echo '<strong>Importati:</strong> ' . $ok_count . ' | ';
    echo '<strong>Errori:</strong> ' . $err_count;
    echo '</div>';

    echo '<table class="table table-sm table-bordered">';
    echo '<thead class="thead-dark"><tr>';
    echo '<th>Studente</th><th>Email</th><th>Stato</th><th>Azioni</th><th>Errori</th>';
    echo '</tr></thead><tbody>';

    foreach ($results as $r) {
        $rowclass = empty($r['errors']) ? '' : 'table-danger';
        echo '<tr class="' . $rowclass . '">';
        echo '<td>' . s($r['name']) . '</td>';
        echo '<td>' . s($r['email']) . '</td>';
        echo '<td>' . s($r['status_import']) . '</td>';
        echo '<td><small>' . implode('<br>', array_map('s', $r['actions'])) . '</small></td>';
        echo '<td>';
        if (!empty($r['errors'])) {
            echo '<span class="text-danger">' . implode('<br>', array_map('s', $r['errors'])) . '</span>';
        } else {
            echo '<span class="text-success">OK</span>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    echo '<a href="' . (new moodle_url('/local/ftm_cpurc/index.php'))->out() . '" class="btn btn-primary mt-3">Vai a CPURC Dashboard</a> ';
    echo '<a href="' . (new moodle_url('/local/coachmanager/coach_dashboard_v2.php'))->out() . '" class="btn btn-secondary mt-3">Vai a Coach Dashboard</a>';

    echo $OUTPUT->footer();
    die();
}

// ============================================================
// STEP 1: Upload e anteprima
// ============================================================
echo $OUTPUT->header();

// Process upload.
$preview_rows = null;
$upload_error = '';

if (!empty($_FILES['excelfile']['tmp_name']) && confirm_sesskey()) {
    $tmpfile = $_FILES['excelfile']['tmp_name'];
    $origname = $_FILES['excelfile']['name'];

    $importer = new \local_ftm_cpurc\csv_importer();
    $parsed = $importer->parse_file($tmpfile, $origname);

    if (empty($parsed)) {
        $upload_error = 'Nessuna riga valida trovata nel file. ' . implode('; ', array_map(function($e) {
            return $e['error'];
        }, $importer->get_errors()));
    } else {
        // Deduplicazione: per email, tieni riga con date_start piu' recente.
        $by_email = [];
        foreach ($parsed as $row) {
            $email = strtolower(trim($row['email'] ?? ''));
            if (empty($email)) {
                continue;
            }
            if (!isset($by_email[$email])) {
                $by_email[$email] = $row;
            } else {
                // Tieni quella con date_start piu' recente.
                $existing_date = $by_email[$email]['date_start'] ?? 0;
                $new_date = $row['date_start'] ?? 0;
                if ($new_date > $existing_date) {
                    $by_email[$email] = $row;
                }
            }
        }

        // Arricchisci ogni riga con info preview.
        $preview_rows = [];
        foreach ($by_email as $email => $row) {

            // Cerca utente Moodle.
            $moodle_user = $DB->get_record('user', ['email' => $email, 'deleted' => 0], 'id, firstname, lastname');
            $row['_moodle_found'] = !empty($moodle_user);
            $row['_moodle_userid'] = $moodle_user ? $moodle_user->id : 0;

            // Stato.
            $rawstatus = strtolower(trim($row['status'] ?? ''));
            if (strpos($rawstatus, 'aperto') !== false || empty($rawstatus)) {
                $row['_status'] = 'active';
            } else {
                $row['_status'] = 'closed';
            }

            // Coach match.
            $trainer = strtolower(trim($row['trainer'] ?? ''));
            $row['_coach_name'] = '';
            $row['_coach_userid'] = 0;
            if ($trainer) {
                foreach ($COACH_NAMES as $cname => $cuid) {
                    if (strpos($trainer, $cname) !== false || strpos($cname, $trainer) !== false) {
                        $row['_coach_name'] = ucwords($cname);
                        $row['_coach_userid'] = $cuid ?: 0;
                        break;
                    }
                }
                if (!$row['_coach_name']) {
                    // Prova match parziale (solo cognome).
                    foreach ($COACH_NAMES as $cname => $cuid) {
                        $parts = explode(' ', $cname);
                        $cognome = end($parts);
                        if (strpos($trainer, $cognome) !== false) {
                            $row['_coach_name'] = ucwords($cname);
                            $row['_coach_userid'] = $cuid ?: 0;
                            break;
                        }
                    }
                }
            }

            // Gruppo colore da data inizio.
            $row['_group_color'] = '';
            $row['_group_label'] = '';
            if (!empty($row['date_start']) && $row['_status'] === 'active') {
                $date_str = date('d.m.Y', $row['date_start']);
                if (isset($GROUP_MAP[$date_str])) {
                    $row['_group_color'] = $GROUP_MAP[$date_str];
                    $kw = (int)date('W', $row['date_start']);
                    $row['_group_label'] = ucfirst($GROUP_MAP[$date_str]) . ' KW' . str_pad($kw, 2, '0', STR_PAD_LEFT);
                }
            }

            // Settore.
            $row['_sector'] = \local_ftm_cpurc\profession_mapper::detect_sector($row['last_profession'] ?? '');
            $row['sector_detected'] = $row['_sector'];

            // Warning.
            $row['_warnings'] = [];
            if (!$row['_moodle_found']) {
                $row['_warnings'][] = 'Utente non trovato in Moodle!';
            }
            if ($row['_status'] === 'active' && empty($row['_group_color'])) {
                $date_str = !empty($row['date_start']) ? date('d.m.Y', $row['date_start']) : '(vuota)';
                $row['_warnings'][] = 'Data inizio ' . $date_str . ' non in mapping gruppi';
            }
            if ($row['_status'] === 'active' && !empty($row['trainer']) && empty($row['_coach_userid'])) {
                $row['_warnings'][] = 'Formatore "' . $row['trainer'] . '" non matchato a coach';
            }
            if (empty($row['_sector'])) {
                $row['_warnings'][] = 'Settore non rilevato da "' . ($row['last_profession'] ?? '') . '"';
            }

            $preview_rows[] = $row;
        }

        // Salva in sessione per il confirm (evita hidden field enorme).
        $SESSION->cpurc_import_preview = $preview_rows;
    }
}

?>

<style>
.import-prod-container { max-width: 1400px; margin: 0 auto; }
.upload-zone {
    border: 3px dashed #0066cc;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    background: #f8f9fa;
    cursor: pointer;
    transition: all 0.3s;
    margin-bottom: 20px;
}
.upload-zone:hover, .upload-zone.dragover {
    background: #e3f2fd;
    border-color: #004c99;
}
.upload-zone input[type="file"] { display: none; }
.preview-table { font-size: 0.85em; }
.preview-table th { position: sticky; top: 0; background: #343a40; color: white; z-index: 1; }
.badge-color {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.8em;
    color: #000;
}
.badge-color.giallo { background: #FFFF00; }
.badge-color.grigio { background: #808080; color: white; }
.badge-color.rosso { background: #FF0000; color: white; }
.badge-color.marrone { background: #996633; color: white; }
.badge-color.viola { background: #7030A0; color: white; }
.status-active { color: #28a745; font-weight: 600; }
.status-closed { color: #dc3545; font-weight: 600; }
.warn-cell { background: #fff3cd; }
.err-cell { background: #f8d7da; }
.summary-box {
    display: flex; gap: 15px; flex-wrap: wrap; margin: 15px 0;
}
.summary-box .card {
    flex: 1; min-width: 150px; text-align: center; padding: 15px;
    border-radius: 8px; border: 1px solid #dee2e6;
}
.summary-box .card h4 { margin: 0 0 5px; font-size: 1.8em; }
.summary-box .card p { margin: 0; color: #6c757d; font-size: 0.85em; }
</style>

<div class="import-prod-container">
    <h3>Import Produzione Studenti</h3>
    <?php if (!$COURSE_RCOMP_ID): ?>
        <div class="alert alert-warning"><strong>Attenzione:</strong> Corso "R.comp" non trovato su questo server. L'iscrizione al corso verra' saltata.</div>
    <?php else: ?>
        <div class="alert alert-info" style="padding:8px 15px;font-size:0.9em;">Corso R.comp trovato: id=<?php echo $COURSE_RCOMP_ID; ?></div>
    <?php endif; ?>
    <p class="text-muted">Carica l'Excel CPURC con gli studenti da importare in produzione.
    Gli utenti devono gia' esistere in Moodle. Lo script associa: dati CPURC, corso R.comp, gruppo colore, coach e settore.</p>

    <?php if ($upload_error): ?>
        <div class="alert alert-danger"><?php echo s($upload_error); ?></div>
    <?php endif; ?>

    <?php if (empty($preview_rows)): ?>
    <!-- Upload form -->
    <form method="post" enctype="multipart/form-data" id="uploadForm">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <div class="upload-zone" id="uploadZone" onclick="document.getElementById('excelfile').click();">
            <div style="font-size: 3em; margin-bottom: 10px;">📁</div>
            <div style="font-size: 1.2em; font-weight: 600;">Trascina qui il file Excel oppure clicca per selezionarlo</div>
            <div class="text-muted mt-2">Formati supportati: .xlsx, .xls, .csv</div>
            <input type="file" id="excelfile" name="excelfile" accept=".xlsx,.xls,.csv">
        </div>
        <div id="fileInfo" style="display:none;" class="alert alert-info">
            File selezionato: <strong id="fileName"></strong>
            <button type="submit" class="btn btn-primary ml-3">Carica e Analizza</button>
        </div>
    </form>

    <script>
    (function() {
        var zone = document.getElementById('uploadZone');
        var input = document.getElementById('excelfile');
        var info = document.getElementById('fileInfo');
        var fname = document.getElementById('fileName');

        ['dragenter','dragover'].forEach(function(ev) {
            zone.addEventListener(ev, function(e) { e.preventDefault(); zone.classList.add('dragover'); });
        });
        ['dragleave','drop'].forEach(function(ev) {
            zone.addEventListener(ev, function(e) { e.preventDefault(); zone.classList.remove('dragover'); });
        });
        zone.addEventListener('drop', function(e) {
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                showFile();
            }
        });
        input.addEventListener('change', showFile);

        function showFile() {
            if (input.files.length) {
                fname.textContent = input.files[0].name + ' (' + (input.files[0].size / 1024).toFixed(1) + ' KB)';
                info.style.display = 'block';
            }
        }
    })();
    </script>

    <hr>
    <h5>Mapping Gruppi Colore (Data Inizio)</h5>
    <table class="table table-sm" style="max-width:400px;">
        <thead><tr><th>Data Inizio</th><th>Colore Gruppo</th></tr></thead>
        <tbody>
        <?php foreach ($GROUP_MAP as $date => $color): ?>
            <tr>
                <td><?php echo s($date); ?></td>
                <td><span class="badge-color <?php echo s($color); ?>"><?php echo s(ucfirst($color)); ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php else: ?>
    <!-- Preview + Conferma -->
    <?php
        $cnt_active = count(array_filter($preview_rows, function($r) { return $r['_status'] === 'active'; }));
        $cnt_closed = count($preview_rows) - $cnt_active;
        $cnt_found = count(array_filter($preview_rows, function($r) { return $r['_moodle_found']; }));
        $cnt_notfound = count($preview_rows) - $cnt_found;
        $cnt_warnings = count(array_filter($preview_rows, function($r) { return !empty($r['_warnings']); }));
    ?>

    <div class="summary-box">
        <div class="card">
            <h4><?php echo count($preview_rows); ?></h4>
            <p>Studenti totali (dopo dedup)</p>
        </div>
        <div class="card" style="border-color: #28a745;">
            <h4 style="color:#28a745;"><?php echo $cnt_active; ?></h4>
            <p>Attivi (Aperto)</p>
        </div>
        <div class="card" style="border-color: #dc3545;">
            <h4 style="color:#dc3545;"><?php echo $cnt_closed; ?></h4>
            <p>Chiusi (Interrotto/Annullato)</p>
        </div>
        <div class="card" style="border-color: <?php echo $cnt_notfound ? '#dc3545' : '#28a745'; ?>;">
            <h4><?php echo $cnt_found; ?>/<?php echo count($preview_rows); ?></h4>
            <p>Trovati in Moodle</p>
        </div>
        <div class="card" style="border-color: <?php echo $cnt_warnings ? '#ffc107' : '#28a745'; ?>;">
            <h4 style="color:<?php echo $cnt_warnings ? '#ffc107' : '#28a745'; ?>;"><?php echo $cnt_warnings; ?></h4>
            <p>Con warning</p>
        </div>
    </div>

    <div style="overflow-x: auto;">
    <table class="table table-sm table-bordered preview-table">
        <thead class="thead-dark">
            <tr>
                <th>#</th>
                <th>Nome</th>
                <th>Cognome</th>
                <th>Email</th>
                <th>Moodle</th>
                <th>Stato</th>
                <th>Data Inizio</th>
                <th>Gruppo</th>
                <th>Formatore</th>
                <th>Coach</th>
                <th>Professione</th>
                <th>Settore</th>
                <th>Warning</th>
            </tr>
        </thead>
        <tbody>
        <?php $i = 0; foreach ($preview_rows as $row): $i++; ?>
            <?php
                $has_error = !$row['_moodle_found'];
                $has_warn = !empty($row['_warnings']);
                $trclass = $has_error ? 'table-danger' : ($has_warn ? 'table-warning' : '');
            ?>
            <tr class="<?php echo $trclass; ?>">
                <td><?php echo $i; ?></td>
                <td><?php echo s($row['firstname']); ?></td>
                <td><?php echo s($row['lastname']); ?></td>
                <td><?php echo s($row['email']); ?></td>
                <td>
                    <?php if ($row['_moodle_found']): ?>
                        <span class="text-success" title="userid=<?php echo $row['_moodle_userid']; ?>">Trovato</span>
                    <?php else: ?>
                        <span class="text-danger font-weight-bold">NON TROVATO</span>
                    <?php endif; ?>
                </td>
                <td class="<?php echo $row['_status'] === 'active' ? 'status-active' : 'status-closed'; ?>">
                    <?php echo $row['_status'] === 'active' ? 'Aperto' : 'Chiuso'; ?>
                    <small class="text-muted d-block"><?php echo s($row['status'] ?? ''); ?></small>
                </td>
                <td><?php echo !empty($row['date_start']) ? date('d.m.Y', $row['date_start']) : '-'; ?></td>
                <td>
                    <?php if ($row['_group_color']): ?>
                        <span class="badge-color <?php echo s($row['_group_color']); ?>"><?php echo s($row['_group_label']); ?></span>
                    <?php elseif ($row['_status'] === 'active'): ?>
                        <span class="text-warning">Non mappato</span>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
                <td><?php echo s($row['trainer'] ?? ''); ?></td>
                <td>
                    <?php if ($row['_coach_name']): ?>
                        <span class="text-success"><?php echo s($row['_coach_name']); ?></span>
                        <?php if (!$row['_coach_userid']): ?>
                            <small class="text-warning d-block">userid non trovato</small>
                        <?php endif; ?>
                    <?php elseif ($row['_status'] === 'active' && !empty($row['trainer'])): ?>
                        <span class="text-warning">Non matchato</span>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
                <td><small><?php echo s($row['last_profession'] ?? ''); ?></small></td>
                <td>
                    <?php if ($row['_sector']): ?>
                        <span class="badge badge-info"><?php echo s($row['_sector']); ?></span>
                    <?php else: ?>
                        <span class="text-warning">?</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($row['_warnings'])): ?>
                        <?php foreach ($row['_warnings'] as $w): ?>
                            <small class="text-warning d-block"><?php echo s($w); ?></small>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- Confirm form -->
    <form method="post">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="action" value="confirm">

        <div class="mt-3 mb-3">
            <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('Confermi l\'import di <?php echo count($preview_rows); ?> studenti?');">
                Conferma Import (<?php echo count($preview_rows); ?> studenti)
            </button>
            <a href="<?php echo (new moodle_url('/local/ftm_cpurc/import_production.php'))->out(); ?>" class="btn btn-secondary btn-lg ml-2">
                Annulla e ricarica
            </a>
        </div>
    </form>

    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
