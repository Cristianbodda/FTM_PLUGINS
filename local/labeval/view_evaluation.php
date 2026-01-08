<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * View completed evaluation details
 *
 * @package    local_labeval
 * @copyright  2024 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/api.php');

use local_labeval\api;

// Require login
require_login();
$context = context_system::instance();

// Parameters
$sessionid = required_param('sessionid', PARAM_INT);

// Get session details
$session = api::get_session_details($sessionid);
if (!$session) {
    throw new moodle_exception('Session not found');
}

// Check permission - coach can see all, student only own if authorized
$canviewall = has_capability('local/labeval:viewallreports', $context);
$isownreport = ($session->studentid == $USER->id);
$isauthorized = local_labeval_is_student_authorized($session->studentid);

if (!$canviewall && !($isownreport && $isauthorized)) {
    throw new moodle_exception('Access denied');
}

// Get template with behaviors
$template = api::get_template_details($session->templateid);

// Page setup
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/labeval/view_evaluation.php', ['sessionid' => $sessionid]));
$PAGE->set_title('Valutazione - ' . $session->studentfirst . ' ' . $session->studentlast);
$PAGE->set_heading('Dettaglio Valutazione');
$PAGE->set_pagelayout('standard');

// Output
echo $OUTPUT->header();

echo local_labeval_get_common_styles();
?>

