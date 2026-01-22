<?php
/**
 * FTM Design System Preview
 *
 * Pagina di anteprima per testare tutti i componenti del design system.
 * Usa ?design=new per vedere il nuovo design, altrimenti vedi il classico.
 *
 * @package    local_ftm_common
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/design_helper.php');

use local_ftm_common\design_helper;

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_common/preview.php'));
$PAGE->set_title('FTM Design System Preview');
$PAGE->set_heading('FTM Design System Preview');

// Carica il design system se richiesto
$is_new_design = design_helper::load_design($PAGE);

echo $OUTPUT->header();

// Toggle button fisso in basso a destra
echo design_helper::render_toggle_button($PAGE->url);

$design_label = $is_new_design ? '🎨 NUOVO DESIGN ATTIVO' : '📋 DESIGN CLASSICO';
$bg_class = $is_new_design ? 'ftm-page-bg' : '';
?>

<div class="<?php echo $bg_class; ?>" style="<?php echo !$is_new_design ? 'padding: 20px;' : ''; ?>">
<div class="<?php echo $is_new_design ? 'ftm-container' : ''; ?>" style="max-width: 1200px; margin: 0 auto;">

    <!-- Status Banner -->
    <div style="background: <?php echo $is_new_design ? 'linear-gradient(135deg, #28a745, #20c997)' : '#6c757d'; ?>; color: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
        <strong><?php echo $design_label; ?></strong>
        <br><small>Aggiungi <code>?design=new</code> o <code>?design=old</code> all'URL per switchare</small>
    </div>

    <!-- HEADER -->
    <h2>1. Header</h2>
    <?php echo design_helper::render_header('Dashboard FTM', 'Panoramica delle attività'); ?>

    <!-- USER CARD -->
    <h2 style="margin-top: 30px;">2. User Card</h2>
    <?php if ($is_new_design): ?>
    <div class="ftm-user-card">
        <div class="ftm-user-card-left">
            <div class="ftm-user-avatar">CB</div>
            <div class="ftm-user-info">
                <h2>[TEST] Studente Eccellente 95%</h2>
                <p>ftm_test_high95@test.local</p>
                <?php echo design_helper::render_badge('Autovalutazione abilitata', 'success'); ?>
            </div>
        </div>
        <div class="ftm-user-card-right">
            <div class="ftm-user-stat-big">84%</div>
            <div class="ftm-user-stat-label">Media Autovalutazione</div>
        </div>
    </div>
    <?php else: ?>
    <div style="background: #1a5a5a; color: white; padding: 20px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <strong>[TEST] Studente Eccellente 95%</strong><br>
            <small>ftm_test_high95@test.local</small>
        </div>
        <div style="text-align: right;">
            <strong style="font-size: 32px;">84%</strong><br>
            <small>Media Autovalutazione</small>
        </div>
    </div>
    <?php endif; ?>

    <!-- STATS CARDS -->
    <h2 style="margin-top: 30px;">3. Stats Cards</h2>
    <?php if ($is_new_design): ?>
    <div class="ftm-stats-grid">
        <?php
        echo design_helper::render_stat_card('100', 'Competenze Valutate', '📊', 'yellow');
        echo design_helper::render_stat_card('5.1/6', 'Livello Medio', '📈', 'green');
        echo design_helper::render_stat_card('128', 'Competenze Assegnate', '📋', 'blue');
        echo design_helper::render_stat_card('38', 'Test Passati', '✅', 'teal');
        echo design_helper::render_stat_card('3', 'Warning', '⚠️', 'orange');
        echo design_helper::render_stat_card('0', 'Falliti', '❌', 'red');
        ?>
    </div>
    <?php else: ?>
    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
        <?php
        echo design_helper::render_stat_card('100', 'Competenze Valutate', '📊', 'yellow');
        echo design_helper::render_stat_card('5.1/6', 'Livello Medio', '📈', 'green');
        echo design_helper::render_stat_card('128', 'Competenze Assegnate', '📋', 'blue');
        ?>
    </div>
    <?php endif; ?>

    <!-- PROGRESS BAR -->
    <h2 style="margin-top: 30px;">4. Progress Bar</h2>
    <?php echo design_helper::render_progress(92.68, '92.68% Test Passati', 'success', true); ?>
    <div style="height: 15px;"></div>
    <?php echo design_helper::render_progress(70.7, '70.7% Copertura', 'warning', false); ?>
    <div style="height: 15px;"></div>
    <?php echo design_helper::render_progress(25, '25% Critico', 'danger', false); ?>

    <!-- BADGES -->
    <h2 style="margin-top: 30px;">5. Badges</h2>
    <p>
        <?php
        echo design_helper::render_badge('Success', 'success') . ' ';
        echo design_helper::render_badge('Warning', 'warning') . ' ';
        echo design_helper::render_badge('Danger', 'danger') . ' ';
        echo design_helper::render_badge('Info', 'info') . ' ';
        echo design_helper::render_badge('Primary', 'primary') . ' ';
        echo design_helper::render_badge('Teal', 'teal');
        ?>
    </p>

    <!-- ALERTS -->
    <h2 style="margin-top: 30px;">6. Alerts</h2>
    <?php
    echo design_helper::render_alert('✅ Operazione completata con successo!', 'success');
    echo design_helper::render_alert('⚠️ Attenzione: alcuni dati potrebbero essere incompleti.', 'warning');
    echo design_helper::render_alert('❌ Errore durante il salvataggio dei dati.', 'danger');
    echo design_helper::render_alert('ℹ️ Informazione: il sistema sarà in manutenzione domani.', 'info');
    ?>

    <!-- BUTTONS -->
    <h2 style="margin-top: 30px;">7. Buttons</h2>
    <?php if ($is_new_design): ?>
    <p>
        <button class="ftm-btn ftm-btn-primary">Primario</button>
        <button class="ftm-btn ftm-btn-secondary">Secondario</button>
        <button class="ftm-btn ftm-btn-success">Successo</button>
        <button class="ftm-btn ftm-btn-danger">Pericolo</button>
        <button class="ftm-btn ftm-btn-teal">Teal</button>
        <button class="ftm-btn ftm-btn-outline">Outline</button>
    </p>
    <p>
        <button class="ftm-btn ftm-btn-primary ftm-btn-lg">Grande</button>
        <button class="ftm-btn ftm-btn-primary">Normale</button>
        <button class="ftm-btn ftm-btn-primary ftm-btn-sm">Piccolo</button>
    </p>
    <?php else: ?>
    <p>
        <button style="background: #f5a623; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">Primario</button>
        <button style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">Secondario</button>
        <button style="background: #28a745; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">Successo</button>
    </p>
    <?php endif; ?>

    <!-- CARD -->
    <h2 style="margin-top: 30px;">8. Card</h2>
    <?php if ($is_new_design): ?>
    <div class="ftm-card">
        <div class="ftm-card-header">
            <h3>Titolo Card</h3>
            <?php echo design_helper::render_badge('Nuovo', 'primary'); ?>
        </div>
        <div class="ftm-card-body">
            <p>Questo è il contenuto della card. Può contenere qualsiasi tipo di contenuto HTML.</p>
            <table class="ftm-table">
                <thead>
                    <tr>
                        <th>Test</th>
                        <th>Stato</th>
                        <th>Tempo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Test 1.1 - Domande con competenze</td>
                        <td><span class="ftm-status ftm-status-passed">✓</span></td>
                        <td>42.8 ms</td>
                    </tr>
                    <tr>
                        <td>Test 1.8 - Parsing idnumber</td>
                        <td><span class="ftm-status ftm-status-warning">!</span></td>
                        <td>1.0 ms</td>
                    </tr>
                    <tr>
                        <td>Test 3.6 - Calcolo punteggio</td>
                        <td><span class="ftm-status ftm-status-passed">✓</span></td>
                        <td>7.0 ms</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="ftm-card-footer">
            <button class="ftm-btn ftm-btn-primary ftm-btn-sm">Azione</button>
        </div>
    </div>
    <?php else: ?>
    <div style="background: white; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden;">
        <div style="padding: 15px; border-bottom: 1px solid #dee2e6; background: #f8f9fa;">
            <strong>Titolo Card</strong>
        </div>
        <div style="padding: 15px;">
            <p>Contenuto della card nel design classico.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- STATUS INDICATORS -->
    <h2 style="margin-top: 30px;">9. Status Indicators</h2>
    <?php if ($is_new_design): ?>
    <p>
        <span class="ftm-status ftm-status-passed">✓</span> Passato
        <span class="ftm-status ftm-status-failed">✗</span> Fallito
        <span class="ftm-status ftm-status-warning">!</span> Warning
        <span class="ftm-status ftm-status-skipped">–</span> Saltato
    </p>
    <?php else: ?>
    <p>
        <span style="display: inline-block; width: 24px; height: 24px; background: #d4edda; color: #28a745; border-radius: 50%; text-align: center; line-height: 24px;">✓</span> Passato
        <span style="display: inline-block; width: 24px; height: 24px; background: #f8d7da; color: #dc3545; border-radius: 50%; text-align: center; line-height: 24px;">✗</span> Fallito
    </p>
    <?php endif; ?>

    <div style="margin-top: 40px; padding: 20px; background: #e9ecef; border-radius: 8px;">
        <h3>Come Usare</h3>
        <ol>
            <li>Carica la cartella <code>local/ftm_common</code> sul server</li>
            <li>Visita <code>/admin/index.php</code> per installare il plugin</li>
            <li>In ogni pagina FTM, aggiungi all'inizio:
                <pre style="background: #212529; color: #f8f9fa; padding: 10px; border-radius: 4px; margin-top: 10px;">require_once($CFG->dirroot . '/local/ftm_common/classes/design_helper.php');
use local_ftm_common\design_helper;
design_helper::load_design($PAGE);</pre>
            </li>
            <li>Usa <code>?design=new</code> per attivare il nuovo design</li>
            <li>Se non ti piace, togli il parametro e torna al classico!</li>
        </ol>
    </div>

</div>
</div>

<?php
echo $OUTPUT->footer();
