<?php
/**
 * FTM Test Suite - Risolvi Punteggi LabEval
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/ftm_testsuite:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/fix_labeval.php'));
$PAGE->set_title('Correggi Punteggi LabEval - FTM Test Suite');
$PAGE->set_heading('Correggi Punteggi LabEval');
$PAGE->set_pagelayout('admin');

// Parametri
$action = optional_param('fts_action', '', PARAM_ALPHA);
$sessionids = optional_param_array('fts_sessionids', [], PARAM_INT);

global $DB;

$message = '';
$messagetype = '';

// Esegui azione se richiesta
if ($action === 'recalculate' && confirm_sesskey() && !empty($sessionids)) {
    $fixed = 0;

    foreach ($sessionids as $sid) {
        // Calcola punteggio corretto da comp_scores (include i pesi!)
        $calc = $DB->get_record_sql("
            SELECT SUM(cs.score) as total_score,
                   SUM(cs.maxscore) as max_score
            FROM {local_labeval_comp_scores} cs
            WHERE cs.sessionid = ?
        ", [$sid]);

        if ($calc && $calc->max_score > 0) {
            $percentage = round(($calc->total_score / $calc->max_score) * 100, 2);

            $DB->update_record('local_labeval_sessions', (object)[
                'id' => $sid,
                'totalscore' => $calc->total_score,
                'maxscore' => $calc->max_score,
                'percentage' => $percentage
            ]);
            $fixed++;
        }
    }

    $message = "✅ Ricalcolati punteggi per {$fixed} sessioni!";
    $messagetype = 'success';
}

if ($action === 'delete' && confirm_sesskey() && !empty($sessionids)) {
    $deleted = 0;
    
    foreach ($sessionids as $sid) {
        // Elimina ratings
        $DB->delete_records('local_labeval_ratings', ['sessionid' => $sid]);
        // Elimina comp_scores
        $DB->delete_records('local_labeval_comp_scores', ['sessionid' => $sid]);
        // Elimina sessione
        $DB->delete_records('local_labeval_sessions', ['id' => $sid]);
        $deleted++;
    }
    
    $message = "✅ Eliminate {$deleted} sessioni!";
    $messagetype = 'success';
}

// Trova sessioni con punteggio errato
// Confronta i totali salvati nella sessione con la somma dei comp_scores (che include i pesi)
$inconsistent_sessions = $DB->get_records_sql("
    SELECT s.id, s.assignmentid, s.status, s.totalscore as saved_score,
           s.maxscore as saved_maxscore, s.percentage as saved_percentage,
           s.timecreated, s.timecompleted,
           a.studentid, a.templateid,
           u.firstname, u.lastname, u.username,
           t.name as templatename,
           (SELECT SUM(cs.score) FROM {local_labeval_comp_scores} cs WHERE cs.sessionid = s.id) as calc_score,
           (SELECT SUM(cs.maxscore) FROM {local_labeval_comp_scores} cs WHERE cs.sessionid = s.id) as calc_maxscore,
           (SELECT COUNT(cs.id) FROM {local_labeval_comp_scores} cs WHERE cs.sessionid = s.id) as comp_count,
           (SELECT COUNT(r.id) FROM {local_labeval_ratings} r WHERE r.sessionid = s.id) as rating_count
    FROM {local_labeval_sessions} s
    JOIN {local_labeval_assignments} a ON a.id = s.assignmentid
    JOIN {user} u ON u.id = a.studentid
    JOIN {local_labeval_templates} t ON t.id = a.templateid
    WHERE s.status = 'completed'
    GROUP BY s.id, s.assignmentid, s.status, s.totalscore, s.maxscore, s.percentage,
             s.timecreated, s.timecompleted, a.studentid, a.templateid,
             u.firstname, u.lastname, u.username, t.name
    HAVING ABS(COALESCE(saved_score, 0) - COALESCE(calc_score, 0)) > 0.01
    ORDER BY s.timecreated DESC
");

// Calcola differenza per ogni sessione
foreach ($inconsistent_sessions as &$s) {
    $s->diff = abs(($s->saved_score ?? 0) - ($s->calc_score ?? 0));
    $s->calc_percentage = $s->calc_maxscore > 0 ? round(($s->calc_score / $s->calc_maxscore) * 100, 2) : 0;
}

$total_inconsistent = count($inconsistent_sessions);

echo $OUTPUT->header();
?>

<style>
.fix-container { max-width: 1200px; margin: 0 auto; }
.fix-header {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
}

.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}
.card-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-header h3 { margin: 0; font-size: 16px; }
.card-body { padding: 0; }

.stats-bar {
    display: flex;
    gap: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #f8d7da, #f5c6cb);
    border-radius: 8px;
    margin-bottom: 20px;
}
.stats-bar.success { background: linear-gradient(135deg, #d4edda, #c3e6cb); }
.stat-item { text-align: center; }
.stat-item .number { font-size: 32px; font-weight: 700; color: #721c24; }
.stats-bar.success .stat-item .number { color: #155724; }
.stat-item .label { font-size: 13px; color: #666; }

.session-table {
    width: 100%;
    border-collapse: collapse;
}
.session-table th, .session-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}
.session-table th { background: #f8f9fa; font-weight: 600; font-size: 13px; }
.session-table tr:hover { background: #f8f9fa; }

.score-comparison {
    display: flex;
    gap: 10px;
    align-items: center;
}
.score-box {
    padding: 5px 10px;
    border-radius: 4px;
    font-family: monospace;
    font-weight: 600;
}
.score-box.saved { background: #f8d7da; color: #721c24; }
.score-box.calc { background: #d4edda; color: #155724; }
.score-box.diff { background: #fff3cd; color: #856404; }

.detail-link {
    font-size: 12px;
    color: #1e3c72;
    text-decoration: none;
}
.detail-link:hover { text-decoration: underline; }

.action-buttons {
    display: flex;
    gap: 10px;
    padding: 20px;
    background: #f8f9fa;
    border-top: 1px solid #eee;
}

.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    border: none;
}
.btn-success { background: #28a745; color: white; }
.btn-danger { background: #dc3545; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-sm { padding: 5px 12px; font-size: 12px; }

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.alert-success { background: #d4edda; color: #155724; }
.alert-info { background: #d1ecf1; color: #0c5460; }
.alert-warning { background: #fff3cd; color: #856404; }

.detail-panel {
    display: none;
    background: #f8f9fa;
    padding: 15px;
    border-top: 2px solid #dee2e6;
}
.detail-panel.show { display: block; }
.rating-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
}
.rating-item {
    background: white;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
}
.rating-item .behavior { font-weight: 600; font-size: 13px; }
.rating-item .value { 
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 600;
    margin-top: 5px;
}
.rating-0 { background: #e9ecef; color: #6c757d; }
.rating-1 { background: #fff3cd; color: #856404; }
.rating-3 { background: #d4edda; color: #155724; }
</style>

<div class="fix-container">
    
    <div class="fix-header">
        <h1>🔬 Correggi Punteggi LabEval</h1>
        <p>Trova e correggi sessioni con punteggi inconsistenti</p>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messagetype; ?>">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <!-- Statistiche -->
    <div class="stats-bar <?php echo $total_inconsistent == 0 ? 'success' : ''; ?>">
        <div class="stat-item">
            <div class="number"><?php echo $total_inconsistent; ?></div>
            <div class="label">Sessioni Inconsistenti</div>
        </div>
    </div>
    
    <?php if ($total_inconsistent == 0): ?>
    <div class="alert alert-success">
        ✅ Tutte le sessioni LabEval hanno punteggi corretti.
    </div>
    <?php else: ?>
    
    <div class="alert alert-warning">
        ⚠️ <strong>Attenzione:</strong> Queste sessioni hanno un punteggio salvato diverso dalla somma dei ratings.
        Questo può indicare un bug nel salvataggio o dati corrotti.
    </div>
    
    <form method="post" action="" id="fixForm">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        
        <div class="card">
            <div class="card-header">
                <h3>📋 Sessioni da Correggere</h3>
                <div>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="selectAll(true)">
                        ☑️ Seleziona Tutto
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="selectAll(false)">
                        ☐ Deseleziona
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table class="session-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th>
                            <th>ID</th>
                            <th>Studente</th>
                            <th>Template</th>
                            <th>Data</th>
                            <th>Punteggio Salvato</th>
                            <th>Punteggio Calcolato</th>
                            <th>Differenza</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inconsistent_sessions as $s): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="fts_sessionids[]" 
                                       value="<?php echo $s->id; ?>" class="session-cb">
                            </td>
                            <td><strong>#<?php echo $s->id; ?></strong></td>
                            <td>
                                <?php echo $s->firstname . ' ' . $s->lastname; ?>
                                <br><small style="color: #666;"><?php echo $s->username; ?></small>
                            </td>
                            <td><?php echo $s->templatename; ?></td>
                            <td><?php echo userdate($s->timecreated, '%d/%m/%Y'); ?></td>
                            <td>
                                <span class="score-box saved">
                                    <?php echo $s->saved_score ?? 0; ?> / <?php echo $s->saved_maxscore ?? 0; ?>
                                    (<?php echo $s->saved_percentage ?? 0; ?>%)
                                </span>
                            </td>
                            <td>
                                <span class="score-box calc">
                                    <?php echo $s->calc_score ?? 0; ?> / <?php echo $s->calc_maxscore ?? 0; ?>
                                    (<?php echo $s->calc_percentage; ?>%)
                                </span>
                            </td>
                            <td>
                                <span class="score-box diff">Δ <?php echo $s->diff; ?></span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-secondary" 
                                        onclick="toggleDetail(<?php echo $s->id; ?>)">
                                    🔍 Dettagli
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="9" style="padding: 0;">
                                <div id="detail_<?php echo $s->id; ?>" class="detail-panel">
                                    <div style="margin-bottom: 15px;">
                                        <strong>📊 Punteggi Competenze (<?php echo $s->comp_count ?? 0; ?> competenze):</strong>
                                        <p style="font-size: 12px; color: #666; margin: 5px 0;">
                                            I totali sono calcolati dalla somma dei punteggi per competenza (che includono i pesi).
                                        </p>
                                        <div class="rating-grid" style="margin-top: 10px;">
                                            <?php
                                            $comp_scores = $DB->get_records_sql("
                                                SELECT cs.id, cs.competencycode, cs.score, cs.maxscore, cs.percentage
                                                FROM {local_labeval_comp_scores} cs
                                                WHERE cs.sessionid = ?
                                                ORDER BY cs.competencycode
                                            ", [$s->id]);
                                            foreach ($comp_scores as $cs):
                                            ?>
                                            <div class="rating-item">
                                                <div class="behavior" style="font-size: 11px;"><?php echo $cs->competencycode; ?></div>
                                                <span class="value" style="background: #e3f2fd; color: #1565c0;">
                                                    <?php echo $cs->score; ?> / <?php echo $cs->maxscore; ?>
                                                    (<?php echo round($cs->percentage, 1); ?>%)
                                                </span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <hr style="border: none; border-top: 1px solid #ddd; margin: 15px 0;">
                                    <strong>📝 Ratings grezzi (<?php echo $s->rating_count; ?> comportamenti):</strong>
                                    <p style="font-size: 12px; color: #666; margin: 5px 0;">
                                        Valori base senza pesi. Usati per calcolare i punteggi competenze.
                                    </p>
                                    <div class="rating-grid" style="margin-top: 10px;">
                                        <?php
                                        $ratings = $DB->get_records_sql("
                                            SELECT r.id, r.rating, b.description
                                            FROM {local_labeval_ratings} r
                                            JOIN {local_labeval_behaviors} b ON b.id = r.behaviorid
                                            WHERE r.sessionid = ?
                                            ORDER BY b.sortorder
                                        ", [$s->id]);
                                        foreach ($ratings as $r):
                                        ?>
                                        <div class="rating-item">
                                            <div class="behavior"><?php echo substr($r->description, 0, 50); ?>...</div>
                                            <span class="value rating-<?php echo $r->rating; ?>">
                                                <?php echo $r->rating == 0 ? 'Non osservato' : ($r->rating == 1 ? 'Parziale' : 'Adeguato'); ?>
                                                (<?php echo $r->rating; ?>)
                                            </span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="action-buttons">
                <button type="submit" name="fts_action" value="recalculate" class="btn btn-success"
                        onclick="return confirm('Ricalcolare i punteggi per le sessioni selezionate?')">
                    ✅ Ricalcola Punteggi Selezionati
                </button>
                <button type="submit" name="fts_action" value="delete" class="btn btn-danger"
                        onclick="return confirm('ATTENZIONE: Eliminare le sessioni selezionate? Questa azione non può essere annullata!')">
                    🗑️ Elimina Sessioni Selezionate
                </button>
            </div>
        </div>
        
    </form>
    
    <?php endif; ?>
    
    <!-- Link -->
    <div style="text-align: center; margin-top: 25px;">
        <a href="fix.php" class="btn btn-secondary">← Centro Risoluzione</a>
        <a href="run.php" class="btn btn-secondary" style="margin-left: 10px;">▶️ Riesegui Test</a>
    </div>
    
</div>

<script>
function selectAll(checked) {
    document.querySelectorAll('.session-cb').forEach(function(cb) {
        cb.checked = checked;
    });
}

function toggleDetail(id) {
    var panel = document.getElementById('detail_' + id);
    panel.classList.toggle('show');
}
</script>

<?php
echo $OUTPUT->footer();
