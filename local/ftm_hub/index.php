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
 * FTM Hub - Main page with tool cards.
 *
 * @package    local_ftm_hub
 * @copyright  2026 Cristian Bodda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);

require_login();

if ($courseid > 0) {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $context = context_course::instance($courseid);
    $PAGE->set_course($course);
} else {
    $context = context_system::instance();
}

$PAGE->set_context($context);
$PAGE->set_url('/local/ftm_hub/index.php', ['courseid' => $courseid]);
$PAGE->set_title('FTM Tools Hub');
$PAGE->set_heading('🎯 FTM Tools Hub - Centro Strumenti');
$PAGE->set_pagelayout('standard');

$is_admin = has_capability('moodle/site:config', context_system::instance());
$is_teacher = $courseid > 0 && has_capability('moodle/course:manageactivities', $context);
$is_student = $courseid > 0 && has_capability('mod/quiz:attempt', $context) && !$is_teacher;

echo $OUTPUT->header();
?>
<style>
.ftm-hub-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
.ftm-hub-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white; padding: 30px; border-radius: 16px; margin-bottom: 30px;
    box-shadow: 0 4px 20px rgba(40, 167, 69, 0.3);
}
.ftm-hub-header h1 { margin: 0 0 10px; font-size: 2rem; }
.ftm-hub-header p { margin: 0; opacity: 0.9; font-size: 1.1rem; }
.ftm-section-title {
    font-size: 1.4rem; font-weight: 600; color: #333; margin: 30px 0 20px;
    padding-bottom: 10px; border-bottom: 3px solid #28a745;
    display: flex; align-items: center;
}
.ftm-section-title .icon { margin-right: 12px; font-size: 1.5rem; }
.ftm-cards-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px; margin-bottom: 30px;
}
.ftm-card {
    background: white; border-radius: 12px; padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08); transition: all 0.3s;
    border: 1px solid #e0e0e0; display: flex; flex-direction: column;
}
.ftm-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
.ftm-card-icon { font-size: 2.5rem; margin-bottom: 15px; }
.ftm-card h4 { margin: 0 0 10px; font-size: 1.15rem; font-weight: 600; color: #333; }
.ftm-card .description { color: #666; font-size: 0.9rem; line-height: 1.5; margin-bottom: 15px; flex-grow: 1; }
.ftm-card .btn {
    display: inline-block; padding: 10px 20px; border-radius: 8px;
    text-decoration: none; font-weight: 500; transition: all 0.2s;
    font-size: 0.9rem; text-align: center;
}
.ftm-card .btn:hover { transform: translateY(-2px); text-decoration: none; }
.btn-green { background: linear-gradient(135deg, #28a745, #20c997); color: white !important; }
.btn-blue { background: linear-gradient(135deg, #667eea, #764ba2); color: white !important; }
.btn-orange { background: linear-gradient(135deg, #f39c12, #e67e22); color: white !important; }
.btn-teal { background: linear-gradient(135deg, #17a2b8, #138496); color: white !important; }
.btn-purple { background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white !important; }
.btn-red { background: linear-gradient(135deg, #dc3545, #c82333); color: white !important; }
.ftm-card.highlight-green { border-left: 4px solid #28a745; }
.ftm-card.highlight-blue { border-left: 4px solid #667eea; }
.ftm-card.highlight-orange { border-left: 4px solid #f39c12; }
.ftm-card.highlight-teal { border-left: 4px solid #17a2b8; }
.ftm-card.highlight-purple { border-left: 4px solid #9b59b6; }
.ftm-card.highlight-red { border-left: 4px solid #dc3545; }
.course-selector { background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 30px; }
.course-selector label { font-weight: 600; margin-right: 15px; }
.course-selector select { padding: 10px 15px; border-radius: 8px; border: 1px solid #ddd; min-width: 300px; }
.no-course-warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
</style>

<div class="ftm-hub-container">
    <div class="ftm-hub-header">
        <h1>🎯 FTM Tools Hub</h1>
        <p>Centro Strumenti per il Passaporto Tecnico - Gestione competenze, quiz, report e autovalutazioni</p>
    </div>

    <?php if ($courseid > 0): ?>
        <div class="course-selector">
            <label>📚 Corso attivo:</label>
            <strong><?php echo format_string($course->fullname); ?></strong>
            <a href="<?php echo new moodle_url('/local/ftm_hub/index.php'); ?>" class="btn btn-sm btn-secondary ml-3">Cambia corso</a>
        </div>
    <?php else: ?>
        <div class="course-selector">
            <label>📚 Seleziona un corso:</label>
            <select onchange="if(this.value) window.location.href='?courseid='+this.value">
                <option value="">-- Seleziona corso --</option>
                <?php
                $courses = get_courses();
                foreach ($courses as $c) {
                    if ($c->id > 1) {
                        echo '<option value="' . $c->id . '">' . format_string($c->fullname) . '</option>';
                    }
                }
                ?>
            </select>
        </div>
        <div class="no-course-warning">
            ⚠️ <strong>Seleziona un corso</strong> per accedere a tutti gli strumenti.
        </div>
    <?php endif; ?>

    <?php if ($is_admin || $is_teacher): ?>
    <h2 class="ftm-section-title"><span class="icon">👨‍🏫</span> Area Coach</h2>
    <div class="ftm-cards-grid">
        <?php if ($courseid > 0): ?>
        <div class="ftm-card highlight-blue">
            <div class="ftm-card-icon">📊</div>
            <h4>Report Classe</h4>
            <p class="description">Visualizza la <strong>panoramica completa</strong> di tutti gli studenti del corso. Mostra le percentuali di competenza raggiunte, i quiz completati e lo stato delle autovalutazioni.</p>
            <a href="<?php echo new moodle_url('/local/competencymanager/reports.php', ['courseid' => $courseid]); ?>" class="btn btn-blue">Apri Report →</a>
        </div>
        <div class="ftm-card highlight-green">
            <div class="ftm-card-icon">👤</div>
            <h4>Student Report</h4>
            <p class="description">Report <strong>dettagliato per singolo studente</strong> con grafici radar interattivi, analisi per area di competenza, confronto tra performance quiz e autovalutazione (gap analysis).</p>
            <a href="<?php echo new moodle_url('/local/competencymanager/student_report.php', ['courseid' => $courseid]); ?>" class="btn btn-green">Apri Report →</a>
        </div>
        <?php endif; ?>
        <div class="ftm-card highlight-teal">
            <div class="ftm-card-icon">👥</div>
            <h4>Lista Studenti</h4>
            <p class="description">Gestisci gli <strong>studenti assegnati</strong> a te come coach. Visualizza lo stato di avanzamento, le autovalutazioni completate e i quiz svolti.</p>
            <a href="<?php echo new moodle_url('/local/coachmanager/index.php'); ?>" class="btn btn-teal">Gestisci →</a>
        </div>
        <?php if ($courseid > 0): ?>
        <div class="ftm-card highlight-orange">
            <div class="ftm-card-icon">🎯</div>
            <h4>Gestione Autovalutazioni</h4>
            <p class="description">Controlla le <strong>autovalutazioni degli studenti</strong> basate sulla tassonomia di Bloom. Visualizza chi ha completato e confronta con le performance reali.</p>
            <a href="<?php echo new moodle_url('/local/selfassessment/index.php', ['courseid' => $courseid]); ?>" class="btn btn-orange">Gestisci →</a>
        </div>
        <?php endif; ?>
        <div class="ftm-card highlight-purple">
            <div class="ftm-card-icon">📋</div>
            <h4>Report Colloqui</h4>
            <p class="description">Genera <strong>report strutturati per i colloqui</strong>. Include confronto autovalutazione/performance, identifica gap di percezione e fornisce spunti per la discussione.</p>
            <a href="<?php echo new moodle_url('/local/coachmanager/reports_v2.php'); ?>" class="btn btn-purple">Genera Report →</a>
        </div>
        <div class="ftm-card highlight-teal">
            <div class="ftm-card-icon">🔬</div>
            <h4>Valutazione Laboratorio</h4>
            <p class="description">Gestisci le <strong>valutazioni pratiche di laboratorio</strong>. Registra osservazioni, assegna punteggi per competenze operative e integra con valutazioni teoriche.</p>
            <a href="<?php echo new moodle_url('/local/labeval/index.php', $courseid > 0 ? ['courseid' => $courseid] : []); ?>" class="btn btn-teal">Apri LabEval →</a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (($is_admin || $is_teacher) && $courseid > 0): ?>
    <h2 class="ftm-section-title"><span class="icon">🔧</span> Strumenti Corso</h2>
    <div class="ftm-cards-grid">
        <div class="ftm-card highlight-green">
            <div class="ftm-card-icon">🏠</div>
            <h4>Dashboard</h4>
            <p class="description"><strong>Centro di controllo principale</strong>. Statistiche su quiz, domande, competenze e studenti. Accesso rapido a tutte le funzionalità.</p>
            <a href="<?php echo new moodle_url('/local/competencymanager/dashboard.php', ['courseid' => $courseid]); ?>" class="btn btn-green">Apri Dashboard →</a>
        </div>
        <div class="ftm-card highlight-blue">
            <div class="ftm-card-icon">📝</div>
            <h4>Crea Quiz</h4>
            <p class="description"><strong>Importa quiz da file XML</strong> o crea da banco domande. Il sistema associa automaticamente le competenze leggendo i codici nel nome.</p>
            <a href="<?php echo new moodle_url('/local/competencymanager/create_quiz.php', ['courseid' => $courseid]); ?>" class="btn btn-blue">Crea Quiz →</a>
        </div>
        <div class="ftm-card highlight-purple">
            <div class="ftm-card-icon">⚙️</div>
            <h4>Gestione Competenze</h4>
            <p class="description"><strong>Visualizza e modifica</strong> le associazioni domande-competenze. Filtra per quiz, cerca competenze, modifica livelli Bloom.</p>
            <a href="<?php echo new moodle_url('/local/competencymanager/manage_competencies.php', ['courseid' => $courseid]); ?>" class="btn btn-purple">Gestisci →</a>
        </div>
        <div class="ftm-card highlight-orange">
            <div class="ftm-card-icon">➕</div>
            <h4>Assegna Competenze</h4>
            <p class="description"><strong>Assegnazione automatica</strong> dalle domande. Legge il codice competenza dal nome (es. MECCANICA_OA_A1) e crea l'associazione.</p>
            <a href="<?php echo new moodle_url('/local/competencymanager/assign_competencies.php', ['courseid' => $courseid]); ?>" class="btn btn-orange">Assegna →</a>
        </div>
        <div class="ftm-card highlight-teal">
            <div class="ftm-card-icon">🔐</div>
            <h4>Autorizzazioni</h4>
            <p class="description"><strong>Gestisci chi può vedere i report</strong>. Autorizza studenti singoli o tutti insieme a visualizzare il proprio report competenze.</p>
            <a href="<?php echo new moodle_url('/local/competencymanager/authorize.php', ['courseid' => $courseid]); ?>" class="btn btn-teal">Gestisci →</a>
        </div>
        <div class="ftm-card highlight-green">
            <div class="ftm-card-icon">📥</div>
            <h4>Esporta Dati</h4>
            <p class="description"><strong>Scarica i dati</strong> in formato CSV o Excel. Esporta performance studenti, risultati quiz e autovalutazioni per analisi esterne.</p>
            <a href="<?php echo new moodle_url('/local/competencymanager/export.php', ['courseid' => $courseid]); ?>" class="btn btn-green">Esporta →</a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($is_admin): ?>
    <h2 class="ftm-section-title"><span class="icon">🐛</span> Debug & Test</h2>
    <div class="ftm-cards-grid">
        <?php if ($courseid > 0): ?>
        <div class="ftm-card highlight-orange">
            <div class="ftm-card-icon">🔍</div>
            <h4>Question Check</h4>
            <p class="description"><strong>Verifica struttura domande</strong>. Controlla che ogni domanda abbia competenza assegnata, identifica domande orfane o con codici errati.</p>
            <a href="<?php echo new moodle_url('/local/competencymanager/question_check.php', ['courseid' => $courseid]); ?>" class="btn btn-orange">Verifica →</a>
        </div>
        <div class="ftm-card highlight-purple">
            <div class="ftm-card-icon">🐛</div>
            <h4>Debug Competencies</h4>
            <p class="description"><strong>Analizza step-by-step</strong> il calcolo punteggi. Mostra come vengono aggregate le risposte, i pesi e il calcolo finale delle percentuali.</p>
            <a href="<?php echo new moodle_url('/local/competencymanager/debug_competencies.php', ['courseid' => $courseid]); ?>" class="btn btn-purple">Debug →</a>
        </div>
        <div class="ftm-card highlight-teal">
            <div class="ftm-card-icon">🩺</div>
            <h4>Diagnostics</h4>
            <p class="description"><strong>Diagnostica quiz e slot</strong>. Verifica struttura corretta, slot collegati alle domande giuste e riferimenti competenze validi.</p>
            <a href="<?php echo new moodle_url('/local/competencymanager/diagnostics.php', ['courseid' => $courseid]); ?>" class="btn btn-teal">Diagnostica →</a>
        </div>
        <?php endif; ?>
        <div class="ftm-card highlight-blue">
            <div class="ftm-card-icon">📊</div>
            <h4>Coverage Manager</h4>
            <p class="description"><strong>Analizza copertura competenze</strong>. Mostra quante competenze hanno domande associate, identifica gap per profilo (OA, MA, PE, IE).</p>
            <a href="<?php echo new moodle_url('/local/competencymanager/coverage_report.php'); ?>" class="btn btn-blue">Analizza →</a>
        </div>
        <div class="ftm-card highlight-green">
            <div class="ftm-card-icon">🤖</div>
            <h4>Simulatore Studente</h4>
            <p class="description"><strong>Simula tentativi quiz</strong> per testare il sistema. Crea dati test realistici per verificare report, radar chart e gap analysis.</p>
            <a href="<?php echo new moodle_url('/local/competencymanager/simulate_student.php'); ?>" class="btn btn-green">Simula →</a>
        </div>
        <div class="ftm-card highlight-red">
            <div class="ftm-card-icon">🧪</div>
            <h4>Test Suite</h4>
            <p class="description"><strong>Suite test automatici</strong>. Verifica connessione DB, tabelle, framework competenze, calcoli report e integrità dati.</p>
            <a href="<?php echo new moodle_url('/local/ftm_testsuite/index.php'); ?>" class="btn btn-red">Esegui Test →</a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($is_student || $is_admin || $is_teacher): ?>
    <h2 class="ftm-section-title"><span class="icon">👨‍🎓</span> Area Studente</h2>
    <div class="ftm-cards-grid">
        <?php if ($courseid > 0): ?>
        <div class="ftm-card highlight-blue">
            <div class="ftm-card-icon">📊</div>
            <h4>Il Mio Report</h4>
            <p class="description">Visualizza il <strong>tuo report personale</strong>. Risultati quiz, aree di forza e miglioramento con grafici radar. Disponibile se autorizzato dal coach.</p>
            <a href="<?php echo new moodle_url('/local/competencymanager/student_report.php', ['courseid' => $courseid, 'userid' => $USER->id]); ?>" class="btn btn-blue">Vedi Report →</a>
        </div>
        <?php endif; ?>
        <div class="ftm-card highlight-green">
            <div class="ftm-card-icon">✍️</div>
            <h4>La Mia Autovalutazione</h4>
            <p class="description"><strong>Compila l'autovalutazione</strong> basata su Bloom. Per ogni competenza indica il livello raggiunto (da "Conosco" a "Creo/Innovo").</p>
            <a href="<?php echo new moodle_url('/local/selfassessment/compile.php'); ?>" class="btn btn-green">Compila →</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
