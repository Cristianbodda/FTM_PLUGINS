<?php
/**
 * Agent Tests Runner - Interfaccia web per eseguire i test degli agenti
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_common/classes/design_helper.php');

use local_ftm_common\design_helper;

require_login();

$context = context_system::instance();
require_capability('local/ftm_testsuite:execute', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/agent_tests.php'));
$PAGE->set_title('FTM Test Suite - Agenti di Test');
$PAGE->set_heading('Agenti di Test Moodle');
$PAGE->set_pagelayout('admin');

// Carica FTM Design System
$is_new_design = design_helper::load_design($PAGE);

// Carica classi agenti
require_once(__DIR__ . '/classes/agents/base_agent.php');
require_once(__DIR__ . '/classes/agents/agent_runner.php');

// Parametri
$action = optional_param('action', '', PARAM_ALPHA);
$agent_id = optional_param('agent', '', PARAM_ALPHANUMEXT);
$category = optional_param('category', '', PARAM_ALPHA);

// Crea runner
$runner = new \local_ftm_testsuite\agents\agent_runner();
$agents = $runner->get_available_agents();
$by_category = $runner->get_agents_by_category();

// Esegui test se richiesto
$report_html = '';
if ($action === 'run') {
    if ($agent_id) {
        $runner->run_agent($agent_id);
    } else if ($category) {
        $runner->run_category($category);
    } else {
        $runner->run_all();
    }
    $report_html = $runner->generate_html_report();
}

echo $OUTPUT->header();

// Toggle button per design
echo design_helper::render_toggle_button($PAGE->url);
?>

<?php if ($is_new_design): ?>
<style>
/* FTM Design System per Agent Tests */
.agent-dashboard { max-width: 1400px; margin: 0 auto; }
.agent-header {
    background: linear-gradient(135deg, #F5A623 0%, #f7b84e 100%);
    color: white;
    padding: 35px;
    border-radius: 16px;
    margin-bottom: 30px;
    box-shadow: 0 4px 20px rgba(245, 166, 35, 0.3);
}
.agent-header h1 { margin: 0 0 12px 0; font-size: 28px; font-weight: 700; }
.agent-header p { margin: 0; opacity: 0.95; font-size: 15px; }
.category-section { margin-bottom: 30px; }
.category-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 18px 24px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 12px 12px 0 0;
    border-bottom: 2px solid #e2e8f0;
}
.category-header h3 { margin: 0; font-size: 18px; font-weight: 600; color: #1A5A5A; }
.category-icon { font-size: 28px; }
.agents-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 24px;
    background: white;
    border-radius: 0 0 12px 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}
