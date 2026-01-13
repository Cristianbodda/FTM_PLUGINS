<?php
/**
 * Script per generare un'autovalutazione di TEST
 * Crea automaticamente la tabella se non esiste
 * 
 * USO: Accedi a questo file dal browser con i parametri:
 * /local/competencymanager/generate_test_assessment.php?userid=XXX&courseid=YYY
 * 
 * IMPORTANTE: Elimina questo file dopo il test!
 * 
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/report_generator.php');

require_login();

// Solo admin può usare questo script
if (!is_siteadmin()) {
    die('❌ Solo gli amministratori possono usare questo script');
}

$userid = required_param('userid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);

$student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
$course = get_course($courseid);

// ============================================
// CREA LA TABELLA SE NON ESISTE
// ============================================
$dbman = $DB->get_manager();
$tablename = 'local_coachmanager_assessment';

if (!$dbman->table_exists($tablename)) {
    // Crea la tabella
    $table = new xmldb_table($tablename);
    
    // Aggiungi i campi
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('assessmenttype', XMLDB_TYPE_CHAR, '50', null, null, null, 'comprehensive');
    $table->add_field('details', XMLDB_TYPE_TEXT, null, null, null, null, null);
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    
    // Aggiungi la chiave primaria
    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    
    // Aggiungi gli indici
    $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
    $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
    $table->add_index('userid_courseid', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
    
    // Crea la tabella
    $dbman->create_table($table);
    
    $tableCreated = true;
} else {
    $tableCreated = false;
}

// Recupera le competenze del corso dai quiz
$radardata = \local_competencymanager\report_generator::get_radar_chart_data($userid, $courseid);
$competencies = $radardata['competencies'];

if (empty($competencies)) {
    die('❌ Nessuna competenza trovata per questo studente/corso');
}

// Verifica se esiste già un'autovalutazione
$existing = $DB->get_record('local_coachmanager_assessment', [
    'userid' => $userid,
    'courseid' => $courseid
]);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/competencymanager/generate_test_assessment.php'));
$PAGE->set_title('Genera Autovalutazione Test');

echo $OUTPUT->header();
?>

<style>
    .container { max-width: 900px; margin: 0 auto; padding: 20px; }
    .card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
    th { background: #f8f9fa; }
    .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; margin-right: 10px; text-decoration: none; display: inline-block; }
    .btn-primary { background: #667eea; color: white; }
    .btn-danger { background: #dc3545; color: white; }
    .btn-success { background: #28a745; color: white; }
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    .alert-warning { background: #fff3cd; border: 1px solid #ffc107; }
    .alert-success { background: #d4edda; border: 1px solid #28a745; }
    .alert-info { background: #d1ecf1; border: 1px solid #17a2b8; }
    .bloom-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; color: white; font-weight: bold; }
    select { padding: 8px; border-radius: 4px; border: 1px solid #ddd; width: 100%; }
</style>

<div class="container">
    <div class="header">
        <h1>🧪 Genera Autovalutazione di TEST</h1>
        <p>Studente: <strong><?php echo fullname($student); ?></strong></p>
        <p>Corso: <strong><?php echo format_string($course->fullname); ?></strong></p>
    </div>

    <?php if ($tableCreated): ?>
    <div class="alert alert-success">
        ✅ <strong>Tabella creata!</strong> La tabella <code>local_coachmanager_assessment</code> è stata creata con successo nel database.
    </div>
    <?php endif; ?>

    <?php if ($existing): ?>
    <div class="alert alert-warning">
        ⚠️ <strong>Attenzione:</strong> Esiste già un'autovalutazione per questo studente!
        <br>Data: <?php echo userdate($existing->timecreated); ?>
        <br><br>
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="userid" value="<?php echo $userid; ?>">
            <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Sei sicuro di voler eliminare l\'autovalutazione esistente?')">🗑️ Elimina Esistente</button>
        </form>
    </div>
    <?php endif; ?>

    <?php
    // Gestione azioni POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
        $postAction = required_param('action', PARAM_ALPHA);
        
        if ($postAction === 'delete' && $existing) {
            $DB->delete_records('local_coachmanager_assessment', ['id' => $existing->id]);
            echo '<div class="alert alert-success">✅ Autovalutazione eliminata! <a href="?userid='.$userid.'&courseid='.$courseid.'">Ricarica pagina</a></div>';
            $existing = null;
        }
        
        if ($postAction === 'create') {
            // Genera autovalutazione
            $assessmentData = [];
            
            foreach ($competencies as $comp) {
                $code = $comp['idnumber'] ?: $comp['name'];
                $bloomLevel = required_param('bloom_' . md5($code), PARAM_INT);
                
                $assessmentData[] = [
                    'idnumber' => $code,
                    'name' => $comp['description'] ?? $code,
                    'bloom_level' => $bloomLevel
                ];
            }
            
            $record = new stdClass();
            $record->userid = $userid;
            $record->courseid = $courseid;
            $record->assessmenttype = 'comprehensive';
            $record->details = json_encode([
                'competencies' => $assessmentData,
                'generated' => 'test_script',
                'timestamp' => time()
            ]);
            $record->timecreated = time();
            $record->timemodified = time();
            
            $newid = $DB->insert_record('local_coachmanager_assessment', $record);
            
            echo '<div class="alert alert-success">';
            echo '✅ <strong>Autovalutazione creata con successo!</strong> (ID: '.$newid.')<br><br>';
            echo '👉 <a href="' . new moodle_url('/local/competencymanager/student_report.php', ['userid' => $userid, 'courseid' => $courseid]) . '" class="btn btn-primary">Vai al Report Studente</a>';
            echo '</div>';
        }
    }
    ?>

    <?php if (!$existing || $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
    <div class="card">
        <h3>📋 Competenze trovate: <?php echo count($competencies); ?></h3>
        <p>Imposta il livello Bloom per ogni competenza (simula come lo studente si autovaluta):</p>
        
        <form method="post">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="userid" value="<?php echo $userid; ?>">
            <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            
            <table>
                <thead>
                    <tr>
                        <th style="width: 40%;">Codice Competenza</th>
                        <th style="width: 20%;">Performance Reale</th>
                        <th style="width: 40%;">Autovalutazione (Bloom 1-6)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($competencies as $comp): 
                        $code = $comp['idnumber'] ?: $comp['name'];
                        $realPerc = $comp['percentage'];
                        
                        // Genera un livello Bloom "realistico" con variazione
                        $realBloom = round(($realPerc / 100) * 6);
                        $variation = rand(-2, 2);
                        $suggestedBloom = max(1, min(6, $realBloom + $variation));
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo $code; ?></strong><br>
                            <small style="color: #666;"><?php echo substr($comp['description'] ?? '', 0, 60); ?></small>
                        </td>
                        <td style="text-align: center;">
                            <span class="bloom-badge" style="background: <?php echo $realPerc >= 60 ? '#28a745' : ($realPerc >= 40 ? '#ffc107' : '#dc3545'); ?>">
                                <?php echo $realPerc; ?>%
                            </span>
                        </td>
                        <td>
                            <select name="bloom_<?php echo md5($code); ?>">
                                <?php for ($i = 1; $i <= 6; $i++): 
                                    $bloomLabels = [
                                        1 => '1 - Ricordare (17%)',
                                        2 => '2 - Comprendere (33%)', 
                                        3 => '3 - Applicare (50%)',
                                        4 => '4 - Analizzare (67%)',
                                        5 => '5 - Valutare (83%)',
                                        6 => '6 - Creare (100%)'
                                    ];
                                ?>
                                <option value="<?php echo $i; ?>" <?php echo $i == $suggestedBloom ? 'selected' : ''; ?>>
                                    <?php echo $bloomLabels[$i]; ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px; text-align: center;">
                <button type="submit" class="btn btn-success" style="padding: 15px 40px; font-size: 16px;">
                    ✅ Genera Autovalutazione di Test
                </button>
            </div>
        </form>
    </div>
    
    <div class="alert alert-info">
        <strong>💡 Suggerimento:</strong> Per testare il Gap Analysis, imposta alcuni livelli Bloom 
        più alti della performance reale (sopravvalutazione) e alcuni più bassi (sottovalutazione).
        <br><br>
        Esempio: Se la performance reale è 80%, prova a impostare Bloom 3 (50%) per vedere una sottovalutazione,
        oppure Bloom 6 (100%) per vedere una sopravvalutazione.
    </div>
    <?php endif; ?>
    
    <div class="card" style="background: #fff3cd;">
        <h4>⚠️ IMPORTANTE - DOPO IL TEST</h4>
        <p>Questo script è solo per <strong>TEST</strong>. Dopo aver testato il sistema:</p>
        <ol>
            <li><strong>Elimina questo file</strong> dal server (<code>generate_test_assessment.php</code>)</li>
            <li>Oppure elimina l'autovalutazione di test dal database</li>
        </ol>
    </div>
</div>

<?php
echo $OUTPUT->footer();