<style>
.result-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.score-display {
    text-align: center;
    padding: 20px 30px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.score-display .percentage {
    font-size: 3rem;
    font-weight: 700;
}

.score-display .details {
    font-size: 14px;
    color: #666;
    margin-top: 5px;
}

.rating-display {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 13px;
}

.rating-display.rating-0 { background: #e9ecef; color: #666; }
.rating-display.rating-1 { background: #fff3cd; color: #856404; }
.rating-display.rating-3 { background: #d4edda; color: #155724; }

.competency-score-bar {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 8px;
}

.competency-score-bar .comp-info {
    min-width: 280px;
    flex: 1;
}

.competency-score-bar .code {
    font-weight: 600;
    color: #0d6efd;
    font-size: 13px;
}

.competency-score-bar .comp-desc {
    font-size: 12px;
    color: #666;
    margin-top: 2px;
    line-height: 1.3;
}

.competency-score-bar .area-badge {
    display: inline-block;
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 10px;
    background: #e3f2fd;
    color: #1565c0;
    margin-left: 8px;
}

.competency-score-bar .bar-container {
    flex: 1;
    min-width: 150px;
    height: 20px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.competency-score-bar .bar-fill {
    height: 100%;
    border-radius: 10px;
    transition: width 0.5s;
}

.competency-score-bar .percentage {
    min-width: 60px;
    text-align: right;
    font-weight: 600;
}

.behavior-result {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 15px;
    background: white;
    border-radius: 10px;
    margin-bottom: 10px;
    box-shadow: 0 1px 5px rgba(0,0,0,0.05);
}

.behavior-result .num {
    min-width: 30px;
    height: 30px;
    background: #e9ecef;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 13px;
}

.behavior-result .content {
    flex: 1;
}

.behavior-result .description {
    font-weight: 500;
    margin-bottom: 5px;
}

.behavior-result .notes {
    font-size: 13px;
    color: #666;
    font-style: italic;
    margin-top: 5px;
}
</style>

<div class="labeval-container">
    
    <!-- Back button -->
    <div style="margin-bottom: 15px;">
        <a href="<?php echo new moodle_url('/local/labeval/assignments.php'); ?>" class="btn btn-outline">
            ← Torna alle Assegnazioni
        </a>
    </div>
    
    <!-- Header -->
    <div class="card">
        <div class="card-header primary">
            <div class="result-header">
                <div>
                    <h2 style="margin: 0;">✅ Valutazione Completata</h2>
                    <p style="margin: 10px 0 0; opacity: 0.9;">
                        <strong>Studente:</strong> <?php echo $session->studentfirst . ' ' . $session->studentlast; ?>
                    </p>
                    <p style="margin: 5px 0 0; opacity: 0.9;">
                        <strong>Prova:</strong> <?php echo $session->templatename; ?> 
                        <span class="badge" style="background: rgba(255,255,255,0.2);"><?php echo $session->sectorcode; ?></span>
                    </p>
                </div>
                <div class="score-display">
                    <?php 
                    $percentage = $session->percentage ?? 0;
                    $percentcolor = $percentage >= 70 ? '#28a745' : ($percentage >= 50 ? '#ffc107' : '#dc3545');
                    ?>
                    <div class="percentage" style="color: <?php echo $percentcolor; ?>;">
                        <?php echo round($percentage); ?>%
                    </div>
                    <div class="details">
                        <?php echo round($session->totalscore, 1); ?> / <?php echo round($session->maxscore, 1); ?> punti
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Info Bar -->
    <div class="stats-row">
        <div class="stat-card info">
            <div class="number"><?php echo userdate($session->timecompleted, '%d/%m'); ?></div>
            <div class="label">📅 Data Valutazione</div>
        </div>
        <div class="stat-card purple">
            <div class="number"><?php echo $session->assessorfirst[0] . $session->assessorlast[0]; ?></div>
            <div class="label">👤 <?php echo $session->assessorfirst . ' ' . $session->assessorlast; ?></div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo count($template->behaviors); ?></div>
            <div class="label">📝 Comportamenti</div>
        </div>
        <div class="stat-card warning">
            <div class="number"><?php echo count($session->competencyscores); ?></div>
            <div class="label">🎯 Competenze</div>
        </div>
    </div>
    
    <!-- Competency Scores -->
    <div class="card">
        <div class="card-header">
            <h3>🎯 Punteggi per Competenza</h3>
        </div>
        <div class="card-body">
            <?php foreach ($session->competencyscores as $comp): 
                $pct = $comp->percentage ?? 0;
                $color = $pct >= 70 ? '#28a745' : ($pct >= 50 ? '#ffc107' : '#dc3545');
                $compinfo = local_labeval_get_competency_info($comp->competencycode);
            ?>
            <div class="competency-score-bar">
                <div class="comp-info">
                    <div>
                        <span class="code"><?php echo $comp->competencycode; ?></span>
                        <span class="area-badge"><?php echo $compinfo['area']; ?></span>
                    </div>
                    <?php if (!empty($compinfo['description'])): ?>
                    <div class="comp-desc"><?php echo $compinfo['description']; ?></div>
                    <?php endif; ?>
                </div>
                <div class="bar-container">
                    <div class="bar-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $color; ?>;"></div>
                </div>
                <span class="percentage" style="color: <?php echo $color; ?>;"><?php echo round($pct); ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Behavior Ratings -->
    <div class="card">
        <div class="card-header">
            <h3>📝 Dettaglio Comportamenti</h3>
        </div>
        <div class="card-body">
            <?php 
            $num = 1;
            foreach ($template->behaviors as $behavior): 
                $rating = $session->ratings[$behavior->id] ?? null;
                $ratingval = $rating ? $rating->rating : 0;
                $ratinginfo = local_labeval_get_rating_info($ratingval);
            ?>
            <div class="behavior-result">
                <div class="num"><?php echo $num++; ?></div>
                <div class="content">
                    <div class="description"><?php echo $behavior->description; ?></div>
                    
                    <!-- Competencies -->
                    <div style="margin-top: 8px;">
                        <?php foreach ($behavior->competencies as $comp): ?>
                        <span style="font-size: 11px; color: #666; margin-right: 10px;">
                            <?php echo $comp->competencycode; ?> 
                            <span style="color: <?php echo $comp->weight == 3 ? '#28a745' : '#999'; ?>">
                                (<?php echo $comp->weight == 3 ? '★★★' : '★'; ?>)
                            </span>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($rating && $rating->notes): ?>
                    <div class="notes">💬 <?php echo $rating->notes; ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <span class="rating-display rating-<?php echo $ratingval; ?>">
                        <?php echo $ratinginfo['stars']; ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- General Notes -->
    <?php if ($session->notes): ?>
    <div class="card">
        <div class="card-header">
            <h3>📝 Note Generali</h3>
        </div>
        <div class="card-body">
            <p style="white-space: pre-wrap;"><?php echo $session->notes; ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Actions -->
    <div class="card">
        <div class="card-body">
            <div class="btn-group">
                <a href="<?php echo new moodle_url('/local/labeval/reports.php', ['studentid' => $session->studentid]); ?>" 
                   class="btn btn-primary">
                    📊 Report Integrato Studente
                </a>
                <button onclick="window.print();" class="btn btn-secondary">
                    🖨️ Stampa
                </button>
            </div>
        </div>
    </div>
    
</div>

<!-- Print styles -->
<style media="print">
    .btn, .filters-bar, nav, header, footer, .tabtree { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    .labeval-container { max-width: 100% !important; }
</style>

<?php
echo $OUTPUT->footer();