.agent-card {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 12px;
    padding: 24px;
    border: 2px solid transparent;
    transition: all 0.2s;
    width: 100%;
}
.agent-card:hover {
    border-color: #F5A623;
    box-shadow: 0 6px 20px rgba(245, 166, 35, 0.2);
}
.agent-card h4 { margin: 0 0 8px 0; font-size: 18px; color: #1A5A5A; font-weight: 600; }
.agent-card p { margin: 0 0 15px 0; font-size: 14px; color: #64748B; line-height: 1.6; }
.agent-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
}
.test-count { font-size: 12px; color: #94a3b8; font-weight: 500; }
.btn-run {
    padding: 10px 20px;
    background: linear-gradient(135deg, #F5A623 0%, #e09000 100%);
    color: #ffffff !important;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}
.btn-run:hover {
    background: linear-gradient(135deg, #e09000 0%, #c97f00 100%);
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(245, 166, 35, 0.5);
    transform: translateY(-1px);
}
.btn-run-all {
    padding: 14px 28px;
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    color: #ffffff !important;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}
.btn-run-all:hover {
    background: linear-gradient(135deg, #1e7e34 0%, #155724 100%);
    color: #ffffff !important;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.5);
}
.btn-run-category {
    padding: 12px 24px;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: #ffffff !important;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}
.btn-run-category:hover {
    background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.5);
}
.btn-secondary {
    padding: 12px 24px;
    background: #64748B;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
}
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding: 16px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.test-report {
    background: white;
    border-radius: 16px;
    padding: 24px;
    margin-top: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}
.tests-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 10px;
    margin-top: 15px;
    padding: 20px;
    background: white;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}
.tests-list div {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    background: #f8fafc;
    border-radius: 8px;
    border-left: 3px solid #F5A623;
    font-size: 13px;
    color: #495057;
}
.tests-list code {
    background: #F5A623;
    color: white;
    padding: 4px 10px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 700;
    margin-right: 12px;
    white-space: nowrap;
}
</style>
<?php else: ?>
<style>
.agent-dashboard { max-width: 1400px; margin: 0 auto; }
.agent-header { background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; }
.agent-header h1 { margin: 0 0 10px 0; font-size: 28px; }
.agent-header p { margin: 0; opacity: 0.9; }
.category-section { margin-bottom: 30px; }
.category-header { display: flex; align-items: center; gap: 10px; padding: 15px 20px; background: #f1f5f9; border-radius: 8px 8px 0 0; border-bottom: 2px solid #e2e8f0; }
.category-header h3 { margin: 0; font-size: 18px; }
.category-icon { font-size: 24px; }
.agents-grid { display: flex; flex-direction: column; gap: 15px; padding: 20px; background: white; border-radius: 0 0 8px 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.agent-card { background: #f8fafc; border-radius: 8px; padding: 20px; border: 1px solid #e2e8f0; transition: all 0.2s; }
.agent-card:hover { border-color: #3b82f6; box-shadow: 0 4px 12px rgba(59,130,246,0.15); }
.agent-card h4 { margin: 0 0 8px 0; font-size: 16px; color: #1e293b; }
.agent-card p { margin: 0 0 15px 0; font-size: 13px; color: #64748b; line-height: 1.5; }
.agent-meta { display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid #e2e8f0; }
.test-count { font-size: 12px; color: #94a3b8; }
.btn-run { padding: 10px 20px; background: #3b82f6; color: #ffffff !important; border: none; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; }
.btn-run:hover { background: #2563eb; color: #ffffff !important; }
.btn-run-all { padding: 14px 28px; background: #10b981; color: #ffffff !important; border: none; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; }
.btn-run-all:hover { background: #059669; color: #ffffff !important; }
.btn-run-category { padding: 12px 24px; background: #6366f1; color: #ffffff !important; border: none; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; }
.btn-run-category:hover { background: #4f46e5; color: #ffffff !important; }
.action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.test-report { background: white; border-radius: 12px; padding: 20px; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.report-header { border-radius: 8px; padding: 20px; margin-bottom: 20px; }
.summary-stats { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 10px; }
.summary-stats .stat { font-size: 14px; }
.report-section { margin-bottom: 20px; }
.report-section h4 { margin-bottom: 10px; }
.table-sm { font-size: 13px; }
.table-sm td, .table-sm th { padding: 8px; }
.tests-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 8px; margin-top: 12px; padding: 15px; background: #f8fafc; border-radius: 6px; }
.tests-list div { display: flex; align-items: center; padding: 10px 12px; background: white; border-radius: 6px; border-left: 3px solid #3b82f6; font-size: 13px; }
.tests-list code { background: #3b82f6; color: white; padding: 3px 8px; border-radius: 4px; margin-right: 10px; font-weight: 600; white-space: nowrap; }
</style>
<?php endif; ?>

<div class="<?php echo $is_new_design ? 'ftm-page-bg' : ''; ?>" style="<?php echo $is_new_design ? 'padding: 20px;' : ''; ?>">
<div class="agent-dashboard">
    <div class="agent-header">
        <h1>🧪 Agenti di Test Moodle</h1>
        <p>Suite di test automatizzati per validare sicurezza, struttura, API e standard dei plugin FTM</p>
    </div>

    <div class="action-bar">
        <div>
            <strong><?php echo count($agents); ?></strong> agenti disponibili |
            <strong><?php echo array_sum(array_column($agents, 'tests_count')); ?></strong> test totali
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="<?php echo new moodle_url('/local/ftm_testsuite/index.php'); ?>" class="btn btn-secondary">← Indietro</a>
            <a href="<?php echo new moodle_url('/local/ftm_testsuite/agent_tests.php', ['action' => 'run']); ?>" class="btn-run-all">
                ▶️ Esegui TUTTI i Test
            </a>
        </div>
    </div>

    <?php if ($report_html): ?>
        <?php echo $report_html; ?>
        <hr style="margin: 30px 0;">
    <?php endif; ?>

    <?php foreach ($by_category as $cat_id => $cat_data): ?>
    <div class="category-section">
        <div class="category-header">
            <span class="category-icon"><?php echo $cat_data['icon']; ?></span>
            <h3><?php echo $cat_data['name']; ?></h3>
            <span style="margin-left: auto;">
                <a href="<?php echo new moodle_url('/local/ftm_testsuite/agent_tests.php', ['action' => 'run', 'category' => $cat_id]); ?>" class="btn-run-category">
                    ▶️ Esegui Categoria
                </a>
            </span>
        </div>
        <div class="agents-grid">
            <?php foreach ($cat_data['agents'] as $agent_id => $agent): ?>
            <div class="agent-card">
                <h4><?php echo htmlspecialchars($agent['name']); ?></h4>
                <p><?php echo htmlspecialchars($agent['description']); ?></p>

                <div class="tests-list">
                    <?php foreach ($agent['tests'] as $code => $name): ?>
                        <div><code><?php echo $code; ?></code> <?php echo htmlspecialchars($name); ?></div>
                    <?php endforeach; ?>
                </div>

                <div class="agent-meta">
                    <span class="test-count"><?php echo $agent['tests_count']; ?> test</span>
                    <a href="<?php echo new moodle_url('/local/ftm_testsuite/agent_tests.php', ['action' => 'run', 'agent' => $agent_id]); ?>" class="btn-run">
                        ▶️ Esegui
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
</div>

<?php
echo $OUTPUT->footer();
