<?php
/**
 * Report singolo studente - VERSIONE COMPLETA
 * Con selezione MULTIPLA dei quiz, doppio grafico radar INTERATTIVO
 * STAMPA PERSONALIZZATA con selezione sezioni e GRAFICI SVG
 * DESCRIZIONI DINAMICHE dal database Moodle
 * 
 * ============================================
 * VERSIONE CON ESTENSIONI ADDITIVE COACHMANAGER
 * - Doppio Radar (Autovalutazione affiancato)
 * - Gap Analysis
 * - Spunti Colloquio
 * ============================================
 * 
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/report_generator.php');
require_once(__DIR__ . '/classes/coach_evaluation_manager.php');
require_once(__DIR__ . '/area_mapping.php');
require_once(__DIR__ . '/gap_comments_mapping.php');

use local_competencymanager\coach_evaluation_manager;

require_login();

$userid = optional_param('userid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$area = optional_param('area', '', PARAM_TEXT);
$tab = optional_param('tab', 'overview', PARAM_ALPHA);
$print = optional_param('print', 0, PARAM_INT);
$selectedArea = optional_param('selectedarea', '', PARAM_TEXT);

// Parametri per filtro e ordinamento nella tab Dettagli
$sortBy = optional_param('sort', 'area', PARAM_ALPHA);
$filterLevel = optional_param('filter', 'all', PARAM_ALPHANUMEXT);
$filterArea = optional_param('filter_area', '', PARAM_TEXT);

// Parametri stampa personalizzata
// Se print_form=1, il modale è stato usato quindi i checkbox deselezionati = 0
// Se print_form non è presente, usa default 1 per compatibilità con link diretti
$printFormUsed = optional_param('print_form', 0, PARAM_INT);
$defaultPrint = $printFormUsed ? 0 : 1;

$printPanoramica = optional_param('print_panoramica', $defaultPrint, PARAM_INT);
$printPiano = optional_param('print_piano', $defaultPrint, PARAM_INT);
$printProgressi = optional_param('print_progressi', $defaultPrint, PARAM_INT);
$printDettagli = optional_param('print_dettagli', $defaultPrint, PARAM_INT);
$printRadarAree = optional_param('print_radar_aree', $defaultPrint, PARAM_INT);
$printRadarAreas = optional_param_array('print_radar_areas', [], PARAM_TEXT);

// ============================================
// NUOVI PARAMETRI ADDITIVI (CoachManager)
// ============================================
$showDualRadar = optional_param('show_dual_radar', 0, PARAM_INT);
$showGapAnalysis = optional_param('show_gap', 0, PARAM_INT);
$showSpuntiColloquio = optional_param('show_spunti', 0, PARAM_INT);

// Nuovi parametri stampa additivi
$printDualRadar = optional_param('print_dual_radar', 0, PARAM_INT);
$printGapAnalysis = optional_param('print_gap', 0, PARAM_INT);

// Filtro settore (sincronizzato con CoachManager)
$cm_sector_filter = optional_param('cm_sector', 'all', PARAM_ALPHANUMEXT);
$printSpuntiColloquio = optional_param('print_spunti', 0, PARAM_INT);

// Filtro settore per STAMPA (nuovo)
$printSectorFilter = optional_param('print_sector', 'all', PARAM_ALPHANUMEXT);

// ============================================
// PARAMETRI VALUTAZIONE FORMATORE (Coach Evaluation)
// ============================================
$showCoachEvaluation = optional_param('show_coach_eval', 0, PARAM_INT);
$printCoachEvaluation = optional_param('print_coach_eval', 0, PARAM_INT);

// ============================================
// PARAMETRI GRAFICO SOVRAPPOSIZIONE (Overlay Radar)
// ============================================
$showOverlayRadar = optional_param('show_overlay', 0, PARAM_INT);
$printOverlayRadar = optional_param('print_overlay', 0, PARAM_INT);

// ============================================
// PARAMETRI SUGGERIMENTI RAPPORTO (Commenti Automatici Gap)
// ============================================
$showSuggerimentiRapporto = optional_param('show_suggerimenti', 0, PARAM_INT);
$printSuggerimentiRapporto = optional_param('print_suggerimenti', 0, PARAM_INT);
// Tono: 'formale' (per URC/datori lavoro) o 'colloquiale' (per uso interno coach)
$tonoCommenti = optional_param('tono_commenti', 'formale', PARAM_ALPHA);
if (!in_array($tonoCommenti, ['formale', 'colloquiale'])) {
    $tonoCommenti = 'formale';
}

// ============================================
// ORDINAMENTO SEZIONI STAMPA (impostabile dal coach)
// ============================================
$sectionOrder = [
    'valutazione'    => optional_param('order_valutazione', 1, PARAM_INT),
    'progressi'      => optional_param('order_progressi', 2, PARAM_INT),
    'radar_aree'     => optional_param('order_radar_aree', 3, PARAM_INT),
    'radar_dettagli' => optional_param('order_radar_dettagli', 4, PARAM_INT),
    'piano'          => optional_param('order_piano', 5, PARAM_INT),
    'dettagli'       => optional_param('order_dettagli', 6, PARAM_INT),
    'dual_radar'     => optional_param('order_dual_radar', 7, PARAM_INT),
    'gap_analysis'   => optional_param('order_gap', 8, PARAM_INT),
    'spunti'         => optional_param('order_spunti', 9, PARAM_INT),
    'suggerimenti'   => optional_param('order_suggerimenti', 10, PARAM_INT),
    'coach_eval'     => optional_param('order_coach_eval', 11, PARAM_INT),
];
// Ordina per valore (ordine di stampa)
asort($sectionOrder);

// ============================================
// SOGLIE CONFIGURABILI (impostabili dal coach)
// ============================================
// Soglia per considerare un gap come "allineato" (default 10%)
// < 10% = Allineato (verde)
$sogliaAllineamento = optional_param('soglia_allineamento', 10, PARAM_INT);
// Limita il range valido: minimo 5%, massimo 40%
$sogliaAllineamento = max(5, min(40, $sogliaAllineamento));

// Soglia per considerare un gap come "da monitorare" (default 25%)
// 10-25% = Da monitorare (arancione)
// > 25% = Critico (rosso)
$sogliaMonitorare = optional_param('soglia_monitorare', 25, PARAM_INT);
// Limita il range valido: minimo 15%, massimo 60%
$sogliaMonitorare = max(15, min(60, $sogliaMonitorare));

// Assicura che soglia monitorare sia sempre > soglia allineamento
if ($sogliaMonitorare <= $sogliaAllineamento) {
    $sogliaMonitorare = $sogliaAllineamento + 15;
}

// Soglia per considerare un gap come "critico" (default 30%)
// > 30% = Critico (rosso intenso)
$sogliaCritico = optional_param('soglia_critico', 30, PARAM_INT);
// Limita il range valido: minimo 20%, massimo 80%
$sogliaCritico = max(20, min(80, $sogliaCritico));

// Assicura che soglia critico sia sempre > soglia monitorare
if ($sogliaCritico <= $sogliaMonitorare) {
    $sogliaCritico = $sogliaMonitorare + 5;
}
// ============================================

$selectedQuizzes = optional_param_array('quizids', [], PARAM_INT);
$singleQuizId = optional_param('quizid', 0, PARAM_INT);
if ($singleQuizId && empty($selectedQuizzes)) {
    $selectedQuizzes = [$singleQuizId];
}
// Filtro tentativi: 'all' (tutti), 'first' (solo primo), 'last' (solo ultimo)
$attemptFilter = optional_param('attempt_filter', 'all', PARAM_ALPHA);
if (!in_array($attemptFilter, ['all', 'first', 'last'])) {
    $attemptFilter = 'all';
}

// ============================================
// SELETTORE STUDENTE (se userid non passato)
// ============================================
if ($userid == 0) {
    // Verifica permessi
    $tempcontext = $courseid > 0 ? context_course::instance($courseid) : context_system::instance();
    $canview = has_capability('moodle/grade:viewall', $tempcontext) || is_siteadmin();
    
    if (!$canview) {
        throw new moodle_exception('nopermissions', 'error', '', 'view competency reports');
    }
    
    $PAGE->set_context($tempcontext);
    $PAGE->set_url(new moodle_url('/local/competencymanager/student_report.php', ['courseid' => $courseid]));
    $PAGE->set_title('Student Report - Seleziona Studente');
    $PAGE->set_heading('📊 Student Report');
    $PAGE->set_pagelayout('report');
    
    echo $OUTPUT->header();
    ?>
    <style>
        .selector-container { max-width: 800px; margin: 40px auto; padding: 30px; }
        .selector-card { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); padding: 40px; }
        .selector-title { font-size: 1.8rem; font-weight: 600; color: #333; margin-bottom: 30px; text-align: center; }
        .selector-form { display: flex; flex-direction: column; gap: 25px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-weight: 600; color: #555; font-size: 1rem; }
        .form-group select { padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 1rem; transition: all 0.3s; }
        .form-group select:focus { border-color: #667eea; outline: none; box-shadow: 0 0 0 3px rgba(102,126,234,0.2); }
        .btn-view { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 14px 30px; border-radius: 10px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; margin-top: 10px; }
        .btn-view:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102,126,234,0.4); }
        .btn-view:disabled { background: #ccc; cursor: not-allowed; transform: none; box-shadow: none; }
        .info-text { color: #888; font-size: 0.9rem; text-align: center; margin-top: 20px; }
    </style>
    
    <div class="selector-container">
        <div class="selector-card">
            <h2 class="selector-title">👤 Seleziona Studente</h2>
            
            <form method="get" class="selector-form" id="studentSelectorForm">
                <div class="form-group">
                    <label for="courseid">📚 Corso</label>
                    <select name="courseid" id="courseid" onchange="loadStudents(this.value)" required>
                        <option value="">-- Seleziona un corso --</option>
                        <?php
                        $courses = get_courses();
                        foreach ($courses as $c) {
                            if ($c->id > 1) {
                                $selected = ($c->id == $courseid) ? 'selected' : '';
                                echo '<option value="' . $c->id . '" ' . $selected . '>' . format_string($c->fullname) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="userid">👨‍🎓 Studente</label>
                    <select name="userid" id="userid" required>
                        <option value="">-- Prima seleziona un corso --</option>
                        <?php
                        if ($courseid > 0) {
                            $coursecontext = context_course::instance($courseid);
                            $students = get_enrolled_users($coursecontext, 'mod/quiz:attempt');
                            foreach ($students as $s) {
                                echo '<option value="' . $s->id . '">' . fullname($s) . ' (' . $s->email . ')</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-view" id="btnView">📊 Visualizza Report</button>
            </form>
            
            <p class="info-text">Seleziona un corso e uno studente per visualizzare il report delle competenze.</p>
        </div>
    </div>
    
    <script>
    function loadStudents(courseId) {
        var studentSelect = document.getElementById('userid');
        studentSelect.innerHTML = '<option value="">Caricamento...</option>';
        
        if (!courseId) {
            studentSelect.innerHTML = '<option value="">-- Prima seleziona un corso --</option>';
            return;
        }
        
        // Ricarica la pagina con il corso selezionato per caricare gli studenti
        window.location.href = '?courseid=' + courseId;
    }
    </script>
    <?php
    echo $OUTPUT->footer();
    exit;
}
// ============================================
// FINE SELETTORE STUDENTE
// ============================================

$student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

if ($courseid) {
    $context = context_course::instance($courseid);
    $course = get_course($courseid);
} else {
    $context = context_system::instance();
    $course = null;
}

$canviewall = has_capability('moodle/grade:viewall', $context);
$isadmin = is_siteadmin();
$isownreport = ($USER->id == $userid);

if (!$canviewall && !$isadmin) {
    if ($isownreport && $courseid) {
        if (!\local_competencymanager\report_generator::student_can_view_own_report($userid, $courseid)) {
            throw new moodle_exception('nopermissions', 'error', '', 'view this report');
        }
    } else {
        throw new moodle_exception('nopermissions', 'error', '', 'view competency reports');
    }
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/student_report.php', ['userid' => $userid, 'courseid' => $courseid, 'tab' => $tab]));
$PAGE->set_title(get_string('studentreport', 'local_competencymanager') . ': ' . fullname($student));
$PAGE->set_heading(get_string('studentreport', 'local_competencymanager'));
$PAGE->set_pagelayout('report');

// ========================================
// FUNZIONI HELPER
// ========================================

// ========================================
// FUNZIONE HELPER: Settore dal nome corso
// ========================================
function get_sector_from_course_name($coursename) {
    $name = strtoupper($coursename);
    if (strpos($name, 'AUTOVEICOLO') !== false || strpos($name, 'AUTOMOBILE') !== false) {
        return 'AUTOMOBILE';
    }
    if (strpos($name, 'CHIMICA') !== false || strpos($name, 'CHIMFARM') !== false || strpos($name, 'FARMAC') !== false) {
        return 'CHIMFARM';
    }
    if (strpos($name, 'LOGISTICA') !== false) {
        return 'LOGISTICA';
    }
    if (strpos($name, 'MECCANICA') !== false) {
        return 'MECCANICA';
    }
    if (strpos($name, 'ELETTRIC') !== false) {
        return 'ELETTRICITA';
    }
    if (strpos($name, 'AUTOMAZIONE') !== false) {
        return 'AUTOMAZIONE';
    }
    if (strpos($name, 'METALCOSTRUZIONE') !== false || strpos($name, 'METAL') !== false) {
        return 'METALCOSTRUZIONE';
    }
    return '';
}

function get_evaluation_band($percentage) {
    if ($percentage >= 80) {
        return ['label' => 'ECCELLENTE', 'icon' => '🌟', 'color' => '#28a745', 'bgColor' => '#d4edda', 'description' => 'Padronanza completa della competenza.', 'action' => 'Pronto per attività avanzate e tutoraggio compagni.', 'class' => 'success'];
    } else if ($percentage >= 60) {
        return ['label' => 'BUONO', 'icon' => '✅', 'color' => '#20c997', 'bgColor' => '#d1f2eb', 'description' => 'Competenza acquisita con buona padronanza.', 'action' => 'Consolidare con esercizi pratici.', 'class' => 'info'];
    } else if ($percentage >= 50) {
        return ['label' => 'SUFFICIENTE', 'icon' => '⚠️', 'color' => '#ffc107', 'bgColor' => '#fff3cd', 'description' => 'Base acquisita ma da consolidare.', 'action' => 'Ripasso teoria ed esercitazioni.', 'class' => 'warning'];
    } else if ($percentage >= 30) {
        return ['label' => 'INSUFFICIENTE', 'icon' => '⚡', 'color' => '#fd7e14', 'bgColor' => '#ffe5d0', 'description' => 'Lacune significative identificate.', 'action' => 'Percorso di recupero mirato richiesto.', 'class' => 'orange'];
    } else {
        return ['label' => 'CRITICO', 'icon' => '🔴', 'color' => '#dc3545', 'bgColor' => '#f8d7da', 'description' => 'Competenza non acquisita.', 'action' => 'Formazione base completa richiesta.', 'class' => 'danger'];
    }
}

function aggregate_by_area($competencies, $areaDescriptions, $sector = '') {
    global $AREA_NAMES;
    $areas = [];
    $colors = ['#667eea','#764ba2','#f093fb','#f5576c','#4facfe','#00f2fe','#43e97b','#38f9d7','#fa709a','#fee140','#30cfd0','#c471f5','#48c6ef','#6f86d6'];
    foreach ($competencies as $comp) {
        $code = $comp['idnumber'] ?: $comp['name'];
        $areaInfo = get_area_info($code);
        // IMPORTANTE: Usa areaKey (es. AUTOMOBILE_A) invece di solo codice (A) per evitare conflitti tra settori
        $areaKey = $areaInfo['key'];
        $areaCode = $areaInfo['code'];
        $areaName = $areaInfo['name'];
        if (!isset($areas[$areaKey])) {
            $colorIndex = count($areas) % count($colors);
            $areas[$areaKey] = [
                'key' => $areaKey,          // Es. AUTOMOBILE_A
                'code' => $areaCode,         // Es. A
                'name' => $areaName,
                'icon' => '📁',
                'color' => $colors[$colorIndex],
                'total_questions' => 0,
                'correct_questions' => 0,
                'competencies' => [],
                'count' => 0
            ];
        }
        $areas[$areaKey]['total_questions'] += $comp['total_questions'];
        $areas[$areaKey]['correct_questions'] += $comp['correct_questions'];
        $areas[$areaKey]['competencies'][] = $comp;
        $areas[$areaKey]['count']++;
    }
    foreach ($areas as $key => &$area) {
        $area['percentage'] = $area['total_questions'] > 0 ? round($area['correct_questions'] / $area['total_questions'] * 100, 1) : 0;
    }
    ksort($areas);
    return $areas;
}

function generate_action_plan($competencies, $competencyDescriptions) {
    $plan = ['excellence' => [], 'good' => [], 'toImprove' => [], 'critical' => []];
    
    foreach ($competencies as $comp) {
        $code = $comp['idnumber'] ?: $comp['name'];
        $info = $competencyDescriptions[$code] ?? ['name' => $code, 'full_name' => $code];
        $percentage = $comp['percentage'];
        $displayName = $info['full_name'] ?? $info['name'] ?? $code;
        
        $item = ['code' => $code, 'name' => $displayName, 'percentage' => $percentage, 'correct' => $comp['correct_questions'], 'total' => $comp['total_questions'], 'description' => $comp['description'] ?? $info['description'] ?? ''];
        
        if ($percentage >= 80) $plan['excellence'][] = $item;
        else if ($percentage >= 60) $plan['good'][] = $item;
        else if ($percentage >= 30) $plan['toImprove'][] = $item;
        else $plan['critical'][] = $item;
    }
    
    usort($plan['critical'], fn($a, $b) => $a['percentage'] <=> $b['percentage']);
    usort($plan['toImprove'], fn($a, $b) => $a['percentage'] <=> $b['percentage']);
    usort($plan['good'], fn($a, $b) => $b['percentage'] <=> $a['percentage']);
    usort($plan['excellence'], fn($a, $b) => $b['percentage'] <=> $a['percentage']);
    
    return $plan;
}

function generate_certification_progress($competencies) {
    $certified = $inProgress = $notStarted = 0;
    foreach ($competencies as $comp) {
        if ($comp['percentage'] >= 80) $certified++;
        else if ($comp['percentage'] > 0) $inProgress++;
        else $notStarted++;
    }
    $total = count($competencies);
    return ['certified' => $certified, 'inProgress' => $inProgress, 'notStarted' => $notStarted, 'total' => $total, 'percentage' => $total > 0 ? round($certified / $total * 100) : 0];
}

// ============================================
// NUOVE FUNZIONI ADDITIVE (CoachManager)
// ============================================
/**
 * Recupera dati autovalutazione dello studente (se esistono)
 * Cerca in local_coachmanager_assessment E in local_selfassessment
 */
function get_student_self_assessment($userid, $courseid, $sector = '') {
    global $DB;
    $dbman = $DB->get_manager();
    
    // METODO 1: Cerca in local_coachmanager_assessment
    if ($dbman->table_exists('local_coachmanager_assessment')) {
        $assessments = $DB->get_records('local_coachmanager_assessment', [
            'userid' => $userid,
            'courseid' => $courseid
        ], 'timecreated DESC', '*', 0, 1);
        if (!empty($assessments)) {
            $assessment = reset($assessments);
            $details = json_decode($assessment->details, true);
            if (!empty($details['competencies'])) {
                $autovalutazione = [];
                foreach ($details['competencies'] as $comp) {
                    // Usa description se disponibile, altrimenti name, altrimenti idnumber formattato
                    $displayName = '';
                    if (!empty($comp['description'])) {
                        $cleanDesc = strip_tags($comp['description']);
                        $displayName = mb_strlen($cleanDesc) > 80 ? mb_substr($cleanDesc, 0, 80) . '...' : $cleanDesc;
                    } elseif (!empty($comp['name']) && $comp['name'] !== $comp['idnumber']) {
                        $displayName = $comp['name'];
                    } else {
                        $displayName = str_replace('_', ' ', $comp['idnumber']);
                    }
                    $autovalutazione[$comp['idnumber']] = [
                        'idnumber' => $comp['idnumber'],
                        'name' => $displayName,
                        'bloom_level' => $comp['bloom_level'] ?? 0,
                        'percentage' => isset($comp['bloom_level']) ? round(($comp['bloom_level'] / 6) * 100, 1) : 0
                    ];
                }
                return ['data' => $autovalutazione, 'timestamp' => $assessment->timecreated];
            }
        }
    }
    
    // METODO 2: Cerca in local_selfassessment (filtrato per settore se specificato)
    if ($dbman->table_exists('local_selfassessment')) {
        $params = ['userid' => $userid];
        $sectorFilter = '';
        if (!empty($sector)) {
            $sectorFilter = " AND c.idnumber LIKE :sector_prefix";
            $params['sector_prefix'] = $sector . '_%';
        }
        $sql = "SELECT sa.id, sa.competencyid, sa.level, sa.timecreated, c.idnumber, c.shortname, c.description FROM {local_selfassessment} sa JOIN {competency} c ON sa.competencyid = c.id WHERE sa.userid = :userid" . $sectorFilter . " ORDER BY c.idnumber ASC";
        $records = $DB->get_records_sql($sql, $params);
        if (!empty($records)) {
            $autovalutazione = [];
            $latestTimestamp = 0;
            foreach ($records as $rec) {
                // Usa description se disponibile, altrimenti shortname, altrimenti idnumber formattato
                $displayName = '';
                if (!empty($rec->description)) {
                    $cleanDesc = strip_tags($rec->description);
                    $displayName = mb_strlen($cleanDesc) > 80 ? mb_substr($cleanDesc, 0, 80) . '...' : $cleanDesc;
                } elseif (!empty($rec->shortname) && $rec->shortname !== $rec->idnumber) {
                    $displayName = $rec->shortname;
                } else {
                    $displayName = str_replace('_', ' ', $rec->idnumber);
                }
                $autovalutazione[$rec->idnumber] = [
                    'idnumber' => $rec->idnumber,
                    'name' => $displayName,
                    'bloom_level' => (int)$rec->level,
                    'percentage' => round(($rec->level / 6) * 100, 1)
                ];
                if ($rec->timecreated > $latestTimestamp) {
                    $latestTimestamp = $rec->timecreated;
                }
            }
            return ['data' => $autovalutazione, 'timestamp' => $latestTimestamp];
        }
    }
    
    return null;
}

/**
 * Calcola Gap Analysis tra autovalutazione e performance reale
 */
function calculate_gap_analysis($autovalutazione, $performance, $soglia = 15) {
    if (empty($autovalutazione) || empty($performance)) {
        return [];
    }
    
    $gap = [];
    $perfMap = [];
    foreach ($performance as $comp) {
        $code = $comp['idnumber'] ?? $comp['name'];
        $perfMap[$code] = $comp['percentage'] ?? 0;
    }
    
    foreach ($autovalutazione as $code => $auto) {
        $autoVal = $auto['percentage'];
        $perfVal = $perfMap[$code] ?? null;
        
        if ($perfVal === null) continue;
        
        $diff = $autoVal - $perfVal;
        
        if ($diff > $soglia) {
            $tipo = 'sopravvalutazione';
            $icona = '⬆️';
            $colore = '#dc3545';
            $bg = '#fadbd8';
        } else if ($diff < -$soglia) {
            $tipo = 'sottovalutazione';
            $icona = '⬇️';
            $colore = '#f39c12';
            $bg = '#fef9e7';
        } else {
            $tipo = 'allineato';
            $icona = '✅';
            $colore = '#28a745';
            $bg = '#d5f5e3';
        }
        
        $gap[$code] = [
            'idnumber' => $code,
            'name' => $auto['name'],
            'autovalutazione' => $autoVal,
            'performance' => $perfVal,
            'differenza' => $diff,
            'tipo' => $tipo,
            'icona' => $icona,
            'colore' => $colore,
            'bg' => $bg
        ];
    }
    
    uasort($gap, fn($a, $b) => abs($b['differenza']) <=> abs($a['differenza']));
    return $gap;
}

/**
 * Genera spunti per il colloquio basati sul gap analysis
 * @param array $gapData Dati gap analysis
 * @param array $competencyDescriptions Descrizioni competenze
 * @param int $sogliaCritico Soglia percentuale per gap critici (default 30%)
 */
function generate_colloquio_hints($gapData, $competencyDescriptions, $sogliaCritico = 30) {
    $hints = ['critici' => [], 'attenzione' => [], 'positivi' => []];
    
    foreach ($gapData as $code => $gap) {
        $compInfo = $competencyDescriptions[$code] ?? ['name' => $code, 'full_name' => $code];
        $displayName = $compInfo['full_name'] ?? $compInfo['name'] ?? $code;
        
        $hint = ['competenza' => $displayName, 'codice' => $code, 'gap' => $gap];
        $diffAbs = abs($gap['differenza']);
        
        if ($gap['tipo'] === 'sopravvalutazione') {
            if ($diffAbs > $sogliaCritico) {  // ← USA PARAMETRO INVECE DI 30 HARDCODED
                $hint['messaggio'] = "Lo studente si percepisce molto più competente di quanto dimostrato. Approfondire le lacune.";
                $hint['domanda'] = "Come valuti le tue competenze in {$displayName}? Quali difficoltà hai incontrato?";
                $hints['critici'][] = $hint;
            } else {
                $hint['messaggio'] = "Leggera sopravvalutazione. Consolidare con esercizi pratici.";
                $hint['domanda'] = "Cosa potresti fare per migliorare in {$displayName}?";
                $hints['attenzione'][] = $hint;
            }
        } else if ($gap['tipo'] === 'sottovalutazione') {
            $hint['messaggio'] = "Lo studente si sottovaluta! Valorizzare i risultati positivi.";
            $hint['domanda'] = "Hai notato che nei quiz hai ottenuto risultati migliori di quanto ti aspettassi?";
            $hints['positivi'][] = $hint;
        } else {
            $hint['messaggio'] = "Buon allineamento tra percezione e realtà.";
            $hints['positivi'][] = $hint;
        }
    }
    
    return $hints;
}

/**
 * Aggrega autovalutazione per area
 */
function aggregate_autovalutazione_by_area($autovalutazione, $areaDescriptions, $sector = '') {
    global $AREA_NAMES;
    $areas = [];
    $colors = ['#667eea','#764ba2','#f093fb','#f5576c','#4facfe','#00f2fe','#43e97b','#38f9d7','#fa709a','#fee140'];
    foreach ($autovalutazione as $code => $comp) {
        $areaInfo = get_area_info($code);
        // IMPORTANTE: Usa areaKey (es. AUTOMOBILE_A) invece di solo codice (A) per evitare conflitti tra settori
        $areaKey = $areaInfo['key'];
        $areaCode = $areaInfo['code'];
        $areaName = $areaInfo['name'];
        if (!isset($areas[$areaKey])) {
            $colorIndex = count($areas) % count($colors);
            $areas[$areaKey] = [
                'key' => $areaKey,           // Es. AUTOMOBILE_A
                'code' => $areaCode,         // Es. A
                'name' => $areaName,
                'icon' => '📁',
                'color' => $colors[$colorIndex],
                'total_percentage' => 0,
                'count' => 0
            ];
        }
        $areas[$areaKey]['total_percentage'] += $comp['percentage'];
        $areas[$areaKey]['count']++;
    }
    foreach ($areas as $key => &$area) {
        $area['percentage'] = $area['count'] > 0 ? round($area['total_percentage'] / $area['count'], 1) : 0;
    }
    ksort($areas);
    return $areas;
}

// ============================================
// FINE FUNZIONI ADDITIVE
// ============================================

/**
 * Genera un grafico radar SVG
 */
function generate_svg_radar($data, $title = '', $size = 300, $fillColor = 'rgba(102,126,234,0.3)', $strokeColor = '#667eea', $labelFontSize = 9, $maxLabelLen = 250) {
    if (empty($data)) return '<p class="text-muted">Nessun dato disponibile</p>';

    // Padding laterale per etichette lunghe (testo che esce a sinistra/destra)
    $horizontalPadding = 180;
    $svgWidth = $size + (2 * $horizontalPadding);

    $cx = $horizontalPadding + ($size / 2);
    $cy = $size / 2;
    // Margine più ampio per etichette lunghe
    $margin = max(70, $labelFontSize * 8);
    $radius = ($size / 2) - $margin;
    $n = count($data);

    if ($n < 3) {
        return generate_svg_bar_chart($data, $title, $size);
    }

    $angleStep = (2 * M_PI) / $n;

    $svg = '<svg width="' . $svgWidth . '" height="' . ($size + 50) . '" xmlns="http://www.w3.org/2000/svg" style="font-family: Arial, sans-serif;">';

    if ($title) {
        $svg .= '<text x="' . $cx . '" y="20" text-anchor="middle" font-size="12" font-weight="bold" fill="#333">' . htmlspecialchars($title) . '</text>';
    }
    $offsetY = $title ? 35 : 10;

    for ($p = 20; $p <= 100; $p += 20) {
        $r = $radius * ($p / 100);
        $svg .= '<circle cx="' . $cx . '" cy="' . ($cy + $offsetY) . '" r="' . $r . '" fill="none" stroke="#e0e0e0" stroke-width="1"/>';
        if ($p == 100) {
            $svg .= '<text x="' . ($cx + 5) . '" y="' . ($cy + $offsetY - $r - 3) . '" font-size="8" fill="#999">' . $p . '%</text>';
        }
    }

    $svg .= '<circle cx="' . $cx . '" cy="' . ($cy + $offsetY) . '" r="' . ($radius * 0.6) . '" fill="none" stroke="#f39c12" stroke-width="2" stroke-dasharray="5,3"/>';
    $svg .= '<circle cx="' . $cx . '" cy="' . ($cy + $offsetY) . '" r="' . ($radius * 0.8) . '" fill="none" stroke="#27ae60" stroke-width="2" stroke-dasharray="5,3"/>';

    $points = [];
    $i = 0;
    foreach ($data as $item) {
        $angle = ($i * $angleStep) - (M_PI / 2);

        $axisX = $cx + $radius * cos($angle);
        $axisY = ($cy + $offsetY) + $radius * sin($angle);
        $svg .= '<line x1="' . $cx . '" y1="' . ($cy + $offsetY) . '" x2="' . $axisX . '" y2="' . $axisY . '" stroke="#ddd" stroke-width="1"/>';

        $value = min(100, max(0, $item['value']));
        $pointRadius = $radius * ($value / 100);
        $pointX = $cx + $pointRadius * cos($angle);
        $pointY = ($cy + $offsetY) + $pointRadius * sin($angle);
        $points[] = $pointX . ',' . $pointY;

        $labelRadius = $radius + 20;
        $labelX = $cx + $labelRadius * cos($angle);
        $labelY = ($cy + $offsetY) + $labelRadius * sin($angle);

        $anchor = 'middle';
        if ($labelX < $cx - 20) $anchor = 'end';
        else if ($labelX > $cx + 20) $anchor = 'start';

        $labelColor = $value >= 80 ? '#27ae60' : ($value >= 60 ? '#3498db' : ($value >= 40 ? '#f39c12' : '#c0392b'));
        // Tronca etichetta se necessario (parametro configurabile)
        $displayLabel = mb_strlen($item['label']) > $maxLabelLen ? mb_substr($item['label'], 0, $maxLabelLen - 2) . '...' : $item['label'];

        $svg .= '<text x="' . $labelX . '" y="' . $labelY . '" text-anchor="' . $anchor . '" font-size="' . $labelFontSize . '" fill="#333">' . htmlspecialchars($displayLabel) . '</text>';
        $svg .= '<text x="' . $labelX . '" y="' . ($labelY + $labelFontSize + 2) . '" text-anchor="' . $anchor . '" font-size="' . ($labelFontSize + 1) . '" font-weight="bold" fill="' . $labelColor . '">' . $value . '%</text>';

        $i++;
    }

    if (!empty($points)) {
        $svg .= '<polygon points="' . implode(' ', $points) . '" fill="' . $fillColor . '" stroke="' . $strokeColor . '" stroke-width="2"/>';
        foreach ($points as $point) {
            list($px, $py) = explode(',', $point);
            $svg .= '<circle cx="' . $px . '" cy="' . $py . '" r="5" fill="' . $strokeColor . '" stroke="white" stroke-width="2"/>';
        }
    }

    $legendY = $size + $offsetY + 10;
    $svg .= '<line x1="' . ($cx - 100) . '" y1="' . $legendY . '" x2="' . ($cx - 80) . '" y2="' . $legendY . '" stroke="#f39c12" stroke-width="2" stroke-dasharray="5,3"/>';
    $svg .= '<text x="' . ($cx - 75) . '" y="' . ($legendY + 4) . '" font-size="9" fill="#666">60% Sufficiente</text>';
    $svg .= '<line x1="' . ($cx + 20) . '" y1="' . $legendY . '" x2="' . ($cx + 40) . '" y2="' . $legendY . '" stroke="#27ae60" stroke-width="2" stroke-dasharray="5,3"/>';
    $svg .= '<text x="' . ($cx + 45) . '" y="' . ($legendY + 4) . '" font-size="9" fill="#666">80% Eccellente</text>';

    $svg .= '</svg>';
    return $svg;
}

/**
 * Genera un grafico a barre SVG (per meno di 3 elementi)
 */
function generate_svg_bar_chart($data, $title = '', $width = 300) {
    $barHeight = 30;
    $padding = 10;
    $labelWidth = 100;
    $height = count($data) * ($barHeight + $padding) + 60;
    
    $svg = '<svg width="' . $width . '" height="' . $height . '" xmlns="http://www.w3.org/2000/svg" style="font-family: Arial, sans-serif;">';
    
    if ($title) {
        $svg .= '<text x="' . ($width/2) . '" y="20" text-anchor="middle" font-size="12" font-weight="bold" fill="#333">' . htmlspecialchars($title) . '</text>';
    }
    
    $y = 40;
    foreach ($data as $item) {
        $value = min(100, max(0, $item['value']));
        $barWidth = ($width - $labelWidth - 50) * ($value / 100);
        $color = $value >= 80 ? '#27ae60' : ($value >= 60 ? '#3498db' : ($value >= 40 ? '#f39c12' : '#c0392b'));
        
        $svg .= '<text x="5" y="' . ($y + 20) . '" font-size="10" fill="#333">' . htmlspecialchars(substr($item['label'], 0, 15)) . '</text>';
        $svg .= '<rect x="' . $labelWidth . '" y="' . $y . '" width="' . ($width - $labelWidth - 50) . '" height="' . $barHeight . '" fill="#f0f0f0" rx="4"/>';
        $svg .= '<rect x="' . $labelWidth . '" y="' . $y . '" width="' . $barWidth . '" height="' . $barHeight . '" fill="' . $color . '" rx="4"/>';
        $svg .= '<text x="' . ($width - 40) . '" y="' . ($y + 20) . '" font-size="11" font-weight="bold" fill="' . $color . '">' . $value . '%</text>';
        
        $y += $barHeight + $padding;
    }
    
    $svg .= '</svg>';
    return $svg;
}

// ========================================
// CARICAMENTO DATI
// ========================================
$availableQuizzes = \local_competencymanager\report_generator::get_available_quizzes($userid, $courseid);
$quizIdsForQuery = !empty($selectedQuizzes) ? $selectedQuizzes : null;

// Passa attemptFilter per filtrare tentativi (tutti/primo/ultimo)
$radardata = \local_competencymanager\report_generator::get_radar_chart_data($userid, $courseid, $quizIdsForQuery, $area ?: null, $attemptFilter);
$summary = \local_competencymanager\report_generator::get_student_summary($userid, $courseid, $quizIdsForQuery, $area ?: null, $attemptFilter);
$competencies = $radardata['competencies'];

$availableAreas = \local_competencymanager\report_generator::get_available_areas($userid, $courseid);
$quizComparison = \local_competencymanager\report_generator::get_quiz_comparison($userid, $courseid);
$progressData = \local_competencymanager\report_generator::get_progress_over_time($userid, $courseid);

// ============================================
// DEBUG: Informazioni per diagnostica
// ============================================
$debugMode = optional_param('debug', 0, PARAM_INT);
if ($debugMode) {
    echo '<div class="alert alert-info mt-2" style="font-size: 12px; font-family: monospace;">';
    echo '<strong>🔍 DEBUG INFO:</strong><br>';
    echo 'Selected Quiz IDs: ' . ($quizIdsForQuery ? implode(', ', $quizIdsForQuery) : 'TUTTI') . '<br>';
    echo 'Attempt Filter: ' . $attemptFilter . '<br>';
    echo 'Competencies found: ' . count($competencies) . '<br>';
    echo 'Quiz in comparison: ' . count($quizComparison) . '<br>';

    // Mostra tutti i tentativi quiz dallo studente (inclusi quelli non finished)
    global $DB;
    $allAttemptsDebug = $DB->get_records_sql(
        "SELECT qa.id, qa.quiz, qa.state, qa.timefinish, q.name
         FROM {quiz_attempts} qa
         JOIN {quiz} q ON q.id = qa.quiz
         WHERE qa.userid = :userid
         ORDER BY qa.timefinish DESC
         LIMIT 10",
        ['userid' => $userid]
    );
    echo '<br><strong>Ultimi 10 tentativi quiz (tutti gli stati):</strong><br>';
    foreach ($allAttemptsDebug as $att) {
        $date = $att->timefinish ? date('d/m/Y H:i', $att->timefinish) : 'N/A';
        $stateClass = $att->state === 'finished' ? 'text-success' : 'text-warning';
        echo "<span class='$stateClass'>ID:{$att->id} | {$att->name} | Stato: {$att->state} | Data: {$date}</span><br>";
    }

    // Mostra competenze per quiz selezionato
    // Query compatibile con Moodle 4.x (usa question_references invece di questionid diretto)
    if (!empty($quizIdsForQuery)) {
        echo '<br><strong>Competenze nei quiz selezionati:</strong><br>';
        foreach ($quizIdsForQuery as $qid) {
            $quizCompetencies = $DB->get_records_sql(
                "SELECT DISTINCT comp.idnumber, comp.shortname
                 FROM {quiz_slots} qs
                 JOIN {question_references} qr ON qr.component = 'mod_quiz'
                      AND qr.questionarea = 'slot' AND qr.itemid = qs.id
                 JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                 JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                 JOIN {qbank_competenciesbyquestion} qcbq ON qcbq.questionid = qv.questionid
                 JOIN {competency} comp ON comp.id = qcbq.competencyid
                 WHERE qs.quizid = :quizid",
                ['quizid' => $qid]
            );
            echo "Quiz ID $qid: " . count($quizCompetencies) . " competenze<br>";
            if (count($quizCompetencies) === 0) {
                echo "<span class='text-danger'>⚠️ Questo quiz NON ha competenze assegnate alle domande!</span><br>";
            }
        }
    }
    echo '</div>';
}

// CARICAMENTO DESCRIZIONI DINAMICHE DAL DATABASE
// Rileva TUTTI i settori presenti nelle competenze (non solo il maggioritario)
$validSectors = ['AUTOMOBILE', 'AUTOVEICOLO', 'AUTOMAZIONE', 'AUTOM', 'AUTOMAZ',
                 'CHIMFARM', 'CHIM', 'CHIMICA', 'FARMACEUTICA',
                 'ELETTRICITÀ', 'ELETTRICITA', 'ELETTR', 'ELETT',
                 'LOGISTICA', 'LOG', 'MECCANICA', 'MECC', 'METALCOSTRUZIONE', 'METAL', 'GENERICO', 'GEN'];
$sectorsFound = [];
foreach ($competencies as $comp) {
    $idnumber = $comp['idnumber'] ?? '';
    if (empty($idnumber)) continue;
    $parts = explode('_', $idnumber);
    $potentialSector = strtoupper($parts[0] ?? '');
    // Deve avere almeno 2 parti (SETTORE_xxx) e essere un settore valido
    if (count($parts) >= 2 && in_array($potentialSector, $validSectors)) {
        $sectorsFound[$potentialSector] = true;
    }
}
// Settore principale (per compatibilità) = primo trovato
$sector = !empty($sectorsFound) ? array_key_first($sectorsFound) : null;

// Settore dal nome corso (usato per filtrare autovalutazione)
$courseSector = $course ? get_sector_from_course_name($course->fullname) : '';
// Se non c'è settore dai quiz, usa quello del corso
if (empty($sector) && !empty($courseSector)) {
    $sector = $courseSector;
    $sectorsFound[$courseSector] = true;
}

// Carica descrizioni per TUTTI i settori trovati
$areaDescriptions = [];
$competencyDescriptions = [];
foreach (array_keys($sectorsFound) as $sec) {
    $areaDesc = \local_competencymanager\report_generator::get_area_descriptions_from_framework($sec, $courseid);
    $compDesc = \local_competencymanager\report_generator::get_competency_descriptions_from_framework($sec);
    // Merge con priorità ai nuovi (non sovrascrive esistenti)
    $areaDescriptions = array_merge($areaDesc, $areaDescriptions);
    $competencyDescriptions = array_merge($compDesc, $competencyDescriptions);
}

// FILTRA COMPETENZE PER SETTORE PRIMA DI AGGREGARE (se filtro attivo)
$competencies_for_areas = $competencies;
if ($cm_sector_filter !== 'all') {
    $normalizedSectorFilter = normalize_sector_name($cm_sector_filter);
    $competencies_for_areas = array_filter($competencies, function($comp) use ($normalizedSectorFilter) {
        $idnumber = $comp['idnumber'] ?? '';
        $compSector = extract_sector_from_idnumber($idnumber);
        // Confronto case-insensitive
        return strcasecmp($compSector, $normalizedSectorFilter) === 0;
    });
    // Re-indicizza array
    $competencies_for_areas = array_values($competencies_for_areas);
}

$areasData = aggregate_by_area($competencies_for_areas, $areaDescriptions, $sector);

// Il filtro per settore è già stato applicato PRIMA di aggregate_by_area()
// Le aree in $areasData sono già filtrate per il settore selezionato
$evaluation = get_evaluation_band($summary['overall_percentage']);
$actionPlan = generate_action_plan($competencies, $competencyDescriptions);
$certProgress = generate_certification_progress($competencies);

// ============================================
// CARICAMENTO DATI ADDITIVI (CoachManager)
// ============================================
$autovalutazioneResult = null;
$autovalutazioneData = null;
$autovalutazioneTimestamp = null;
$gapAnalysisData = null;
$colloquioHints = null;
$autovalutazioneAreas = null;

// Carica solo se almeno una opzione è attivata (incluso overlay)
if ($showDualRadar || $showGapAnalysis || $showSpuntiColloquio || $showSuggerimentiRapporto ||
    $printDualRadar || $printGapAnalysis || $printSpuntiColloquio || $printSuggerimentiRapporto ||
    $showOverlayRadar || $printOverlayRadar) {
    
    $autovalutazioneResult = get_student_self_assessment($userid, $courseid, $courseSector);
    
    if ($autovalutazioneResult) {
        $autovalutazioneData = $autovalutazioneResult['data'];
        $autovalutazioneTimestamp = $autovalutazioneResult['timestamp'];
        
        // Calcola gap se richiesto - USA SOGLIE CONFIGURABILI
        if ($showGapAnalysis || $printGapAnalysis || $showSpuntiColloquio || $printSpuntiColloquio ||
            $showSuggerimentiRapporto || $printSuggerimentiRapporto) {
            $gapAnalysisData = calculate_gap_analysis($autovalutazioneData, $competencies, $sogliaAllineamento);
        }
        
        // Genera spunti colloquio se richiesto - USA SOGLIE CONFIGURABILI
        if ($showSpuntiColloquio || $printSpuntiColloquio) {
            $colloquioHints = generate_colloquio_hints($gapAnalysisData, $competencyDescriptions, $sogliaCritico);
        }
        
        // Aggrega autovalutazione per area (anche per overlay)
        if ($showDualRadar || $printDualRadar || $showOverlayRadar || $printOverlayRadar) {
            // FILTRA AUTOVALUTAZIONE PER SETTORE (se filtro attivo)
            $autovalutazione_for_areas = $autovalutazioneData;
            if ($cm_sector_filter !== 'all') {
                $normalizedSectorFilter = normalize_sector_name($cm_sector_filter);
                $autovalutazione_for_areas = array_filter($autovalutazioneData, function($comp, $idnumber) use ($normalizedSectorFilter) {
                    $compSector = extract_sector_from_idnumber($idnumber);
                    return strcasecmp($compSector, $normalizedSectorFilter) === 0;
                }, ARRAY_FILTER_USE_BOTH);

                // DEBUG: mostra quante competenze autovalutazione matchano il settore
                echo "<!-- DEBUG AUTOVALUTAZIONE: filtro='$normalizedSectorFilter', totale=" . count($autovalutazioneData) . ", filtrate=" . count($autovalutazione_for_areas) . " -->\n";
                if (count($autovalutazione_for_areas) > 0) {
                    $firstKeys = array_slice(array_keys($autovalutazione_for_areas), 0, 3);
                    echo "<!-- DEBUG AUTOVAL KEYS: " . implode(', ', $firstKeys) . " -->\n";
                }
            }
            $autovalutazioneAreas = aggregate_autovalutazione_by_area($autovalutazione_for_areas, $areaDescriptions, $sector);
        }
    }
}

// Verifica disponibilità autovalutazione (per mostrare/nascondere opzioni)
$checkAutovalutazione = get_student_self_assessment($userid, $courseid, $courseSector);
$hasAutovalutazione = !empty($checkAutovalutazione);
if ($hasAutovalutazione && !$autovalutazioneTimestamp) {
    $autovalutazioneTimestamp = $checkAutovalutazione['timestamp'];
}

// ============================================
// CARICAMENTO VALUTAZIONE FORMATORE (Coach Evaluation)
// ============================================
$hasCoachEvaluation = false;
$coachEvaluationData = null;
$coachRadarData = [];

// Determina il settore corrente per il filtro coach evaluation
$currentSector = ($cm_sector_filter !== 'all') ? $cm_sector_filter : ($studentPrimarySector ?: $sector);

if (!empty($currentSector)) {
    $coachEvaluations = coach_evaluation_manager::get_student_evaluations($userid, $currentSector);
    $hasCoachEvaluation = !empty($coachEvaluations);
    if ($hasCoachEvaluation) {
        // Prendi la valutazione più recente
        $coachEvaluationData = reset($coachEvaluations);
        // Pre-carica dati radar se necessario (anche per overlay)
        if ($showCoachEvaluation || $printCoachEvaluation || $showOverlayRadar || $printOverlayRadar) {
            $coachRadarData = coach_evaluation_manager::get_radar_data($userid, $currentSector);
        }
    }
}
// ============================================

// ============================================
// CARICAMENTO DATI LABEVAL (Valutazione Laboratorio)
// ============================================
$hasLabEval = false;
$labEvalData = [];
$labEvalByArea = [];

// Verifica se il plugin labeval esiste e carica i dati
if (file_exists(__DIR__ . '/../labeval/classes/api.php')) {
    require_once(__DIR__ . '/../labeval/classes/api.php');

    // Determina il settore per labeval
    $labEvalSector = strtoupper($currentSector ?: $sector);
    // Normalizza il settore per labeval
    if ($labEvalSector === 'ELETTRICITA' || $labEvalSector === 'ELETTRICITÀ') {
        $labEvalSector = 'ELETTRICITA';
    }

    if (!empty($labEvalSector)) {
        $labEvalData = \local_labeval\api::get_student_competency_scores($userid, $labEvalSector);
        $hasLabEval = !empty($labEvalData);

        // Aggrega per area
        if ($hasLabEval) {
            foreach ($labEvalData as $code => $data) {
                $areaInfo = get_area_info($code);
                $areaCode = $areaInfo['code'];
                if (!isset($labEvalByArea[$areaCode])) {
                    $labEvalByArea[$areaCode] = [
                        'code' => $areaCode,
                        'name' => $areaInfo['name'],
                        'total_percentage' => 0,
                        'count' => 0
                    ];
                }
                $labEvalByArea[$areaCode]['total_percentage'] += $data['percentage'];
                $labEvalByArea[$areaCode]['count']++;
            }
            // Calcola media per area
            foreach ($labEvalByArea as $code => &$area) {
                $area['percentage'] = round($area['total_percentage'] / $area['count'], 1);
            }
        }
    }
}
// ============================================

// ============================================
// CARICAMENTO SETTORI ASSEGNATI (per stampa)
// ============================================
$studentAssignedSectors = [];
$studentPrimarySector = null;
try {
    // Carica i settori assegnati allo studente dalla tabella local_student_sectors
    $studentAssignedSectors = \local_competencymanager\sector_manager::get_student_sectors_with_quiz_data($userid);
    // Il settore primario è il primo (type = 'primary')
    foreach ($studentAssignedSectors as $sec) {
        if (($sec->type ?? '') === 'primary' || empty($studentPrimarySector)) {
            $studentPrimarySector = $sec->sector ?? $sec->sector_alias ?? null;
            if (($sec->type ?? '') === 'primary') break;
        }
    }
} catch (Exception $e) {
    // Se sector_manager non disponibile, usa $sector dai competencies
    debugging('sector_manager not available: ' . $e->getMessage(), DEBUG_DEVELOPER);
}

// Se non c'è settore assegnato, usa quello rilevato dalle competenze
if (empty($studentPrimarySector) && !empty($sector)) {
    $studentPrimarySector = $sector;
}

// ============================================
// CARICAMENTO NOMI QUIZ E AUTOVALUTAZIONE (per stampa)
// ============================================
$selectedQuizNames = [];
$autovalutazioneQuizName = null;

// Carica i nomi dei quiz selezionati per la stampa
if (!empty($availableQuizzes)) {
    foreach ($availableQuizzes as $quiz) {
        // Se ci sono quiz selezionati, filtra solo quelli
        if (!empty($selectedQuizzes)) {
            if (in_array($quiz->id, $selectedQuizzes)) {
                $selectedQuizNames[] = format_string($quiz->name);
            }
        } else {
            // Se nessun quiz selezionato, usa tutti
            $selectedQuizNames[] = format_string($quiz->name);
        }
    }
}

// DEBUG: mostra i quiz caricati
if ($print) {
    echo "<!-- DEBUG QUIZ NAMES: " . count($selectedQuizNames) . " quiz - " . implode(', ', $selectedQuizNames) . " -->\n";
}

// Carica il nome del quiz/fonte autovalutazione
if (!empty($autovalutazioneResult)) {
    $dbman = $DB->get_manager();

    // Cerca il nome dalla tabella local_coachmanager_assessment
    if ($dbman->table_exists('local_coachmanager_assessment')) {
        $assessment = $DB->get_record_sql(
            "SELECT * FROM {local_coachmanager_assessment}
             WHERE userid = :userid AND courseid = :courseid
             ORDER BY timecreated DESC LIMIT 1",
            ['userid' => $userid, 'courseid' => $courseid]
        );
        if ($assessment) {
            // Prova a estrarre il nome dal campo details JSON
            $details = json_decode($assessment->details ?? '{}', true);
            if (!empty($details['quiz_name'])) {
                $autovalutazioneQuizName = format_string($details['quiz_name']);
            } elseif (!empty($details['source'])) {
                $autovalutazioneQuizName = format_string($details['source']);
            }
        }
    }

    // Se non trovato, cerca in local_selfassessment
    if (empty($autovalutazioneQuizName) && $dbman->table_exists('local_selfassessment')) {
        // Il plugin selfassessment non ha un nome quiz specifico, usa nome generico
        $autovalutazioneQuizName = 'Autovalutazione Competenze';
    }

    // Fallback
    if (empty($autovalutazioneQuizName)) {
        $autovalutazioneQuizName = 'Autovalutazione';
    }
}

// Include file di visualizzazione stampa
if ($print) {
    include(__DIR__ . '/student_report_print.php');
    exit;
}

// ========================================
// OUTPUT NORMALE
// ========================================
echo $OUTPUT->header();

// ========================================
// NAVIGATION BAR (Coach Friendly)
// ========================================
$nav_studentid = $userid;
$nav_studentname = fullname($student);
$nav_courseid = $courseid;
$nav_current = 'quiz';
include(__DIR__ . '/../coachmanager/coach_navigation.php');
?>

<style>
.badge-orange { background-color: #fd7e14; color: white; }
.radar-interactive-container { position: relative; }
.radar-controls { margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; }
.radar-controls .form-check { display: inline-block; margin-right: 15px; margin-bottom: 10px; }
.radar-controls .form-check-input { margin-right: 5px; }
.area-toggle { cursor: pointer; padding: 5px 10px; border-radius: 4px; margin: 2px; display: inline-block; transition: all 0.2s; }
.area-toggle:hover { opacity: 0.8; }
.area-toggle.disabled { opacity: 0.4; text-decoration: line-through; }
.stat-box { text-align: center; padding: 20px; border-radius: 12px; }
.stat-number { font-size: 2.5rem; font-weight: 700; }

/* Stili per sezioni additive */
.gap-row-sopra { background: #fadbd8 !important; }
.gap-row-sotto { background: #fef9e7 !important; }
.gap-row-allineato { background: #d5f5e3 !important; }

/* ============================================
   MAPPA COMPETENZE - Stile da reports_v2.php
   ============================================ */
.mappa-competenze-section {
    margin-top: 30px;
}

.mappa-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 12px 12px 0 0;
}

.mappa-header h3 {
    margin: 0;
    font-size: 1.3em;
}

.mappa-body {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 0 0 12px 12px;
    border: 1px solid #e9ecef;
    border-top: none;
}

.mappa-legend {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    padding: 15px;
    background: white;
    border-radius: 8px;
}

.mappa-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85em;
}

.mappa-legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.areas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 18px;
}

.area-card {
    background: white;
    border-radius: 14px;
    padding: 18px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    border-left: 5px solid;
    position: relative;
    overflow: hidden;
}

.area-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}

.area-card .area-icon { font-size: 2.2em; margin-bottom: 8px; }
.area-card .area-name { font-weight: 600; font-size: 1em; color: #2c3e50; margin-bottom: 4px; line-height: 1.3; }
.area-card .area-count { font-size: 0.8em; color: #7f8c8d; margin-bottom: 12px; }

.area-card .area-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 12px;
    border-top: 1px solid #eee;
}

.area-card .area-percentage { font-size: 1.4em; font-weight: 700; }
.area-card .area-status {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.7em;
    font-weight: 600;
    text-transform: uppercase;
}

.status-excellent { background: #d4edda; color: #155724; }
.status-good { background: #cce5ff; color: #004085; }
.status-warning { background: #fff3cd; color: #856404; }
.status-critical { background: #f8d7da; color: #721c24; }
.status-nodata { background: #e9ecef; color: #6c757d; }

/* Mini comparison bars */
.area-mini-comparison { display: flex; gap: 5px; margin-top: 8px; }
.mini-bar-container { flex: 1; }
.mini-bar-label { font-size: 0.65em; color: #7f8c8d; margin-bottom: 2px; }
.mini-bar { height: 4px; background: #e9ecef; border-radius: 2px; overflow: hidden; }
.mini-bar-fill { height: 100%; border-radius: 2px; }
.mini-bar-fill.quiz { background: #3498db; }
.mini-bar-fill.autoval { background: #9b59b6; }

/* Colori bordo per area */
.area-card[data-area*="ASS"] { border-left-color: #f39c12; }
.area-card[data-area*="AUT"] { border-left-color: #e74c3c; }
.area-card[data-area*="CSP"] { border-left-color: #8e44ad; }
.area-card[data-area*="CNC"] { border-left-color: #00bcd4; }
.area-card[data-area*="DIS"], .area-card[data-area*="DT"] { border-left-color: #3498db; }
.area-card[data-area*="LAV"] { border-left-color: #9e9e9e; }
.area-card[data-area*="LMC"] { border-left-color: #607d8b; }
.area-card[data-area*="LMB"] { border-left-color: #795548; }
.area-card[data-area*="MAN"], .area-card[data-area*="MAu"] { border-left-color: #e67e22; }
.area-card[data-area*="MIS"] { border-left-color: #1abc9c; }
.area-card[data-area*="PIA"] { border-left-color: #9b59b6; }
.area-card[data-area*="PRO"] { border-left-color: #2ecc71; }
.area-card[data-area*="SIC"], .area-card[data-area*="SAQ"] { border-left-color: #c0392b; }

/* Filter bar per Mappa Competenze */
.filter-bar-mappa {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    padding: 15px;
    background: white;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.filter-bar-mappa .filter-label {
    font-weight: 600;
    color: #495057;
}

.filter-bar-mappa select {
    padding: 8px 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    background: white;
    color: #495057;
    font-size: 0.9em;
    min-width: 150px;
    cursor: pointer;
}

.filter-bar-mappa select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
}

.filter-bar-mappa .btn-reset {
    padding: 8px 16px;
    background: #6c757d;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85em;
    transition: background 0.2s;
}

.filter-bar-mappa .btn-reset:hover {
    background: #5a6268;
}

/* Animazione fadeIn */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ============================================
   MODAL DETTAGLIO COMPETENZE
   ============================================ */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.modal-overlay.active { display: flex; }

.modal-content {
    background: white;
    border-radius: 16px;
    max-width: 850px;
    width: 100%;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px 25px;
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    border-radius: 16px 16px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 10;
}

.modal-header h2 {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.3em;
    margin: 0;
}

.modal-close {
    background: rgba(255,255,255,0.2);
    border: none;
    font-size: 1.5em;
    cursor: pointer;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover { background: rgba(255,255,255,0.3); }

.modal-body { padding: 20px 25px; }

/* Competency item */
.competency-item {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    margin-bottom: 12px;
    overflow: hidden;
}

.competency-header {
    padding: 15px 20px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
    transition: background 0.2s;
}

.competency-header:hover { background: #e9ecef; }

.competency-info { flex: 1; }
.competency-name { font-weight: 600; font-size: 0.95em; color: #2c3e50; margin-bottom: 3px; }
.competency-code { font-size: 0.8em; color: #7f8c8d; }

.competency-values { display: flex; gap: 12px; align-items: center; }

.value-box {
    text-align: center;
    min-width: 65px;
    padding: 6px 10px;
    background: white;
    border-radius: 8px;
}

.value-box .label { font-size: 0.65em; color: #7f8c8d; text-transform: uppercase; }
.value-box .value { font-size: 1em; font-weight: 600; }

.competency-toggle {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: 12px;
    transition: all 0.3s ease;
    font-size: 0.85em;
}

.competency-item.open .competency-toggle {
    background: #3498db;
    color: white;
    transform: rotate(180deg);
}

.competency-details {
    display: none;
    padding: 20px;
    background: white;
    border-top: 1px solid #e9ecef;
}

.competency-item.open .competency-details { display: block; }

.detail-section {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px dashed #e9ecef;
}

.detail-section:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }

.detail-section-title {
    font-size: 0.9em;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Quiz item in modal */
.quiz-item {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 10px;
    border-left: 4px solid #3498db;
}

.quiz-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.quiz-name { font-weight: 600; color: #2c3e50; }

.quiz-score {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
}

.quiz-score.good { background: #d4edda; color: #155724; }
.quiz-score.warning { background: #fff3cd; color: #856404; }
.quiz-score.bad { background: #f8d7da; color: #721c24; }

.quiz-details {
    display: flex;
    gap: 15px;
    font-size: 0.85em;
    color: #6c757d;
    flex-wrap: wrap;
}

.quiz-link {
    color: #3498db;
    text-decoration: none;
    font-weight: 500;
}

.quiz-link:hover { text-decoration: underline; }

.no-data-message {
    color: #6c757d;
    font-style: italic;
    background: #f8f9fa;
    border-radius: 8px;
    text-align: center;
}

.critical-value-box {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 12px 18px;
    text-align: center;
    min-width: 100px;
}

.critical-value-box .label {
    font-size: 0.75em;
    color: #7f8c8d;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.critical-value-box .value {
    font-size: 1.3em;
    font-weight: 700;
}

/* ============================================
   BARRA NAVIGAZIONE STUDENTE (Cascata)
   ============================================ */
.student-nav-bar {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    color: white;
}

.student-nav-bar .nav-title {
    font-size: 1.1em;
    font-weight: 600;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.student-nav-bar .nav-row {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.student-nav-bar .nav-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.student-nav-bar .nav-item label {
    font-size: 0.8em;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.student-nav-bar .nav-item select {
    padding: 10px 15px;
    border: 2px solid rgba(255,255,255,0.2);
    border-radius: 8px;
    background: rgba(255,255,255,0.1);
    color: white;
    font-size: 0.95em;
    min-width: 180px;
    cursor: pointer;
    transition: all 0.2s;
}

.student-nav-bar .nav-item select:hover {
    border-color: rgba(255,255,255,0.4);
    background: rgba(255,255,255,0.15);
}

.student-nav-bar .nav-item select:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.3);
}

.student-nav-bar .nav-item select option {
    background: #2c3e50;
    color: white;
}

.student-nav-bar .nav-arrow {
    font-size: 1.5em;
    opacity: 0.5;
    margin-top: 20px;
}

.student-nav-bar .nav-count {
    background: rgba(255,255,255,0.15);
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.85em;
    margin-top: 20px;
}

.student-nav-bar .nav-count strong {
    color: #3498db;
}

.student-nav-bar .btn-nav {
    padding: 10px 20px;
    background: #3498db;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 20px;
    transition: all 0.2s;
}

.student-nav-bar .btn-nav:hover {
    background: #2980b9;
    transform: translateY(-2px);
}

.student-nav-bar .btn-nav:disabled {
    background: rgba(255,255,255,0.2);
    cursor: not-allowed;
    transform: none;
}

/* Color indicators in select */
.color-indicator {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 8px;
}
</style>

<?php
// ============================================
// BARRA NAVIGAZIONE STUDENTE (Cascata)
// ============================================
// Carica dati iniziali per i selettori
$nav_cohorts = \local_competencymanager\sector_manager::get_cohorts_with_students();
$nav_colors = \local_competencymanager\sector_manager::get_colors_for_cohort(0);
$ftm_tables_exist = \local_competencymanager\sector_manager::ftm_scheduler_tables_exist();

// Ottieni settori disponibili per lo studente corrente (filtro intelligente)
$student_sectors = \local_competencymanager\sector_manager::get_student_sectors_with_quiz_data($userid);
?>

<div class="student-nav-bar">
    <div class="nav-title">
        👥 Navigazione Studente
        <span style="font-size: 0.7em; opacity: 0.7; font-weight: normal;">(selettori a cascata)</span>
    </div>

    <div class="nav-row">
        <!-- Coorte -->
        <div class="nav-item">
            <label>📚 Coorte</label>
            <select id="nav_cohort" onchange="loadColors()">
                <option value="0">Tutte le coorti</option>
                <?php foreach ($nav_cohorts as $coh): ?>
                <option value="<?php echo $coh->id; ?>">
                    <?php echo s($coh->name); ?> (<?php echo $coh->student_count; ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <span class="nav-arrow">→</span>

        <!-- Colore -->
        <div class="nav-item">
            <label>🎨 Colore Gruppo</label>
            <select id="nav_color" onchange="loadWeeks()" <?php echo !$ftm_tables_exist ? 'disabled' : ''; ?>>
                <option value="">Tutti i colori</option>
                <?php if ($ftm_tables_exist): ?>
                <?php foreach ($nav_colors as $col): ?>
                <option value="<?php echo $col->color; ?>" data-hex="<?php echo $col->color_hex; ?>">
                    <?php echo \local_competencymanager\sector_manager::COLOR_NAMES[$col->color] ?? ucfirst($col->color); ?> (<?php echo $col->student_count; ?>)
                </option>
                <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <span class="nav-arrow">→</span>

        <!-- Settimana KW -->
        <div class="nav-item">
            <label>📅 Settimana Inizio</label>
            <select id="nav_week" onchange="loadStudents()" <?php echo !$ftm_tables_exist ? 'disabled' : ''; ?>>
                <option value="0">Tutte le settimane</option>
            </select>
        </div>

        <span class="nav-arrow">→</span>

        <!-- Studente -->
        <div class="nav-item">
            <label>👤 Studente</label>
            <select id="nav_student" onchange="updateGoButton()">
                <option value="<?php echo $userid; ?>"><?php echo fullname($student); ?></option>
            </select>
        </div>

        <!-- Conteggio e Pulsante -->
        <div class="nav-count" id="nav_count">
            <strong id="student_count">1</strong> studente/i trovato/i
        </div>

        <button type="button" class="btn-nav" id="btn_go" onclick="goToStudent()" disabled>
            📊 Vai al Report
        </button>
    </div>
</div>

<?php if (!$ftm_tables_exist): ?>
<div class="alert alert-info mb-3">
    <small>ℹ️ Le tabelle FTM Scheduler non sono installate. I filtri Colore e Settimana non sono disponibili.</small>
</div>
<?php endif; ?>

<?php
// Header studente
echo '<div class="student-report-header mb-4 p-4 rounded" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">';
echo '<div class="row align-items-center">';
echo '<div class="col-auto">' . $OUTPUT->user_picture($student, ['size' => 80, 'class' => 'rounded-circle border border-white']) . '</div>';
echo '<div class="col"><h2 class="mb-1 text-white">' . fullname($student) . '</h2><p class="mb-0 opacity-75">' . $student->email . '</p>';
if ($course) echo '<p class="mb-0"><small>📚 ' . format_string($course->fullname) . '</small></p>';
echo '</div><div class="col-auto text-right"><div style="font-size: 3.5rem; font-weight: bold;">' . $summary['overall_percentage'] . '%</div><p class="mb-0 opacity-75">Punteggio globale</p></div></div></div>';

// Box valutazione
echo '<div class="card mb-4" style="border-left: 5px solid ' . $evaluation['color'] . ';"><div class="card-body"><div class="row align-items-center">';
echo '<div class="col-auto"><div style="font-size: 3rem;">' . $evaluation['icon'] . '</div></div>';
echo '<div class="col"><h4 class="mb-1" style="color: ' . $evaluation['color'] . ';">VALUTAZIONE: ' . $evaluation['label'] . '</h4><p class="mb-1">' . $evaluation['description'] . '</p><p class="mb-0"><strong>💡 Azione:</strong> ' . $evaluation['action'] . '</p></div>';
echo '<div class="col-auto text-center p-3 rounded" style="background: ' . $evaluation['bgColor'] . ';"><div style="font-size: 2rem; font-weight: bold; color: ' . $evaluation['color'] . ';">' . ($summary['correct_total'] ?? $summary['correct_questions']) . '/' . ($summary['questions_total'] ?? $summary['total_questions']) . '</div><small>risposte corrette</small></div></div></div></div>';

// ============================================
// PANNELLO DIAGNOSTICA: Quiz ultimi 7 giorni (TUTTI gli stati, TUTTI i corsi)
// ============================================
$sevenDaysAgo = time() - (7 * 24 * 60 * 60);
$recentAllAttempts = $DB->get_records_sql(
    "SELECT qa.id, qa.quiz, qa.state, qa.timestart, qa.timefinish,
            q.name as quizname, q.course as quizcourse, c.shortname as coursename
     FROM {quiz_attempts} qa
     JOIN {quiz} q ON q.id = qa.quiz
     JOIN {course} c ON c.id = q.course
     WHERE qa.userid = :userid
     AND qa.timestart > :sevendays
     ORDER BY qa.timestart DESC",
    ['userid' => $userid, 'sevendays' => $sevenDaysAgo]
);

if (!empty($recentAllAttempts)) {
    echo '<div class="card mb-4 border-primary">';
    echo '<div class="card-header bg-primary text-white"><h6 class="mb-0">📋 Quiz ultimi 7 giorni (tutti i corsi e stati) - Clicca per vedere risposte</h6></div>';
    echo '<div class="card-body p-2">';
    echo '<table class="table table-sm table-striped mb-0" style="font-size: 12px;">';
    echo '<thead><tr><th>Data</th><th>Quiz</th><th>Corso</th><th>Stato</th><th>Azioni</th></tr></thead><tbody>';
    foreach ($recentAllAttempts as $att) {
        $startDate = date('d/m/Y H:i', $att->timestart);
        $isCurrentCourse = ($att->quizcourse == $courseid);
        $rowClass = $isCurrentCourse ? '' : 'table-warning';
        $stateLabels = [
            'finished' => '<span class="badge badge-success">✅ Completato</span>',
            'inprogress' => '<span class="badge badge-info">⏳ In corso</span>',
            'overdue' => '<span class="badge badge-warning">⏱️ Scaduto</span>',
            'abandoned' => '<span class="badge badge-danger">❌ Abbandonato</span>'
        ];
        $stateLabel = $stateLabels[$att->state] ?? $att->state;
        $courseNote = $isCurrentCourse ? '' : ' <small class="text-danger">(altro corso!)</small>';

        // Link al review del quiz (solo per quiz completati)
        $reviewUrl = $CFG->wwwroot . '/mod/quiz/review.php?attempt=' . $att->id;
        $quizLink = '<a href="' . $reviewUrl . '" target="_blank" title="Apri review quiz">' . s($att->quizname) . '</a>';

        // Pulsante azione
        if ($att->state === 'finished') {
            $actionBtn = '<a href="' . $reviewUrl . '" target="_blank" class="btn btn-sm btn-outline-primary" title="Vedi domande e risposte">👁️ Review</a>';
        } else {
            $actionBtn = '<span class="text-muted">-</span>';
        }

        echo "<tr class='$rowClass'>";
        echo "<td>$startDate</td>";
        echo "<td>$quizLink</td>";
        echo "<td>" . s($att->coursename) . "$courseNote</td>";
        echo "<td>$stateLabel</td>";
        echo "<td>$actionBtn</td>";
        echo "</tr>";
    }
    echo '</tbody></table>';
    echo '<small class="text-muted">Righe gialle = quiz in altri corsi • Clicca sul nome quiz o "Review" per vedere domande e risposte</small>';
    echo '</div></div>';
}

// ============================================
// AVVISO: Quiz in corso (non ancora terminati) - SOLO CORSO CORRENTE
// ============================================
$inProgressAttempts = $DB->get_records_sql(
    "SELECT qa.id, qa.quiz, qa.state, qa.timestart, q.name as quizname
     FROM {quiz_attempts} qa
     JOIN {quiz} q ON q.id = qa.quiz
     WHERE qa.userid = :userid
     AND qa.state IN ('inprogress', 'overdue', 'abandoned')
     AND q.course = :courseid
     ORDER BY qa.timestart DESC",
    ['userid' => $userid, 'courseid' => $courseid]
);

if (!empty($inProgressAttempts)) {
    echo '<div class="alert alert-warning mb-4">';
    echo '<h5 class="alert-heading">⚠️ Quiz non terminati in questo corso</h5>';
    echo '<p class="mb-2">Questi quiz <strong>non appariranno nel radar</strong> finché non vengono completati:</p>';
    echo '<ul class="mb-0">';
    foreach ($inProgressAttempts as $att) {
        $startDate = date('d/m/Y H:i', $att->timestart);
        $stateLabel = [
            'inprogress' => '⏳ In corso',
            'overdue' => '⏱️ Scaduto',
            'abandoned' => '❌ Abbandonato'
        ][$att->state] ?? $att->state;
        echo '<li><strong>' . s($att->quizname) . '</strong> - ' . $stateLabel . ' (iniziato: ' . $startDate . ')</li>';
    }
    echo '</ul>';
    echo '</div>';
}

// ============================================
// PANNELLO FILTRO QUIZ AVANZATO
// ============================================
if (!empty($quizComparison)) {
    // Raggruppa quiz per settore (estrai dal nome)
    $quizBySector = [];
    foreach ($quizComparison as $quizId => $quiz) {
        $quizName = $quiz['name'];
        // Estrai settore dal nome (es. "ELETTRICITÀ - ELETTRICITA_APPR01_..." -> ELETTRICITÀ)
        $sector = 'ALTRO';
        if (preg_match('/^(ELETTRICITÀ|ELETTRICITA|AUTOMOBILE|AUTOVEICOLO|MECCANICA|GEN|GENERICO|CHIMFARM|LOGISTICA|AUTOMAZIONE|METALCOSTRUZIONE)\s*[-–]/i', $quizName, $matches)) {
            $sector = strtoupper(trim($matches[1]));
            // Normalizza
            if ($sector === 'ELETTRICITA') $sector = 'ELETTRICITÀ';
            if ($sector === 'AUTOVEICOLO') $sector = 'AUTOMOBILE';
            if ($sector === 'GENERICO') $sector = 'GEN';
        } elseif (preg_match('/^(ELETTRICITÀ|ELETTRICITA|AUTOMOBILE|AUTOVEICOLO|MECCANICA|GEN|GENERICO|CHIMFARM|LOGISTICA|AUTOMAZIONE|METALCOSTRUZIONE)_/i', $quizName, $matches)) {
            $sector = strtoupper(trim($matches[1]));
            if ($sector === 'ELETTRICITA') $sector = 'ELETTRICITÀ';
            if ($sector === 'AUTOVEICOLO') $sector = 'AUTOMOBILE';
            if ($sector === 'GENERICO') $sector = 'GEN';
        }

        if (!isset($quizBySector[$sector])) {
            $quizBySector[$sector] = [];
        }
        $quiz['sector'] = $sector;
        $quizBySector[$sector][$quizId] = $quiz;
    }
    ksort($quizBySector);

    // Icone settori
    $sectorIcons = [
        'ELETTRICITÀ' => '⚡', 'AUTOMOBILE' => '🚗', 'MECCANICA' => '⚙️',
        'GEN' => '📋', 'CHIMFARM' => '🧪', 'LOGISTICA' => '📦',
        'AUTOMAZIONE' => '🤖', 'METALCOSTRUZIONE' => '🔩', 'ALTRO' => '📁'
    ];

    // Parametro filtro tentativi
    $attemptFilter = optional_param('attempt_filter', 'all', PARAM_ALPHA);
    ?>
    <div class="card mb-4">
        <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; cursor: pointer;"
             onclick="document.getElementById('quizFilterPanel').classList.toggle('d-none'); this.querySelector('.toggle-icon').textContent = document.getElementById('quizFilterPanel').classList.contains('d-none') ? '▶' : '▼';">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">🔍 Filtra Quiz per Analisi Radar</h5>
                <span class="toggle-icon">▼</span>
            </div>
            <small class="d-block mt-1 opacity-75">Clicca per espandere/comprimere • Seleziona i quiz da includere nel grafico</small>
        </div>
        <div class="card-body" id="quizFilterPanel">
            <form method="get" id="quizFilterForm">
                <input type="hidden" name="userid" value="<?php echo $userid; ?>">
                <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
                <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                <?php if ($selectedArea): ?><input type="hidden" name="selectedarea" value="<?php echo $selectedArea; ?>"><?php endif; ?>
                <?php if ($showDualRadar): ?><input type="hidden" name="show_dual_radar" value="1"><?php endif; ?>
                <?php if ($showGapAnalysis): ?><input type="hidden" name="show_gap" value="1"><?php endif; ?>
                <?php if ($showSpuntiColloquio): ?><input type="hidden" name="show_spunti" value="1"><?php endif; ?>
                <?php if ($showCoachEvaluation): ?><input type="hidden" name="show_coach_eval" value="1"><?php endif; ?>
                <?php if (!empty($currentSector)): ?><input type="hidden" name="cm_sector" value="<?php echo $currentSector; ?>"><?php endif; ?>

                <!-- Filtro tentativi -->
                <div class="alert alert-light border mb-3">
                    <div class="d-flex align-items-center flex-wrap">
                        <strong class="mr-3">📊 Tentativi da includere:</strong>
                        <div class="btn-group btn-group-toggle" data-toggle="buttons">
                            <label class="btn btn-outline-primary btn-sm <?php echo $attemptFilter === 'all' ? 'active' : ''; ?>">
                                <input type="radio" name="attempt_filter" value="all" <?php echo $attemptFilter === 'all' ? 'checked' : ''; ?>> Tutti
                            </label>
                            <label class="btn btn-outline-primary btn-sm <?php echo $attemptFilter === 'last' ? 'active' : ''; ?>">
                                <input type="radio" name="attempt_filter" value="last" <?php echo $attemptFilter === 'last' ? 'checked' : ''; ?>> Solo ultimo
                            </label>
                            <label class="btn btn-outline-primary btn-sm <?php echo $attemptFilter === 'first' ? 'active' : ''; ?>">
                                <input type="radio" name="attempt_filter" value="first" <?php echo $attemptFilter === 'first' ? 'checked' : ''; ?>> Solo primo
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Quiz raggruppati per settore -->
                <?php foreach ($quizBySector as $sector => $quizzes): ?>
                <div class="mb-3">
                    <div class="d-flex align-items-center mb-2">
                        <span style="font-size: 1.5rem;" class="mr-2"><?php echo $sectorIcons[$sector] ?? '📁'; ?></span>
                        <h6 class="mb-0 mr-3"><?php echo $sector; ?></h6>
                        <button type="button" class="btn btn-outline-success btn-sm py-0 mr-1"
                                onclick="document.querySelectorAll('.quiz-check-<?php echo strtolower(preg_replace('/[^a-z]/i', '', $sector)); ?>').forEach(c=>c.checked=true)">
                            ✓ Tutti
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm py-0"
                                onclick="document.querySelectorAll('.quiz-check-<?php echo strtolower(preg_replace('/[^a-z]/i', '', $sector)); ?>').forEach(c=>c.checked=false)">
                            ✗ Nessuno
                        </button>
                    </div>
                    <div class="row">
                        <?php foreach ($quizzes as $quizId => $quiz):
                            $isSelected = empty($selectedQuizzes) || in_array($quizId, $selectedQuizzes);
                            $attempts = $quiz['attempts'];
                            $lastAttempt = end($attempts);
                            $firstAttempt = reset($attempts);
                            $bestScore = max(array_column($attempts, 'percentage'));
                            $worstScore = min(array_column($attempts, 'percentage'));

                            // Estrai nome breve dal quiz (rimuovi prefisso settore e timestamp)
                            $shortName = $quiz['name'];
                            $shortName = preg_replace('/^[A-ZÀÈÉÌÒÙ]+\s*[-–]\s*/i', '', $shortName);
                            $shortName = preg_replace('/^[A-Z]+_[A-Z]+_([A-Z]+\d+)_/i', '$1 - ', $shortName);
                            $shortName = preg_replace('/_\d{8}_\d+$/', '', $shortName);
                            $shortName = str_replace('_', ' ', $shortName);
                            if (strlen($shortName) > 50) $shortName = substr($shortName, 0, 47) . '...';

                            $sectorClass = strtolower(preg_replace('/[^a-z]/i', '', $sector));
                        ?>
                        <div class="col-md-6 col-lg-4 mb-2">
                            <div class="p-2 border rounded <?php echo $isSelected ? 'border-success' : ''; ?>"
                                 style="<?php echo $isSelected ? 'background: #e8f5e9;' : 'background: #f8f9fa;'; ?>">
                                <div class="form-check mb-1">
                                    <input type="checkbox" class="form-check-input quiz-checkbox quiz-check-<?php echo $sectorClass; ?>"
                                           name="quizids[]" value="<?php echo $quizId; ?>"
                                           id="quiz_<?php echo $quizId; ?>" <?php echo $isSelected ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="quiz_<?php echo $quizId; ?>">
                                        <strong><?php echo s($shortName); ?></strong>
                                    </label>
                                </div>
                                <div class="small mb-1" style="color: #495057;">
                                    <?php echo count($attempts); ?> tentativo/i •
                                    <?php if (count($attempts) > 1): ?>
                                        <?php echo $worstScore; ?>% → <?php echo $bestScore; ?>%
                                    <?php else: ?>
                                        <?php echo $lastAttempt['percentage']; ?>%
                                    <?php endif; ?>
                                    • <strong>Ultimo:</strong> <?php echo date('d/m/Y', $lastAttempt['timefinish']); ?>
                                </div>
                                <!-- Link ai tentativi con contrasto migliorato -->
                                <div class="d-flex flex-wrap" style="gap: 4px;">
                                    <?php foreach ($attempts as $attempt):
                                        // Colori ad alto contrasto per i badge
                                        if ($attempt['percentage'] >= 60) {
                                            $bgColor = '#155724'; // Verde scuro
                                            $textColor = '#fff';
                                        } elseif ($attempt['percentage'] >= 40) {
                                            $bgColor = '#856404'; // Giallo scuro
                                            $textColor = '#fff';
                                        } else {
                                            $bgColor = '#721c24'; // Rosso scuro
                                            $textColor = '#fff';
                                        }
                                    ?>
                                    <a href="<?php echo $CFG->wwwroot; ?>/mod/quiz/review.php?attempt=<?php echo $attempt['attemptid']; ?>"
                                       target="_blank"
                                       style="cursor: pointer; text-decoration: none; background: <?php echo $bgColor; ?>; color: <?php echo $textColor; ?>; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;"
                                       title="Clicca per vedere domande e risposte del tentativo #<?php echo $attempt['attempt_number']; ?>">
                                        #<?php echo $attempt['attempt_number']; ?> <?php echo $attempt['percentage']; ?>%
                                        (<?php echo date('d/m', $attempt['timefinish']); ?>)
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Barra azioni -->
                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                    <div>
                        <button type="button" class="btn btn-outline-primary btn-sm mr-2"
                                onclick="document.querySelectorAll('.quiz-checkbox').forEach(c=>c.checked=true)">
                            ✅ Seleziona Tutti
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                onclick="document.querySelectorAll('.quiz-checkbox').forEach(c=>c.checked=false)">
                            ☐ Deseleziona Tutti
                        </button>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="text-muted mr-3" id="quizSelectionCount">
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    function updateCount() {
                                        var checked = document.querySelectorAll('.quiz-checkbox:checked').length;
                                        var total = document.querySelectorAll('.quiz-checkbox').length;
                                        document.getElementById('quizSelectionCount').innerHTML =
                                            '<strong>' + checked + '</strong>/' + total + ' quiz selezionati';
                                    }
                                    updateCount();
                                    document.querySelectorAll('.quiz-checkbox').forEach(function(cb) {
                                        cb.addEventListener('change', updateCount);
                                    });
                                });
                            </script>
                        </span>
                        <button type="submit" class="btn btn-success">
                            🔄 Aggiorna Grafici
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php
}

// ============================================
// AVVISO: Quiz selezionati senza competenze
// ============================================
if (!empty($selectedQuizzes) && empty($competencies)) {
    // Verifica quali quiz hanno competenze (query compatibile Moodle 4.x)
    $quizzesWithoutComp = [];
    foreach ($selectedQuizzes as $qid) {
        $compCount = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT qcbq.competencyid)
             FROM {quiz_slots} qs
             JOIN {question_references} qr ON qr.component = 'mod_quiz'
                  AND qr.questionarea = 'slot' AND qr.itemid = qs.id
             JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
             JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
             JOIN {qbank_competenciesbyquestion} qcbq ON qcbq.questionid = qv.questionid
             WHERE qs.quizid = :quizid",
            ['quizid' => $qid]
        );
        if ($compCount == 0) {
            $quizName = $DB->get_field('quiz', 'name', ['id' => $qid]);
            $quizzesWithoutComp[] = $quizName ?: "Quiz ID $qid";
        }
    }

    if (!empty($quizzesWithoutComp)) {
        ?>
        <div class="alert alert-warning mb-4">
            <h5 class="alert-heading">⚠️ Nessun radar disponibile per i quiz selezionati</h5>
            <p class="mb-2">I seguenti quiz <strong>non hanno competenze assegnate</strong> alle loro domande:</p>
            <ul class="mb-2">
                <?php foreach ($quizzesWithoutComp as $qn): ?>
                <li><?php echo s($qn); ?></li>
                <?php endforeach; ?>
            </ul>
            <hr>
            <p class="mb-0">
                <strong>💡 Suggerimento:</strong> Per visualizzare il radar, è necessario che le domande del quiz abbiano
                competenze assegnate tramite il plugin "Competenze per Domanda" (qbank_competenciesbyquestion).
                <br>Vai su <strong>Amministrazione del sito → Domande → Banca delle domande</strong> per assegnare le competenze.
            </p>
        </div>
        <?php
    }
}

// ============================================
// PANNELLO OPZIONI ADDITIVE (CoachManager)
// ============================================
?>
<div class="card mb-4">
    <div class="card-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
        <h5 class="mb-0">🔧 Opzioni Visualizzazione Aggiuntive</h5>
    </div>
    <div class="card-body">
        <form method="get" id="additiveOptionsForm">
            <input type="hidden" name="userid" value="<?php echo $userid; ?>">
            <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
            <input type="hidden" name="tab" value="<?php echo $tab; ?>">
            <?php foreach ($selectedQuizzes as $qid): ?>
            <input type="hidden" name="quizids[]" value="<?php echo $qid; ?>">
            <?php endforeach; ?>
            
            <div class="row">
                <div class="col-md-7">
                    <h6>🏭 Filtro Settore:</h6>
                    <?php
                    // Icone e nomi per i settori
                    $sector_display = [
                        'GENERICO' => ['icon' => '📋', 'name' => 'Generico', 'code' => 'gen'],
                        'GEN' => ['icon' => '📋', 'name' => 'Generico', 'code' => 'gen'],
                        'AUTOMOBILE' => ['icon' => '🚗', 'name' => 'Automobile', 'code' => 'automobile'],
                        'AUTOMAZIONE' => ['icon' => '🤖', 'name' => 'Automazione', 'code' => 'automazione'],
                        'CHIMFARM' => ['icon' => '🧪', 'name' => 'Chimico-Farmaceutico', 'code' => 'chimfarm'],
                        'ELETTRICITA' => ['icon' => '⚡', 'name' => 'Elettricità', 'code' => 'elettricita'],
                        'LOGISTICA' => ['icon' => '📦', 'name' => 'Logistica', 'code' => 'logistica'],
                        'MECCANICA' => ['icon' => '⚙️', 'name' => 'Meccanica', 'code' => 'meccanica'],
                        'METALCOSTRUZIONE' => ['icon' => '🔩', 'name' => 'Metalcostruzione', 'code' => 'metalcostruzione'],
                    ];

                    // Conta quiz totali per settore dello studente
                    $total_quiz_count = 0;
                    foreach ($student_sectors as $sec) {
                        $total_quiz_count += (int)$sec->quiz_count;
                    }
                    ?>

                    <?php if (empty($student_sectors)): ?>
                    <!-- Nessun settore rilevato - mostra messaggio -->
                    <div class="alert alert-warning mb-3">
                        <small>⚠️ Nessun settore rilevato per questo studente. Non ha ancora completato quiz con competenze.</small>
                    </div>
                    <div class="form-group mb-3">
                        <select name="cm_sector" id="cm_sector" class="form-control" disabled>
                            <option value="all">Nessun settore disponibile</option>
                        </select>
                    </div>
                    <?php else: ?>
                    <!-- Settori disponibili per lo studente -->
                    <div class="alert alert-success py-2 mb-2">
                        <small>
                            ✅ <strong><?php echo count($student_sectors); ?></strong> settore/i rilevato/i
                            (<strong><?php echo $total_quiz_count; ?></strong> quiz completati)
                        </small>
                    </div>
                    <div class="form-group mb-3">
                        <select name="cm_sector" id="cm_sector" class="form-control" onchange="this.form.submit()" style="font-weight: 500;">
                            <option value="all" <?php echo $cm_sector_filter === "all" ? "selected" : ""; ?>>
                                🌐 Tutti i settori (<?php echo $total_quiz_count; ?> quiz)
                            </option>
                            <?php foreach ($student_sectors as $sec):
                                $display = $sector_display[strtoupper($sec->sector)] ?? [
                                    'icon' => '📁',
                                    'name' => ucfirst(strtolower($sec->sector)),
                                    'code' => strtolower($sec->sector)
                                ];
                                $is_selected = (strtolower($cm_sector_filter) === $display['code']);
                                $is_primary = !empty($sec->is_primary);
                            ?>
                            <option value="<?php echo $display['code']; ?>" <?php echo $is_selected ? "selected" : ""; ?>>
                                <?php echo $display['icon']; ?> <?php echo $display['name']; ?>
                                (<?php echo $sec->quiz_count; ?> quiz)
                                <?php echo $is_primary ? ' ⭐' : ''; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <h6>📊 Sezioni Aggiuntive (CoachManager):</h6>
                    
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="show_dual_radar" 
                               name="show_dual_radar" value="1" <?php echo $showDualRadar ? 'checked' : ''; ?>
                               <?php echo !$hasAutovalutazione ? 'disabled' : ''; ?>>
                        <label class="custom-control-label" for="show_dual_radar">
                            🎯 <strong>Doppio Radar</strong> (Autovalutazione affiancato)
                        </label>
                    </div>
                    
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="show_gap" 
                               name="show_gap" value="1" <?php echo $showGapAnalysis ? 'checked' : ''; ?>
                               <?php echo !$hasAutovalutazione ? 'disabled' : ''; ?>>
                        <label class="custom-control-label" for="show_gap">
                            📈 <strong>Gap Analysis</strong> (Confronto Auto vs Reale)
                        </label>
                    </div>
                    
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="show_spunti"
                               name="show_spunti" value="1" <?php echo $showSpuntiColloquio ? 'checked' : ''; ?>
                               <?php echo !$hasAutovalutazione ? 'disabled' : ''; ?>>
                        <label class="custom-control-label" for="show_spunti">
                            💬 <strong>Spunti Colloquio</strong> (Domande suggerite)
                        </label>
                    </div>

                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="show_coach_eval"
                               name="show_coach_eval" value="1" <?php echo $showCoachEvaluation ? 'checked' : ''; ?>
                               <?php echo !$hasCoachEvaluation ? 'disabled' : ''; ?>>
                        <label class="custom-control-label" for="show_coach_eval">
                            👨‍🏫 <strong>Valutazione Formatore</strong> (Valutazione coach Bloom)
                        </label>
                    </div>

                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="show_overlay"
                               name="show_overlay" value="1" <?php echo $showOverlayRadar ? 'checked' : ''; ?>
                               onchange="this.form.submit()">
                        <label class="custom-control-label" for="show_overlay">
                            🔀 <strong>Grafico Sovrapposizione</strong> (Quiz + Auto + Formatore + Lab)
                        </label>
                    </div>

                    <!-- SOGLIE CONFIGURABILI -->
                    <hr class="my-3">
                    <h6>⚙️ Configurazione Soglie:</h6>
                    <div class="row">
                        <div class="col-6">
                            <label for="soglia_allineamento" class="small">
                                <strong>Soglia Allineamento</strong><br>
                                <span class="text-muted">(Gap ≤ questa % = allineato)</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control" id="soglia_allineamento" 
                                       name="soglia_allineamento" value="<?php echo $sogliaAllineamento; ?>"
                                       min="5" max="40" step="5" <?php echo !$hasAutovalutazione ? 'disabled' : ''; ?>>
                                <div class="input-group-append">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <label for="soglia_critico" class="small">
                                <strong>Soglia Gap Critico</strong><br>
                                <span class="text-muted">(Gap > questa % = critico)</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control" id="soglia_critico" 
                                       name="soglia_critico" value="<?php echo $sogliaCritico; ?>"
                                       min="20" max="60" step="5" <?php echo !$hasAutovalutazione ? 'disabled' : ''; ?>>
                                <div class="input-group-append">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">
                        📊 <strong>Interpretazione:</strong> 
                        Gap ≤ <?php echo $sogliaAllineamento; ?>% = ✅ Allineato | 
                        Gap <?php echo $sogliaAllineamento; ?>-<?php echo $sogliaCritico; ?>% = ⚠️ Attenzione | 
                        Gap > <?php echo $sogliaCritico; ?>% = 🔴 Critico
                    </small>
                </div>
                
                <div class="col-md-5">
                    <?php if (!$hasAutovalutazione): ?>
                    <div class="alert alert-info mb-2">
                        <h6 class="mb-1">ℹ️ Autovalutazione non disponibile</h6>
                        <p class="mb-0 small">Lo studente non ha ancora completato un'autovalutazione nel CoachManager.</p>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-success mb-2">
                        <h6 class="mb-1">✅ Autovalutazione disponibile</h6>
                        <p class="mb-0 small">
                            <strong>Data:</strong> <?php echo userdate($autovalutazioneTimestamp); ?><br>
                            <strong>Competenze:</strong> <?php echo count($checkAutovalutazione['data']); ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if (!$hasCoachEvaluation): ?>
                    <div class="alert alert-secondary mb-0">
                        <h6 class="mb-1">👨‍🏫 Valutazione Formatore non disponibile</h6>
                        <p class="mb-0 small">
                            <?php if (!empty($currentSector)): ?>
                                Nessuna valutazione coach per il settore <?php echo s($currentSector); ?>.
                                <a href="<?php echo new moodle_url('/local/competencymanager/coach_evaluation.php', [
                                    'studentid' => $userid,
                                    'sector' => $currentSector,
                                    'courseid' => $courseid
                                ]); ?>" class="alert-link">➕ Crea valutazione</a>
                            <?php else: ?>
                                Seleziona un settore per creare una valutazione.
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-success mb-0">
                        <h6 class="mb-1">👨‍🏫 Valutazione Formatore disponibile</h6>
                        <p class="mb-0 small">
                            <strong>Coach:</strong> <?php echo fullname($DB->get_record('user', ['id' => $coachEvaluationData->coachid])); ?><br>
                            <strong>Stato:</strong> <?php echo ucfirst($coachEvaluationData->status); ?>
                            <a href="<?php echo new moodle_url('/local/competencymanager/coach_evaluation.php', [
                                'studentid' => $userid,
                                'sector' => $currentSector,
                                'evaluationid' => $coachEvaluationData->id,
                                'courseid' => $courseid
                            ]); ?>" class="alert-link ml-2">✏️ Modifica</a>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mt-3 text-right">
                <button type="submit" class="btn btn-success">🔄 Applica Opzioni</button>
            </div>
        </form>
    </div>
</div>
<?php
// ============================================

// Progresso certificazione
echo '<div class="card mb-4"><div class="card-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;"><h5 class="mb-0">📊 Progresso Certificazione</h5></div><div class="card-body">';
echo '<div class="progress mb-3" style="height: 30px;"><div class="progress-bar bg-success" style="width: ' . $certProgress['percentage'] . '%;"><strong>' . $certProgress['percentage'] . '% completato</strong></div></div>';
echo '<div class="row text-center"><div class="col-md-4"><div class="p-3 rounded" style="background: #d4edda;"><h3 class="text-success mb-0">' . $certProgress['certified'] . '</h3><small>✅ Certificate (≥80%)</small></div></div>';
echo '<div class="col-md-4"><div class="p-3 rounded" style="background: #fff3cd;"><h3 class="text-warning mb-0">' . $certProgress['inProgress'] . '</h3><small>🔄 In corso</small></div></div>';
echo '<div class="col-md-4"><div class="p-3 rounded" style="background: #f8d7da;"><h3 class="text-danger mb-0">' . $certProgress['notStarted'] . '</h3><small>⏳ Da iniziare</small></div></div></div></div></div>';

// Barra azioni
echo '<div class="card mb-4"><div class="card-body d-flex justify-content-between align-items-center flex-wrap">';
echo '<a href="' . new moodle_url('/local/competencymanager/reports.php', ['courseid' => $courseid]) . '" class="btn btn-secondary mb-2">← Torna alla lista</a>';
echo '<div><button type="button" class="btn btn-primary mr-2 mb-2" data-toggle="modal" data-target="#printModal">🖨️ Stampa Personalizzata</button>';
echo '<a href="' . new moodle_url('/local/competencymanager/export.php', ['userid' => $userid, 'courseid' => $courseid, 'format' => 'csv']) . '" class="btn btn-success mr-2 mb-2">📥 CSV</a>';
echo '<a href="' . new moodle_url('/local/competencymanager/export.php', ['userid' => $userid, 'courseid' => $courseid, 'format' => 'excel']) . '" class="btn btn-success mb-2">📥 Excel</a></div></div></div>';

// ========================================
// MODALE STAMPA PERSONALIZZATA
// ========================================
?>
<div class="modal fade" id="printModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">🖨️ Stampa Personalizzata</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="printForm" method="get" target="_blank" action="<?php echo new moodle_url('/local/competencymanager/student_report.php'); ?>">
                    <input type="hidden" name="userid" value="<?php echo $userid; ?>">
                    <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
                    <input type="hidden" name="print" value="1">
                    <input type="hidden" name="print_form" value="1">
                    <?php foreach ($selectedQuizzes as $qid): ?>
                    <input type="hidden" name="quizids[]" value="<?php echo $qid; ?>">
                    <?php endforeach; ?>

                    <!-- SOGLIE CONFIGURABILI - passate alla stampa -->
                    <input type="hidden" name="soglia_allineamento" value="<?php echo $sogliaAllineamento; ?>">
                    <input type="hidden" name="soglia_critico" value="<?php echo $sogliaCritico; ?>">
                    <input type="hidden" name="soglia_monitorare" value="<?php echo $sogliaMonitorare; ?>">

                    <!-- FILTRO SETTORE PER STAMPA -->
                    <div class="mb-3 p-3" style="background: #f8f9fa; border-radius: 8px;">
                        <h6>🏭 Filtra per Settore:</h6>
                        <select name="print_sector" class="form-control">
                            <option value="all">Tutti i settori</option>
                            <?php
                            // Estrai settori unici dalle competenze
                            $printSectors = [];
                            foreach ($competencies as $comp) {
                                $idnumber = $comp['idnumber'] ?? '';
                                if (!empty($idnumber)) {
                                    $parts = explode('_', $idnumber);
                                    if (count($parts) >= 2) {
                                        $sec = strtoupper($parts[0]);
                                        if (!isset($printSectors[$sec])) {
                                            $printSectors[$sec] = 0;
                                        }
                                        $printSectors[$sec]++;
                                    }
                                }
                            }
                            arsort($printSectors);
                            foreach ($printSectors as $sec => $count): ?>
                            <option value="<?php echo $sec; ?>"><?php echo $sec; ?> (<?php echo $count; ?> competenze)</option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Seleziona un settore per stampare solo le competenze di quel settore</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h6>📋 Sezioni da includere:</h6>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input section-check" id="print_panoramica" name="print_panoramica" value="1" checked>
                                <label class="custom-control-label" for="print_panoramica"><strong>📊 Panoramica e Valutazione</strong></label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input section-check" id="print_progressi" name="print_progressi" value="1" checked>
                                <label class="custom-control-label" for="print_progressi"><strong>📈 Progresso Certificazione</strong></label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input section-check" id="print_piano" name="print_piano" value="1" checked>
                                <label class="custom-control-label" for="print_piano"><strong>📚 Piano d'Azione</strong></label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input section-check" id="print_dettagli" name="print_dettagli" value="1" checked>
                                <label class="custom-control-label" for="print_dettagli"><strong>📋 Tabella Dettagli</strong></label>
                            </div>
                            
                            <!-- NUOVE OPZIONI STAMPA ADDITIVE -->
                            <?php if ($hasAutovalutazione): ?>
                            <hr>
                            <h6 class="text-success">🆕 Sezioni CoachManager:</h6>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input section-check" id="print_dual_radar" name="print_dual_radar" value="1">
                                <label class="custom-control-label" for="print_dual_radar"><strong>🎯 Doppio Radar</strong></label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input section-check" id="print_gap" name="print_gap" value="1">
                                <label class="custom-control-label" for="print_gap"><strong>📈 Gap Analysis</strong></label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input section-check" id="print_spunti" name="print_spunti" value="1">
                                <label class="custom-control-label" for="print_spunti"><strong>💬 Spunti Colloquio</strong></label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input section-check" id="print_suggerimenti" name="print_suggerimenti" value="1">
                                <label class="custom-control-label" for="print_suggerimenti"><strong>📋 Suggerimenti Rapporto</strong></label>
                                <small class="d-block text-muted ml-4">Commenti automatici basati sul gap con attivita lavorative</small>
                            </div>
                            <!-- Opzioni tono commenti (visibili solo se suggerimenti abilitati) -->
                            <div id="suggerimentiOptions" class="ml-4 mt-2 mb-2 p-2" style="background: #f8f9fa; border-radius: 5px; border-left: 3px solid #667eea; display: none;">
                                <label class="small font-weight-bold">Tono commenti:</label>
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" class="custom-control-input" id="tono_formale" name="tono_commenti" value="formale" checked>
                                    <label class="custom-control-label" for="tono_formale">Formale <small class="text-muted">(URC/Aziende)</small></label>
                                </div>
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" class="custom-control-input" id="tono_colloquiale" name="tono_commenti" value="colloquiale">
                                    <label class="custom-control-label" for="tono_colloquiale">Colloquiale <small class="text-muted">(Coach interno)</small></label>
                                </div>
                            </div>
                            <script>
                            document.getElementById('print_suggerimenti').addEventListener('change', function() {
                                document.getElementById('suggerimentiOptions').style.display = this.checked ? 'block' : 'none';
                            });
                            </script>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6>📊 Grafici Radar:</h6>
                            <div class="custom-control custom-checkbox mb-3">
                                <input type="checkbox" class="custom-control-input" id="print_radar_aree" name="print_radar_aree" value="1" checked>
                                <label class="custom-control-label" for="print_radar_aree"><strong>🎯 Radar Panoramica Aree</strong></label>
                            </div>
                            
                            <h6>🔍 Radar Dettaglio per Area:</h6>
                            <p class="small text-muted">Seleziona le aree per cui generare un radar dettagliato delle competenze:</p>
                            <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($areasData as $areaCode => $areaInfo): ?>
                                <div class="custom-control custom-checkbox mb-1">
                                    <input type="checkbox" class="custom-control-input area-detail-check" id="print_area_<?php echo $areaCode; ?>" name="print_radar_areas[]" value="<?php echo $areaCode; ?>">
                                    <label class="custom-control-label" for="print_area_<?php echo $areaCode; ?>">
                                        <?php echo $areaInfo['icon'] . ' ' . $areaInfo['name']; ?>
                                        <span class="badge badge-<?php echo get_evaluation_band($areaInfo['percentage'])['class']; ?>"><?php echo $areaInfo['percentage']; ?>%</span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll('.area-detail-check').forEach(c=>c.checked=true)">Tutte</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll('.area-detail-check').forEach(c=>c.checked=false)">Nessuna</button>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <div class="d-flex justify-content-between">
                        <div>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.querySelectorAll('.section-check').forEach(c=>c.checked=true)">✅ Tutte le sezioni</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll('.section-check').forEach(c=>c.checked=false)">☐ Nessuna sezione</button>
                        </div>
                    </div>

                    <!-- ============================================ -->
                    <!-- ORDINAMENTO SEZIONI STAMPA                   -->
                    <!-- ============================================ -->
                    <hr>
                    <div class="mb-3">
                        <h6 class="d-flex justify-content-between align-items-center" style="cursor: pointer;" onclick="document.getElementById('orderSection').classList.toggle('d-none');">
                            🔢 Ordine Sezioni di Stampa
                            <small class="text-muted">(clicca per espandere)</small>
                        </h6>
                        <div id="orderSection" class="d-none mt-2 p-3" style="background: #f0f4f8; border-radius: 8px; border: 1px solid #dee2e6;">
                            <p class="small text-muted mb-2">Imposta l'ordine di stampa delle sezioni (1 = primo, numeri più alti = dopo):</p>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-2 d-flex align-items-center">
                                        <select name="order_valutazione" class="form-control form-control-sm mr-2" style="width: 60px;">
                                            <option value="1" selected>1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                            <option value="6">6</option>
                                            <option value="7">7</option>
                                            <option value="8">8</option>
                                            <option value="9">9</option>
                                        </select>
                                        <label class="mb-0">📋 Scheda Valutazione</label>
                                    </div>
                                    <div class="form-group mb-2 d-flex align-items-center">
                                        <select name="order_progressi" class="form-control form-control-sm mr-2" style="width: 60px;">
                                            <option value="1">1</option>
                                            <option value="2" selected>2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                            <option value="6">6</option>
                                            <option value="7">7</option>
                                            <option value="8">8</option>
                                            <option value="9">9</option>
                                        </select>
                                        <label class="mb-0">📈 Progresso Certificazione</label>
                                    </div>
                                    <div class="form-group mb-2 d-flex align-items-center">
                                        <select name="order_radar_aree" class="form-control form-control-sm mr-2" style="width: 60px;">
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3" selected>3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                            <option value="6">6</option>
                                            <option value="7">7</option>
                                            <option value="8">8</option>
                                            <option value="9">9</option>
                                        </select>
                                        <label class="mb-0">🎯 Radar Panoramica Aree</label>
                                    </div>
                                    <div class="form-group mb-2 d-flex align-items-center">
                                        <select name="order_radar_dettagli" class="form-control form-control-sm mr-2" style="width: 60px;">
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4" selected>4</option>
                                            <option value="5">5</option>
                                            <option value="6">6</option>
                                            <option value="7">7</option>
                                            <option value="8">8</option>
                                            <option value="9">9</option>
                                        </select>
                                        <label class="mb-0">🔍 Radar Dettaglio Aree</label>
                                    </div>
                                    <div class="form-group mb-2 d-flex align-items-center">
                                        <select name="order_piano" class="form-control form-control-sm mr-2" style="width: 60px;">
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5" selected>5</option>
                                            <option value="6">6</option>
                                            <option value="7">7</option>
                                            <option value="8">8</option>
                                            <option value="9">9</option>
                                        </select>
                                        <label class="mb-0">📚 Piano d'Azione</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-2 d-flex align-items-center">
                                        <select name="order_dettagli" class="form-control form-control-sm mr-2" style="width: 60px;">
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                            <option value="6" selected>6</option>
                                            <option value="7">7</option>
                                            <option value="8">8</option>
                                            <option value="9">9</option>
                                        </select>
                                        <label class="mb-0">📋 Tabella Dettagli</label>
                                    </div>
                                    <?php if ($hasAutovalutazione): ?>
                                    <div class="form-group mb-2 d-flex align-items-center">
                                        <select name="order_dual_radar" class="form-control form-control-sm mr-2" style="width: 60px;">
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                            <option value="6">6</option>
                                            <option value="7" selected>7</option>
                                            <option value="8">8</option>
                                            <option value="9">9</option>
                                        </select>
                                        <label class="mb-0">🎯 Doppio Radar</label>
                                    </div>
                                    <div class="form-group mb-2 d-flex align-items-center">
                                        <select name="order_gap" class="form-control form-control-sm mr-2" style="width: 60px;">
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                            <option value="6">6</option>
                                            <option value="7">7</option>
                                            <option value="8" selected>8</option>
                                            <option value="9">9</option>
                                        </select>
                                        <label class="mb-0">📈 Gap Analysis</label>
                                    </div>
                                    <div class="form-group mb-2 d-flex align-items-center">
                                        <select name="order_spunti" class="form-control form-control-sm mr-2" style="width: 60px;">
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                            <option value="6">6</option>
                                            <option value="7">7</option>
                                            <option value="8">8</option>
                                            <option value="9" selected>9</option>
                                            <option value="10">10</option>
                                        </select>
                                        <label class="mb-0">💬 Spunti Colloquio</label>
                                    </div>
                                    <div class="form-group mb-2 d-flex align-items-center">
                                        <select name="order_suggerimenti" class="form-control form-control-sm mr-2" style="width: 60px;">
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                            <option value="6">6</option>
                                            <option value="7">7</option>
                                            <option value="8">8</option>
                                            <option value="9">9</option>
                                            <option value="10" selected>10</option>
                                        </select>
                                        <label class="mb-0">📋 Suggerimenti Rapporto</label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mt-2 p-2" style="background: #fff3cd; border-radius: 4px;">
                                <small class="text-warning"><strong>💡 Suggerimento:</strong> Per stampare prima i grafici di confronto e poi il doppio radar, imposta Doppio Radar = 2 e Gap Analysis = 3</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('printForm').submit()">🖨️ Genera Stampa</button>
            </div>
        </div>
    </div>
</div>

<?php
// Tabs interni + link esterni (stesso stile)
$tabs = [
    'overview' => '📊 Panoramica',
    'action' => '📚 Piano',
    'quiz' => '📝 Quiz',
    'selfassessment' => '📝 Autovalutazione ↗',
    'labeval' => '🔬 Laboratorio ↗',
    'progress' => '📈 Progressi',
    'details' => '📋 Dettagli'
];

// URL esterni
$externalUrls = [
    'selfassessment' => new moodle_url('/local/selfassessment/student_report.php', ['userid' => $userid, 'courseid' => $courseid]),
    'labeval' => new moodle_url('/local/labeval/reports.php', ['studentid' => $userid])
];

echo '<ul class="nav nav-tabs mb-4">';
foreach ($tabs as $tabKey => $tabLabel) {
    // Link esterni
    if (isset($externalUrls[$tabKey])) {
        echo '<li class="nav-item"><a class="nav-link" href="' . $externalUrls[$tabKey] . '" target="_blank">' . $tabLabel . '</a></li>';
        continue;
    }

    // Tab interni
    $activeClass = ($tab === $tabKey) ? 'active' : '';
    $tabUrl = new moodle_url('/local/competencymanager/student_report.php', ['userid' => $userid, 'courseid' => $courseid, 'tab' => $tabKey]);
    if (!empty($selectedQuizzes)) {
        foreach ($selectedQuizzes as $qid) {
            $tabUrl->param('quizids[]', $qid);
        }
    }
    // Mantieni opzioni additive
    if ($showDualRadar) $tabUrl->param('show_dual_radar', 1);
    if ($showGapAnalysis) $tabUrl->param('show_gap', 1);
    if ($showSpuntiColloquio) $tabUrl->param('show_spunti', 1);
    echo '<li class="nav-item"><a class="nav-link ' . $activeClass . '" href="' . $tabUrl . '">' . $tabLabel . '</a></li>';
}
echo '</ul>';

// Contenuto tabs
echo '<div class="tab-content">';

// ========================================
// TAB PANORAMICA
// ========================================
if ($tab === 'overview') {
    ?>
    <div class="row">
        <div class="col-lg-7">
            <div class="card" id="radarCard">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0" id="radarTitle">📊 Radar Aree di Competenza</h5>
                        <small id="radarSubtitle">👆 Clicca su un'area nel radar o nel dropdown per vedere le competenze</small>
                    </div>
                    <button type="button" class="btn btn-light btn-sm" id="btnBackToAreas" style="display: none;" onclick="backToAreasView()">
                        ↩ Torna alle Aree
                    </button>
                </div>
                <div class="card-body">
                    <!-- Controlli interattivi - Vista Aree -->
                    <div class="radar-controls" id="areaToggleControls">
                        <strong>Aree visualizzate:</strong>
                        <div class="mt-2" id="areaToggles">
                            <?php foreach ($areasData as $code => $areaData): ?>
                            <span class="area-toggle" data-area="<?php echo $code; ?>"
                                  style="background: <?php echo $areaData['color']; ?>20; border: 2px solid <?php echo $areaData['color']; ?>; color: <?php echo $areaData['color']; ?>; cursor: pointer;"
                                  onclick="drillDownToArea('<?php echo $code; ?>')" title="Clicca per vedere le competenze">
                                <?php echo $areaData['icon'] . ' ' . $areaData['name']; ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="toggleAllAreas(true)">Mostra tutte</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllAreas(false)">Nascondi tutte</button>
                        </div>
                    </div>

                    <!-- Info Drill-Down - Vista Competenze -->
                    <div class="drill-down-info" id="drillDownInfo" style="display: none;">
                        <div class="alert alert-info py-2 mb-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <strong id="drillAreaName">Area: </strong>
                                    <span class="badge badge-primary ml-2" id="drillCompCount">0 competenze</span>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="backToAreasView()">
                                    ↩ Torna alle Aree
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Canvas per Chart.js -->
                    <canvas id="radarAreas" style="max-height: 400px;"></canvas>

                    <!-- Legenda competenze (drill-down) -->
                    <div id="competencyLegend" style="display: none; margin-top: 15px;">
                        <h6>📋 Competenze dell'area:</h6>
                        <div id="competencyList" style="max-height: 350px; overflow-y: auto;"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">📈 Riepilogo Aree</h5>
                </div>
                <div class="card-body">
                    <p><strong>Aree totali:</strong> <?php echo count($areasData); ?></p>
                    <p><strong>Competenze valutate:</strong> <?php echo count($competencies); ?></p>
                    <p><strong>Domande totali:</strong> <?php echo $summary['questions_total'] ?? $summary['total_questions']; ?></p>
                    
                    <hr>
                    
                    <h6>🏆 Aree Forti (≥60%)</h6>
                    <ul class="list-unstyled">
                    <?php foreach ($areasData as $code => $areaData): ?>
                        <?php if ($areaData['percentage'] >= 60): ?>
                        <li><span style="color: <?php echo $areaData['color']; ?>;"><?php echo $areaData['icon']; ?></span> <?php echo $areaData['name']; ?> - <strong class="text-success"><?php echo $areaData['percentage']; ?>%</strong></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </ul>
                    
                    <h6 class="mt-3">⚠️ Da Migliorare (<50%)</h6>
                    <ul class="list-unstyled">
                    <?php foreach ($areasData as $code => $areaData): ?>
                        <?php if ($areaData['percentage'] < 50): ?>
                        <li><span style="color: <?php echo $areaData['color']; ?>;"><?php echo $areaData['icon']; ?></span> <?php echo $areaData['name']; ?> - <strong class="text-danger"><?php echo $areaData['percentage']; ?>%</strong></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Mini radar per area selezionata -->
            <div class="card mt-3">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">🔍 Dettaglio Area</h5>
                </div>
                <div class="card-body">
                    <select id="areaDetailSelect" class="form-control mb-3">
                        <option value="">-- Seleziona un'area --</option>
                        <?php foreach ($areasData as $code => $areaData): ?>
                        <option value="<?php echo $code; ?>"><?php echo $areaData['icon'] . ' ' . $areaData['name'] . ' (' . $areaData['percentage'] . '%)'; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="areaDetailContent">
                        <p class="text-muted text-center">Seleziona un'area per vedere il dettaglio delle competenze</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    // ============================================
    // SEZIONI ADDITIVE (dopo il radar principale)
    // ============================================
    
    // DOPPIO RADAR AFFIANCATO
    if ($showDualRadar && !empty($autovalutazioneAreas)):
    ?>
    <div class="card mt-4">
        <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h5 class="mb-0">🎯 Confronto Radar: Autovalutazione vs Performance</h5>
            <small class="d-block mt-1 opacity-75">Le competenze sono nella stessa posizione in entrambi i grafici per facilitare il confronto</small>
        </div>
        <div class="card-body">
            <!-- Legenda in alto -->
            <div class="text-center mb-4">
                <span class="badge mr-3" style="background: #667eea; color: white; padding: 10px 20px; font-size: 14px;">🧑 Autovalutazione</span>
                <span class="badge" style="background: #28a745; color: white; padding: 10px 20px; font-size: 14px;">📊 Performance Reale</span>
            </div>

            <!-- Radar Autovalutazione - SOPRA -->
            <div class="radar-comparison-item mb-4">
                <div class="text-center mb-2">
                    <h6 style="color: #667eea; font-weight: 600; font-size: 16px;">
                        <span style="display: inline-block; width: 12px; height: 12px; background: #667eea; border-radius: 50%; margin-right: 8px;"></span>
                        🧑 Come lo studente si percepisce
                    </h6>
                </div>
                <div class="radar-canvas-container" style="max-width: 850px; margin: 0 auto;">
                    <canvas id="radarAutovalutazione" style="height: 550px; max-height: 600px;"></canvas>
                </div>
            </div>

            <!-- Separatore visivo -->
            <div class="text-center my-4">
                <hr style="border-top: 2px dashed #dee2e6; max-width: 400px; margin: 0 auto;">
                <span style="background: white; padding: 0 15px; position: relative; top: -12px; color: #6c757d; font-size: 12px;">VS</span>
            </div>

            <!-- Radar Performance - SOTTO -->
            <div class="radar-comparison-item">
                <div class="text-center mb-2">
                    <h6 style="color: #28a745; font-weight: 600; font-size: 16px;">
                        <span style="display: inline-block; width: 12px; height: 12px; background: #28a745; border-radius: 50%; margin-right: 8px;"></span>
                        📊 Risultati reali dai quiz
                    </h6>
                </div>
                <div class="radar-canvas-container" style="max-width: 850px; margin: 0 auto;">
                    <canvas id="radarPerformanceDual" style="height: 550px; max-height: 600px;"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // ============================================
    // CONFRONTO 4 FONTI (spostato qui - subito dopo Radar)
    // ============================================
    if ($showCoachEvaluation && !empty($coachEvaluationData)):
        $coachEvalRatings = coach_evaluation_manager::get_evaluation_ratings($coachEvaluationData->id);
        $coachEvalByArea = coach_evaluation_manager::get_ratings_by_area($coachEvaluationData->id);
        $coachEvalStats = coach_evaluation_manager::get_rating_stats($coachEvaluationData->id);
        $coachEvalAvg = coach_evaluation_manager::calculate_average($coachEvaluationData->id);
        $bloomScale = coach_evaluation_manager::get_bloom_scale();
        $coachRadarData = coach_evaluation_manager::get_radar_data($userid, $currentSector);
    ?>
    <div class="card mt-4">
        <div class="card-header" style="background: linear-gradient(135deg, #5f2c82 0%, #49a09d 100%); color: white;">
            <h5 class="mb-0">📊 Confronto 4 Fonti: Quiz, Autovalutazione, LabEval, Formatore</h5>
            <small class="d-block mt-1 opacity-75">Visualizzazione comparativa di tutte le valutazioni disponibili</small>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr style="background: #34495e; color: white;">
                            <th>Area</th>
                            <th class="text-center" style="width: 100px; background: #28a745;">📊 Quiz<br><small>%</small></th>
                            <th class="text-center" style="width: 100px; background: #667eea;">🧑 Auto<br><small>Bloom</small></th>
                            <th class="text-center" style="width: 100px; background: #fd7e14;">🔧 LabEval<br><small>%</small></th>
                            <th class="text-center" style="width: 100px; background: #11998e;">👨‍🏫 Coach<br><small>Bloom</small></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $allAreas4Fonti = [];
                        if (!empty($areasData)) {
                            foreach ($areasData as $areaData) {
                                $code = $areaData['code'];
                                if (!isset($allAreas4Fonti[$code])) {
                                    $allAreas4Fonti[$code] = ['name' => $areaData['name'], 'quiz' => null, 'auto' => null, 'labeval' => null, 'coach' => null];
                                }
                                $allAreas4Fonti[$code]['quiz'] = round($areaData['percentage']);
                            }
                        }
                        if (!empty($autovalutazioneAreas)) {
                            foreach ($autovalutazioneAreas as $areaData) {
                                $code = $areaData['code'];
                                if (!isset($allAreas4Fonti[$code])) {
                                    $allAreas4Fonti[$code] = ['name' => $areaData['name'], 'quiz' => null, 'auto' => null, 'labeval' => null, 'coach' => null];
                                }
                                $allAreas4Fonti[$code]['auto'] = round(($areaData['percentage'] / 100) * 6, 1);
                            }
                        }
                        if (!empty($labEvalByArea)) {
                            foreach ($labEvalByArea as $areaCode => $areaData) {
                                if (!isset($allAreas4Fonti[$areaCode])) {
                                    $allAreas4Fonti[$areaCode] = ['name' => $areaData['name'] ?? "Area $areaCode", 'quiz' => null, 'auto' => null, 'labeval' => null, 'coach' => null];
                                }
                                $allAreas4Fonti[$areaCode]['labeval'] = round($areaData['percentage']);
                            }
                        }
                        if (!empty($coachRadarData)) {
                            foreach ($coachRadarData as $areaData) {
                                $code = $areaData['area'];
                                if (!isset($allAreas4Fonti[$code])) {
                                    $allAreas4Fonti[$code] = ['name' => "Area $code", 'quiz' => null, 'auto' => null, 'labeval' => null, 'coach' => null];
                                }
                                $allAreas4Fonti[$code]['coach'] = $areaData['bloom_avg'];
                            }
                        }
                        ksort($allAreas4Fonti);
                        foreach ($allAreas4Fonti as $code => $data):
                        ?>
                        <tr>
                            <td><strong><?php echo s($data['name']); ?></strong> <small class="text-muted">(<?php echo $code; ?>)</small></td>
                            <td class="text-center">
                                <?php if ($data['quiz'] !== null): ?>
                                    <span class="badge badge-<?php echo $data['quiz'] >= 60 ? 'success' : ($data['quiz'] >= 40 ? 'warning' : 'danger'); ?>">
                                        <?php echo $data['quiz']; ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($data['auto'] !== null): ?>
                                    <span class="badge badge-primary"><?php echo $data['auto']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($data['labeval'] !== null): ?>
                                    <span class="badge badge-warning"><?php echo $data['labeval']; ?>%</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($data['coach'] !== null): ?>
                                    <span class="badge badge-info"><?php echo $data['coach']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-center mt-3">
                <small class="text-muted">
                    📊 Quiz: Percentuale risposte corrette |
                    🧑 Auto: Scala Bloom (1-6) |
                    🔧 LabEval: Percentuale valutazione pratica |
                    👨‍🏫 Coach: Scala Bloom (1-6)
                </small>
            </div>

            <!-- ============================================ -->
            <!-- SBLOCCO SEZIONE METODI (con codice) -->
            <!-- ============================================ -->
            <hr class="my-4">
            <div class="card mb-3" style="border: 1px dashed #6c757d;">
                <div class="card-body py-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-muted">
                            🔒 <strong>Sezione Avanzata</strong> - Inserisci codice per visualizzare i Metodi di Valutazione Finale
                        </span>
                        <div class="d-flex align-items-center" style="gap: 8px;">
                            <input type="password" id="metodiUnlockCode" placeholder="Codice"
                                   style="width: 100px; padding: 4px 8px; border: 1px solid #ced4da; border-radius: 4px; font-size: 0.9rem;">
                            <button type="button" onclick="unlockMetodiSection()"
                                    class="btn btn-sm btn-outline-secondary">
                                🔓 Sblocca
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- SEZIONE: METODI DI VALUTAZIONE FINALE -->
            <!-- (Nascosta di default - sbloccabile con codice) -->
            <!-- ============================================ -->
            <div id="sezioneMetodiValutazione" style="display: none;">
            <div class="mt-4">
                <h5 class="mb-3" style="color: #5f2c82;">
                    🎯 Metodi di Valutazione Finale <span class="badge badge-success" style="font-size: 0.6em;">Sbloccato</span>
                    <small class="text-muted d-block mt-1" style="font-size: 0.7em;">
                        Confronto tra diversi metodi di calcolo per determinare la valutazione finale dello studente
                    </small>
                </h5>

                <!-- Spiegazione per il responsabile -->
                <div class="alert alert-info mb-4">
                    <h6 class="alert-heading">📋 Guida alla scelta del metodo di valutazione</h6>
                    <p class="mb-2">Questa sezione presenta <strong>4 metodi diversi</strong> per calcolare la valutazione finale delle competenze dello studente. Ogni metodo ha pro e contro:</p>
                    <ul class="mb-0 small">
                        <li><strong>Media Completa (4 Fonti):</strong> Considera Quiz, Autovalutazione, LabEval e Coach con peso uguale. <em>Pro: visione completa. Contro: l'autovalutazione può essere soggettiva.</em></li>
                        <li><strong>Media Oggettiva (3 Fonti):</strong> Esclude l'autovalutazione, usa solo Quiz, LabEval e Coach. <em>Pro: più oggettiva. Contro: non considera la percezione dello studente.</em></li>
                        <li><strong>Media Pratica (2 Fonti):</strong> Solo Quiz (teoria) + Coach (giudizio esperto). <em>Pro: semplice e diretta. Contro: esclude la pratica (LabEval).</em></li>
                        <li><strong>Valutazione Coach:</strong> Il formatore ha l'ultima parola. <em>Pro: giudizio esperto. Contro: soggettivo a un singolo valutatore.</em></li>
                    </ul>
                </div>

                <?php
                // Calcola le 4 formule per ogni area
                // Normalizza tutto a percentuale (0-100)
                $metodiValutazione = [];
                foreach ($allAreas4Fonti as $code => $data) {
                    // Normalizza i valori Bloom a percentuale (1-6 → 0-100)
                    $quizPct = $data['quiz']; // già in %
                    $autoPct = $data['auto'] !== null ? round(($data['auto'] / 6) * 100) : null;
                    $labPct = $data['labeval']; // già in %
                    $coachPct = $data['coach'] !== null ? round(($data['coach'] / 6) * 100) : null;

                    // Formula 1: Media Completa (4 Fonti)
                    $valori1 = array_filter([$quizPct, $autoPct, $labPct, $coachPct], fn($v) => $v !== null);
                    $media4 = count($valori1) > 0 ? round(array_sum($valori1) / count($valori1)) : null;

                    // Formula 2: Media Oggettiva (3 Fonti - senza Auto)
                    $valori2 = array_filter([$quizPct, $labPct, $coachPct], fn($v) => $v !== null);
                    $media3 = count($valori2) > 0 ? round(array_sum($valori2) / count($valori2)) : null;

                    // Formula 3: Media Pratica (Quiz + Coach)
                    $valori3 = array_filter([$quizPct, $coachPct], fn($v) => $v !== null);
                    $media2 = count($valori3) > 0 ? round(array_sum($valori3) / count($valori3)) : null;

                    // Formula 4: Solo Coach
                    $soloCoach = $coachPct;

                    $metodiValutazione[$code] = [
                        'name' => $data['name'],
                        'quiz' => $quizPct,
                        'auto' => $autoPct,
                        'lab' => $labPct,
                        'coach' => $coachPct,
                        'media4' => $media4,
                        'media3' => $media3,
                        'media2' => $media2,
                        'soloCoach' => $soloCoach,
                        // Valori per editing manuale
                        'media4_calc' => $media4,
                        'media3_calc' => $media3,
                        'media2_calc' => $media2,
                        'soloCoach_calc' => $soloCoach,
                        'media4_modified' => false,
                        'media3_modified' => false,
                        'media2_modified' => false,
                        'soloCoach_modified' => false
                    ];
                }

                // Carica eventuali valori manuali dal database
                $manualRatings = $DB->get_records('local_compman_final_ratings', [
                    'studentid' => $userid,
                    'courseid' => $courseid,
                    'sector' => $currentSector
                ]);

                // Mappa metodi DB → chiavi array
                $methodMap = [
                    'media4' => 'media4',
                    'media3' => 'media3',
                    'media2' => 'media2',
                    'soloCoach' => 'soloCoach'
                ];

                foreach ($manualRatings as $mr) {
                    $areaCode = $mr->area_code;
                    $method = $mr->method;
                    if (isset($metodiValutazione[$areaCode]) && isset($methodMap[$method])) {
                        $key = $methodMap[$method];
                        $metodiValutazione[$areaCode][$key] = (int)$mr->manual_value;
                        $metodiValutazione[$areaCode][$key . '_modified'] = true;
                    }
                }

                // Verifica se l'utente può modificare
                $canEditFinalRatings = has_capability('local/competencymanager:evaluate', $context);
                ?>

                <!-- Tabella Metodi di Valutazione -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-hover table-sm" id="tabellaMetodiValutazione">
                        <thead>
                            <tr style="background: linear-gradient(135deg, #5f2c82 0%, #49a09d 100%); color: white;">
                                <th style="width: 40px;" class="text-center">
                                    <input type="checkbox" id="selectAllAreas" checked title="Seleziona/Deseleziona tutte">
                                </th>
                                <th>Area di Competenza</th>
                                <th class="text-center" style="width: 110px; background: rgba(255,255,255,0.1);">
                                    📊 Media Completa<br><small>(4 Fonti)</small>
                                </th>
                                <th class="text-center" style="width: 110px; background: rgba(255,255,255,0.1);">
                                    🎯 Media Oggettiva<br><small>(3 Fonti)</small>
                                </th>
                                <th class="text-center" style="width: 110px; background: rgba(255,255,255,0.1);">
                                    📝 Media Pratica<br><small>(Quiz+Coach)</small>
                                </th>
                                <th class="text-center" style="width: 110px; background: rgba(255,255,255,0.1);">
                                    👨‍🏫 Solo Coach<br><small>(Formatore)</small>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($metodiValutazione as $code => $mv): ?>
                            <tr data-area="<?php echo $code; ?>">
                                <td class="text-center">
                                    <input type="checkbox" class="area-checkbox" value="<?php echo $code; ?>" checked>
                                </td>
                                <td>
                                    <strong><?php echo s($mv['name']); ?></strong>
                                    <small class="text-muted d-block">(<?php echo $code; ?>)</small>
                                </td>
                                <!-- Media Completa (4 Fonti) -->
                                <td class="text-center">
                                    <?php if ($mv['media4'] !== null): ?>
                                        <?php if ($canEditFinalRatings): ?>
                                        <span class="badge badge-<?php echo $mv['media4'] >= 60 ? 'success' : ($mv['media4'] >= 40 ? 'warning' : 'danger'); ?> final-rating-editable"
                                              style="font-size: 1rem; cursor: pointer;"
                                              data-area="<?php echo $code; ?>"
                                              data-method="media4"
                                              data-value="<?php echo $mv['media4']; ?>"
                                              data-calculated="<?php echo $mv['media4_calc']; ?>"
                                              data-modified="<?php echo $mv['media4_modified'] ? '1' : '0'; ?>"
                                              onclick="showFinalRatingDropdown(this)"
                                              title="Clicca per modificare">
                                            <?php echo $mv['media4']; ?>%
                                            <?php if ($mv['media4_modified']): ?><span style="font-size: 0.7em;">✏️</span><?php endif; ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="badge badge-<?php echo $mv['media4'] >= 60 ? 'success' : ($mv['media4'] >= 40 ? 'warning' : 'danger'); ?>" style="font-size: 1rem;">
                                            <?php echo $mv['media4']; ?>%
                                        </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <!-- Media Oggettiva (3 Fonti) -->
                                <td class="text-center">
                                    <?php if ($mv['media3'] !== null): ?>
                                        <?php if ($canEditFinalRatings): ?>
                                        <span class="badge badge-<?php echo $mv['media3'] >= 60 ? 'success' : ($mv['media3'] >= 40 ? 'warning' : 'danger'); ?> final-rating-editable"
                                              style="font-size: 1rem; cursor: pointer;"
                                              data-area="<?php echo $code; ?>"
                                              data-method="media3"
                                              data-value="<?php echo $mv['media3']; ?>"
                                              data-calculated="<?php echo $mv['media3_calc']; ?>"
                                              data-modified="<?php echo $mv['media3_modified'] ? '1' : '0'; ?>"
                                              onclick="showFinalRatingDropdown(this)"
                                              title="Clicca per modificare">
                                            <?php echo $mv['media3']; ?>%
                                            <?php if ($mv['media3_modified']): ?><span style="font-size: 0.7em;">✏️</span><?php endif; ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="badge badge-<?php echo $mv['media3'] >= 60 ? 'success' : ($mv['media3'] >= 40 ? 'warning' : 'danger'); ?>" style="font-size: 1rem;">
                                            <?php echo $mv['media3']; ?>%
                                        </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <!-- Media Pratica (Quiz+Coach) -->
                                <td class="text-center">
                                    <?php if ($mv['media2'] !== null): ?>
                                        <?php if ($canEditFinalRatings): ?>
                                        <span class="badge badge-<?php echo $mv['media2'] >= 60 ? 'success' : ($mv['media2'] >= 40 ? 'warning' : 'danger'); ?> final-rating-editable"
                                              style="font-size: 1rem; cursor: pointer;"
                                              data-area="<?php echo $code; ?>"
                                              data-method="media2"
                                              data-value="<?php echo $mv['media2']; ?>"
                                              data-calculated="<?php echo $mv['media2_calc']; ?>"
                                              data-modified="<?php echo $mv['media2_modified'] ? '1' : '0'; ?>"
                                              onclick="showFinalRatingDropdown(this)"
                                              title="Clicca per modificare">
                                            <?php echo $mv['media2']; ?>%
                                            <?php if ($mv['media2_modified']): ?><span style="font-size: 0.7em;">✏️</span><?php endif; ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="badge badge-<?php echo $mv['media2'] >= 60 ? 'success' : ($mv['media2'] >= 40 ? 'warning' : 'danger'); ?>" style="font-size: 1rem;">
                                            <?php echo $mv['media2']; ?>%
                                        </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <!-- Solo Coach -->
                                <td class="text-center">
                                    <?php if ($mv['soloCoach'] !== null): ?>
                                        <?php if ($canEditFinalRatings): ?>
                                        <span class="badge badge-info final-rating-editable"
                                              style="font-size: 1rem; cursor: pointer;"
                                              data-area="<?php echo $code; ?>"
                                              data-method="soloCoach"
                                              data-value="<?php echo $mv['soloCoach']; ?>"
                                              data-calculated="<?php echo $mv['soloCoach_calc']; ?>"
                                              data-modified="<?php echo $mv['soloCoach_modified'] ? '1' : '0'; ?>"
                                              onclick="showFinalRatingDropdown(this)"
                                              title="Clicca per modificare">
                                            <?php echo $mv['soloCoach']; ?>%
                                            <?php if ($mv['soloCoach_modified']): ?><span style="font-size: 0.7em;">✏️</span><?php endif; ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="badge badge-info" style="font-size: 1rem;">
                                            <?php echo $mv['soloCoach']; ?>%
                                        </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot style="background: #f8f9fa; font-weight: bold;">
                            <tr>
                                <td></td>
                                <td>📈 Media Globale</td>
                                <?php
                                $totMedia4 = array_filter(array_column($metodiValutazione, 'media4'), fn($v) => $v !== null);
                                $totMedia3 = array_filter(array_column($metodiValutazione, 'media3'), fn($v) => $v !== null);
                                $totMedia2 = array_filter(array_column($metodiValutazione, 'media2'), fn($v) => $v !== null);
                                $totCoach = array_filter(array_column($metodiValutazione, 'soloCoach'), fn($v) => $v !== null);
                                ?>
                                <td class="text-center"><?php echo count($totMedia4) > 0 ? round(array_sum($totMedia4) / count($totMedia4)) . '%' : '-'; ?></td>
                                <td class="text-center"><?php echo count($totMedia3) > 0 ? round(array_sum($totMedia3) / count($totMedia3)) . '%' : '-'; ?></td>
                                <td class="text-center"><?php echo count($totMedia2) > 0 ? round(array_sum($totMedia2) / count($totMedia2)) . '%' : '-'; ?></td>
                                <td class="text-center"><?php echo count($totCoach) > 0 ? round(array_sum($totCoach) / count($totCoach)) . '%' : '-'; ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Grafico Radar Comparativo -->
                <div class="card">
                    <div class="card-header" style="background: #34495e; color: white;">
                        <h6 class="mb-0">📈 Grafico Radar: Confronto Metodi di Valutazione</h6>
                        <small>Seleziona le aree nella tabella sopra per includerle nel grafico</small>
                    </div>
                    <div class="card-body">
                        <div class="text-center">
                            <canvas id="radarMetodiValutazione" style="max-height: 500px;"></canvas>
                        </div>
                        <div class="mt-3">
                            <p class="small text-muted mb-1"><strong>Legenda metodi:</strong></p>
                            <div class="d-flex flex-wrap justify-content-center" style="gap: 15px;">
                                <span class="badge" style="background: #9b59b6; color: white; padding: 8px 12px;">📊 Media Completa (4 Fonti)</span>
                                <span class="badge" style="background: #3498db; color: white; padding: 8px 12px;">🎯 Media Oggettiva (3 Fonti)</span>
                                <span class="badge" style="background: #e67e22; color: white; padding: 8px 12px;">📝 Media Pratica (Quiz+Coach)</span>
                                <span class="badge" style="background: #1abc9c; color: white; padding: 8px 12px;">👨‍🏫 Solo Coach</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Script per il grafico -->
                <script>
                (function() {
                    // Dati iniziali
                    const metodiData = <?php echo json_encode($metodiValutazione); ?>;
                    let radarChart = null;

                    function updateRadarChart() {
                        // Ottieni aree selezionate
                        const selectedAreas = [];
                        document.querySelectorAll('.area-checkbox:checked').forEach(cb => {
                            selectedAreas.push(cb.value);
                        });

                        if (selectedAreas.length === 0) {
                            if (radarChart) {
                                radarChart.destroy();
                                radarChart = null;
                            }
                            return;
                        }

                        // Prepara dati per il grafico
                        const labels = [];
                        const dataMedia4 = [];
                        const dataMedia3 = [];
                        const dataMedia2 = [];
                        const dataCoach = [];

                        selectedAreas.forEach(code => {
                            if (metodiData[code]) {
                                labels.push(metodiData[code].name || code);
                                dataMedia4.push(metodiData[code].media4);
                                dataMedia3.push(metodiData[code].media3);
                                dataMedia2.push(metodiData[code].media2);
                                dataCoach.push(metodiData[code].soloCoach);
                            }
                        });

                        const ctx = document.getElementById('radarMetodiValutazione');
                        if (!ctx) return;

                        if (radarChart) {
                            radarChart.destroy();
                        }

                        radarChart = new Chart(ctx, {
                            type: 'radar',
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        label: '📊 Media Completa (4 Fonti)',
                                        data: dataMedia4,
                                        backgroundColor: 'rgba(155, 89, 182, 0.2)',
                                        borderColor: 'rgba(155, 89, 182, 1)',
                                        borderWidth: 2,
                                        pointBackgroundColor: 'rgba(155, 89, 182, 1)',
                                        pointRadius: 4
                                    },
                                    {
                                        label: '🎯 Media Oggettiva (3 Fonti)',
                                        data: dataMedia3,
                                        backgroundColor: 'rgba(52, 152, 219, 0.2)',
                                        borderColor: 'rgba(52, 152, 219, 1)',
                                        borderWidth: 2,
                                        pointBackgroundColor: 'rgba(52, 152, 219, 1)',
                                        pointRadius: 4
                                    },
                                    {
                                        label: '📝 Media Pratica (Quiz+Coach)',
                                        data: dataMedia2,
                                        backgroundColor: 'rgba(230, 126, 34, 0.2)',
                                        borderColor: 'rgba(230, 126, 34, 1)',
                                        borderWidth: 2,
                                        pointBackgroundColor: 'rgba(230, 126, 34, 1)',
                                        pointRadius: 4
                                    },
                                    {
                                        label: '👨‍🏫 Solo Coach',
                                        data: dataCoach,
                                        backgroundColor: 'rgba(26, 188, 156, 0.2)',
                                        borderColor: 'rgba(26, 188, 156, 1)',
                                        borderWidth: 2,
                                        pointBackgroundColor: 'rgba(26, 188, 156, 1)',
                                        pointRadius: 4
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: true,
                                scales: {
                                    r: {
                                        beginAtZero: true,
                                        max: 100,
                                        ticks: {
                                            stepSize: 20,
                                            callback: function(value) { return value + '%'; }
                                        },
                                        pointLabels: {
                                            font: { size: 11 }
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: { font: { size: 11 }, boxWidth: 15 }
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                let value = context.parsed.r;
                                                return context.dataset.label + ': ' + (value !== null ? value + '%' : 'N/D');
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }

                    // Event listeners per checkbox
                    document.querySelectorAll('.area-checkbox').forEach(cb => {
                        cb.addEventListener('change', updateRadarChart);
                    });

                    // Select All checkbox
                    document.getElementById('selectAllAreas')?.addEventListener('change', function() {
                        document.querySelectorAll('.area-checkbox').forEach(cb => {
                            cb.checked = this.checked;
                        });
                        updateRadarChart();
                    });

                    // Inizializza il grafico quando Chart.js è disponibile
                    if (typeof Chart !== 'undefined') {
                        updateRadarChart();
                    } else {
                        window.addEventListener('load', updateRadarChart);
                    }
                })();
                </script>

            </div>
            <!-- Fine Sezione Metodi di Valutazione Finale -->
            </div><!-- Fine sezioneMetodiValutazione -->

            <script>
            function unlockMetodiSection() {
                const code = document.getElementById('metodiUnlockCode').value;
                if (code === '6807') {
                    document.getElementById('sezioneMetodiValutazione').style.display = 'block';
                    document.getElementById('metodiUnlockCode').parentElement.parentElement.parentElement.parentElement.style.display = 'none';
                    showToast('✅ Sezione Metodi sbloccata');
                } else {
                    alert('Codice non valido');
                    document.getElementById('metodiUnlockCode').value = '';
                }
            }

            // Sblocca anche premendo Enter nell'input
            document.getElementById('metodiUnlockCode')?.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    unlockMetodiSection();
                }
            });
            </script>

        </div>
    </div>
    <?php endif; // Fine Confronto 4 Fonti ?>

    <?php
    // ============================================
    // GRAFICO RADAR SOVRAPPOSIZIONE (Overlay)
    // ============================================
    if ($showOverlayRadar || $printOverlayRadar):
        // Prepara dati normalizzati a percentuale per tutte le fonti
        $overlayAreas = [];

        // 1. Quiz (già in percentuale)
        if (!empty($areasData)) {
            foreach ($areasData as $areaData) {
                $code = $areaData['code'];
                if (!isset($overlayAreas[$code])) {
                    $overlayAreas[$code] = [
                        'code' => $code,
                        'name' => $areaData['name'],
                        'quiz' => null,
                        'auto' => null,
                        'labeval' => null,
                        'coach' => null
                    ];
                }
                $overlayAreas[$code]['quiz'] = round($areaData['percentage'], 1);
            }
        }

        // 2. Autovalutazione (Bloom 1-6 → percentuale: valore/6*100)
        if (!empty($autovalutazioneAreas)) {
            foreach ($autovalutazioneAreas as $areaData) {
                $code = $areaData['code'];
                if (!isset($overlayAreas[$code])) {
                    $overlayAreas[$code] = [
                        'code' => $code,
                        'name' => $areaData['name'],
                        'quiz' => null,
                        'auto' => null,
                        'labeval' => null,
                        'coach' => null
                    ];
                }
                // Autovalutazione è già in percentuale (1-6 scala, dove percentage è 0-100)
                $overlayAreas[$code]['auto'] = round($areaData['percentage'], 1);
            }
        }

        // 3. LabEval (già in percentuale)
        if (!empty($labEvalByArea)) {
            foreach ($labEvalByArea as $areaCode => $areaData) {
                if (!isset($overlayAreas[$areaCode])) {
                    $overlayAreas[$areaCode] = [
                        'code' => $areaCode,
                        'name' => $areaData['name'],
                        'quiz' => null,
                        'auto' => null,
                        'labeval' => null,
                        'coach' => null
                    ];
                }
                $overlayAreas[$areaCode]['labeval'] = round($areaData['percentage'], 1);
            }
        }

        // 4. Coach Evaluation (Bloom 0-6 → percentuale: valore/6*100, 0=0%)
        if (!empty($coachRadarData)) {
            foreach ($coachRadarData as $areaData) {
                $code = $areaData['area'];
                if (!isset($overlayAreas[$code])) {
                    $overlayAreas[$code] = [
                        'code' => $code,
                        'name' => "Area $code",
                        'quiz' => null,
                        'auto' => null,
                        'labeval' => null,
                        'coach' => null
                    ];
                }
                // Bloom 0-6 → percentuale (0=0%, 6=100%)
                $bloomValue = $areaData['bloom_avg'] ?? 0;
                $overlayAreas[$code]['coach'] = round(($bloomValue / 6) * 100, 1);
            }
        }

        ksort($overlayAreas);

        // Prepara dati per Chart.js
        $overlayLabels = [];
        $overlayQuiz = [];
        $overlayAuto = [];
        $overlayLabeval = [];
        $overlayCoach = [];

        foreach ($overlayAreas as $code => $data) {
            $overlayLabels[] = $data['name'] ?: "Area $code";
            $overlayQuiz[] = $data['quiz'];
            $overlayAuto[] = $data['auto'];
            $overlayLabeval[] = $data['labeval'];
            $overlayCoach[] = $data['coach'];
        }

        // Verifica se ci sono dati da visualizzare
        $hasOverlayData = !empty($overlayAreas);
        $sourceCount = 0;
        if (!empty($areasData)) $sourceCount++;
        if (!empty($autovalutazioneAreas)) $sourceCount++;
        if (!empty($labEvalByArea)) $sourceCount++;
        if (!empty($coachRadarData)) $sourceCount++;
    ?>
    <div class="card mt-4" id="overlay-radar-section">
        <div class="card-header" style="background: linear-gradient(135deg, #8E2DE2 0%, #4A00E0 100%); color: white;">
            <h5 class="mb-0">🔀 Grafico Sovrapposizione: Confronto Multi-Fonte</h5>
            <small class="d-block mt-1 opacity-75">
                Visualizza e confronta tutte le valutazioni sovrapposte (normalizzate a percentuale)
                | Fonti: <?php echo $sourceCount; ?> disponibili
                (<?php echo !empty($areasData) ? '✅Quiz ' : '❌Quiz '; ?>
                 <?php echo !empty($autovalutazioneAreas) ? '✅Auto ' : '❌Auto '; ?>
                 <?php echo !empty($labEvalByArea) ? '✅Lab ' : '❌Lab '; ?>
                 <?php echo !empty($coachRadarData) ? '✅Coach' : '❌Coach'; ?>)
            </small>
        </div>
        <div class="card-body">
            <!-- Debug info (rimuovere in produzione) -->
            <div class="alert alert-secondary mb-3 small">
                <strong>🔧 Debug Info:</strong>
                hasOverlayData=<?php echo $hasOverlayData ? 'true' : 'false'; ?> |
                sourceCount=<?php echo $sourceCount; ?> |
                areasData=<?php echo count($areasData ?? []); ?> aree |
                autovalutazioneAreas=<?php echo count($autovalutazioneAreas ?? []); ?> aree |
                labEvalByArea=<?php echo count($labEvalByArea ?? []); ?> aree |
                coachRadarData=<?php echo count($coachRadarData ?? []); ?> aree |
                overlayAreas=<?php echo count($overlayAreas ?? []); ?> aree
            </div>
            <?php if (!$hasOverlayData): ?>
            <!-- Nessun dato disponibile -->
            <div class="alert alert-warning text-center">
                <h5>⚠️ Nessun dato disponibile per il grafico sovrapposizione</h5>
                <p class="mb-2">Per visualizzare questo grafico, lo studente deve avere almeno una delle seguenti valutazioni:</p>
                <ul class="list-unstyled mb-0">
                    <li>📊 Quiz completati con competenze assegnate</li>
                    <li>🧑 Autovalutazione completata</li>
                    <li>🔧 Valutazioni LabEval</li>
                    <li>👨‍🏫 Valutazione Formatore</li>
                </ul>
            </div>
            <?php elseif ($sourceCount < 2): ?>
            <!-- Solo una fonte disponibile -->
            <div class="alert alert-info mb-3">
                <strong>ℹ️ Nota:</strong> È disponibile solo <?php echo $sourceCount; ?> fonte di dati.
                Il grafico di sovrapposizione è più utile quando sono disponibili almeno 2 fonti per confrontarle.
            </div>
            <?php endif; ?>

            <?php if ($hasOverlayData): ?>
            <!-- Controlli Toggle -->
            <div class="alert alert-light border mb-4">
                <div class="d-flex flex-wrap align-items-center justify-content-center" style="gap: 20px;">
                    <span class="font-weight-bold">Mostra:</span>
                    <label class="mb-0 d-flex align-items-center" style="cursor: pointer;">
                        <input type="checkbox" id="overlay-toggle-quiz" checked class="mr-2">
                        <span class="badge" style="background: #28a745; color: white; padding: 8px 12px;">📊 Quiz</span>
                    </label>
                    <label class="mb-0 d-flex align-items-center" style="cursor: pointer;">
                        <input type="checkbox" id="overlay-toggle-auto" checked class="mr-2">
                        <span class="badge" style="background: #667eea; color: white; padding: 8px 12px;">🧑 Autovalutazione</span>
                    </label>
                    <label class="mb-0 d-flex align-items-center" style="cursor: pointer;">
                        <input type="checkbox" id="overlay-toggle-labeval" checked class="mr-2">
                        <span class="badge" style="background: #fd7e14; color: white; padding: 8px 12px;">🔧 LabEval</span>
                    </label>
                    <label class="mb-0 d-flex align-items-center" style="cursor: pointer;">
                        <input type="checkbox" id="overlay-toggle-coach" checked class="mr-2">
                        <span class="badge" style="background: #11998e; color: white; padding: 8px 12px;">👨‍🏫 Formatore</span>
                    </label>
                </div>
            </div>

            <!-- Grafico Radar -->
            <div class="text-center mb-4">
                <canvas id="overlayRadarChart" style="height: 550px; max-height: 600px;"></canvas>
            </div>

            <!-- Tabella Comparativa -->
            <h6 class="mt-4 mb-3">📋 Tabella Comparativa Dettagliata</h6>
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm" id="overlay-comparison-table">
                    <thead>
                        <tr style="background: #34495e; color: white;">
                            <th style="min-width: 200px;">Area</th>
                            <th class="text-center" style="background: #28a745; color: white; width: 80px;">📊 Quiz</th>
                            <th class="text-center" style="background: #667eea; color: white; width: 80px;">🧑 Auto</th>
                            <th class="text-center" style="background: #fd7e14; color: white; width: 80px;">🔧 Lab</th>
                            <th class="text-center" style="background: #11998e; color: white; width: 80px;">👨‍🏫 Coach</th>
                            <th class="text-center" style="background: #6c757d; color: white; width: 80px;">📈 Media</th>
                            <th class="text-center" style="background: #6c757d; color: white; width: 80px;">📊 Gap Max</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($overlayAreas as $code => $data):
                            $values = array_filter([$data['quiz'], $data['auto'], $data['labeval'], $data['coach']], function($v) { return $v !== null; });
                            $avg = count($values) > 0 ? round(array_sum($values) / count($values), 1) : null;
                            $gapMax = count($values) > 1 ? round(max($values) - min($values), 1) : null;
                            $gapClass = $gapMax !== null ? ($gapMax > 30 ? 'text-danger font-weight-bold' : ($gapMax > 15 ? 'text-warning' : 'text-success')) : '';
                        ?>
                        <tr>
                            <td><strong><?php echo s($data['name']); ?></strong> <small class="text-muted">(<?php echo $code; ?>)</small></td>
                            <td class="text-center"><?php echo $data['quiz'] !== null ? "<span class='badge badge-success'>{$data['quiz']}%</span>" : '<span class="text-muted">-</span>'; ?></td>
                            <td class="text-center"><?php echo $data['auto'] !== null ? "<span class='badge badge-primary'>{$data['auto']}%</span>" : '<span class="text-muted">-</span>'; ?></td>
                            <td class="text-center"><?php echo $data['labeval'] !== null ? "<span class='badge badge-warning text-dark'>{$data['labeval']}%</span>" : '<span class="text-muted">-</span>'; ?></td>
                            <td class="text-center"><?php echo $data['coach'] !== null ? "<span class='badge badge-info'>{$data['coach']}%</span>" : '<span class="text-muted">-</span>'; ?></td>
                            <td class="text-center"><?php echo $avg !== null ? "<strong>{$avg}%</strong>" : '-'; ?></td>
                            <td class="text-center <?php echo $gapClass; ?>"><?php echo $gapMax !== null ? "{$gapMax}%" : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="text-center mt-3">
                <small class="text-muted">
                    <strong>Normalizzazione:</strong> Tutti i valori sono convertiti in percentuale (0-100%).
                    Bloom 1-6 → (valore/6)×100 | N/O = 0%
                    <br><strong>Gap Max:</strong> Differenza tra valore massimo e minimo tra le fonti disponibili.
                    <span class="text-danger">Rosso > 30%</span> |
                    <span class="text-warning">Arancione > 15%</span> |
                    <span class="text-success">Verde ≤ 15%</span>
                </small>
            </div>
        </div>
    </div>

    <script>
    // Aspetta il caricamento di Chart.js (che viene caricato alla fine della pagina)
    window.addEventListener('load', function() {
        // Verifica che Chart.js sia disponibile
        if (typeof Chart === 'undefined') {
            console.error('Chart.js non caricato');
            document.getElementById('overlayRadarChart').parentElement.innerHTML =
                '<div class="alert alert-danger">Errore: Chart.js non disponibile</div>';
            return;
        }

        const overlayLabels = <?php echo json_encode($overlayLabels); ?>;
        const overlayData = {
            quiz: <?php echo json_encode($overlayQuiz); ?>,
            auto: <?php echo json_encode($overlayAuto); ?>,
            labeval: <?php echo json_encode($overlayLabeval); ?>,
            coach: <?php echo json_encode($overlayCoach); ?>
        };

        const ctx = document.getElementById('overlayRadarChart');
        if (!ctx) {
            console.error('Canvas overlayRadarChart non trovato');
            return;
        }

        const overlayChart = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: overlayLabels,
                datasets: [
                    {
                        label: '📊 Quiz',
                        data: overlayData.quiz,
                        backgroundColor: 'rgba(40, 167, 69, 0.2)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(40, 167, 69, 1)',
                        pointRadius: 4
                    },
                    {
                        label: '🧑 Autovalutazione',
                        data: overlayData.auto,
                        backgroundColor: 'rgba(102, 126, 234, 0.2)',
                        borderColor: 'rgba(102, 126, 234, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(102, 126, 234, 1)',
                        pointRadius: 4
                    },
                    {
                        label: '🔧 LabEval',
                        data: overlayData.labeval,
                        backgroundColor: 'rgba(253, 126, 20, 0.2)',
                        borderColor: 'rgba(253, 126, 20, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(253, 126, 20, 1)',
                        pointRadius: 4
                    },
                    {
                        label: '👨‍🏫 Formatore',
                        data: overlayData.coach,
                        backgroundColor: 'rgba(17, 153, 142, 0.2)',
                        borderColor: 'rgba(17, 153, 142, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(17, 153, 142, 1)',
                        pointRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            stepSize: 20,
                            callback: function(value) { return value + '%'; }
                        },
                        pointLabels: {
                            font: { size: 11 }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { size: 12 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let value = context.parsed.r;
                                return context.dataset.label + ': ' + (value !== null ? value + '%' : 'N/D');
                            }
                        }
                    }
                }
            }
        });

        // Toggle handlers
        document.getElementById('overlay-toggle-quiz')?.addEventListener('change', function() {
            overlayChart.data.datasets[0].hidden = !this.checked;
            overlayChart.update();
        });
        document.getElementById('overlay-toggle-auto')?.addEventListener('change', function() {
            overlayChart.data.datasets[1].hidden = !this.checked;
            overlayChart.update();
        });
        document.getElementById('overlay-toggle-labeval')?.addEventListener('change', function() {
            overlayChart.data.datasets[2].hidden = !this.checked;
            overlayChart.update();
        });
        document.getElementById('overlay-toggle-coach')?.addEventListener('change', function() {
            overlayChart.data.datasets[3].hidden = !this.checked;
            overlayChart.update();
        });

        console.log('Overlay Radar Chart inizializzato con successo');
    });
    </script>
    <?php endif; // Fine hasOverlayData ?>
    <?php endif; // Fine Overlay Radar ?>

    <?php if ($showCoachEvaluation && !empty($coachEvaluationData)): ?>
    <!-- VALUTAZIONE FORMATORE (subito dopo Confronto 4 Fonti) -->
    <div class="card mt-4">
        <div class="card-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">👨‍🏫 Valutazione Formatore</h5>
                    <small class="d-block mt-1 opacity-75">
                        Valutato da: <?php echo fullname($DB->get_record('user', ['id' => $coachEvaluationData->coachid])); ?>
                        | Data: <?php echo userdate($coachEvaluationData->evaluation_date ?: $coachEvaluationData->timemodified); ?>
                        | Stato: <?php echo ucfirst($coachEvaluationData->status); ?>
                    </small>
                </div>
                <a href="<?php echo new moodle_url('/local/competencymanager/coach_evaluation.php', [
                    'studentid' => $userid,
                    'sector' => $currentSector,
                    'evaluationid' => $coachEvaluationData->id,
                    'courseid' => $courseid
                ]); ?>" class="btn btn-sm btn-light">
                    ✏️ Modifica Valutazione
                </a>
            </div>
        </div>
        <div class="card-body">
            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="text-center p-3 rounded" style="background: #e8f5e9;">
                        <h3 class="text-success mb-0"><?php echo $coachEvalStats['rated']; ?>/<?php echo $coachEvalStats['total']; ?></h3>
                        <small>Competenze Valutate</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center p-3 rounded" style="background: #e3f2fd;">
                        <h3 class="text-primary mb-0"><?php echo $coachEvalAvg ?: '-'; ?></h3>
                        <small>Media Bloom (1-6)</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center p-3 rounded" style="background: #fce4ec;">
                        <h3 class="text-danger mb-0"><?php echo $coachEvalStats['not_observed']; ?></h3>
                        <small>Non Osservate (N/O)</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center p-3 rounded" style="background: #fff3e0;">
                        <h3 class="text-warning mb-0"><?php echo count($coachEvalByArea); ?></h3>
                        <small>Aree Valutate</small>
                    </div>
                </div>
            </div>

            <!-- Coach Evaluation by Area (accordion) -->
            <?php foreach ($coachEvalByArea as $area => $areaRatings): ?>
                <?php
                $areaAvg = 0;
                $areaCount = 0;
                foreach ($areaRatings as $r) {
                    if ($r->rating > 0) {
                        $areaAvg += $r->rating;
                        $areaCount++;
                    }
                }
                $areaAvg = $areaCount > 0 ? round($areaAvg / $areaCount, 1) : 0;

                // Ottieni nome completo area dal primo rating
                $firstRating = reset($areaRatings);
                $areaInfo = get_area_info($firstRating->idnumber ?? '');
                $areaFullName = $areaInfo['name'] ?? "Area $area";
                ?>
                <div class="card mb-2">
                    <div class="card-header py-2" style="background: #f8f9fa; cursor: pointer;"
                         onclick="this.nextElementSibling.classList.toggle('d-none')">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>📁 <?php echo s($areaFullName); ?></strong>
                            <div>
                                <span class="badge badge-info"><?php echo count($areaRatings); ?> comp.</span>
                                <?php if ($areaAvg > 0): ?>
                                    <span class="badge badge-success">Media: <?php echo $areaAvg; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0 d-none">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr style="background: #e9ecef;">
                                    <th>Competenza</th>
                                    <th class="text-center" style="width: 120px;">Bloom (1-6)</th>
                                    <th>Note Coach</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($areaRatings as $rating): ?>
                                    <?php
                                    $ratingDisplay = $rating->rating == 0 ? 'N/O' : $rating->rating;
                                    $ratingClass = $rating->rating == 0 ? 'secondary' :
                                                  ($rating->rating >= 5 ? 'success' :
                                                  ($rating->rating >= 3 ? 'warning' : 'danger'));
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo format_string($rating->shortname); ?></strong><br>
                                            <small class="text-muted"><?php echo s($rating->idnumber); ?></small>
                                            <?php if (!empty($rating->comp_description)): ?>
                                                <div class="mt-1" style="font-size: 0.85em; color: #555;">
                                                    <?php echo format_text($rating->comp_description, FORMAT_HTML); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if (has_capability('local/competencymanager:evaluate', $context)): ?>
                                            <!-- Editable rating dropdown -->
                                            <div class="inline-rating-editor" style="position: relative; display: inline-block;">
                                                <span class="badge badge-<?php echo $ratingClass; ?> rating-badge-clickable"
                                                      style="font-size: 1.1rem; cursor: pointer;"
                                                      data-evaluationid="<?php echo $coachEvaluationData->id; ?>"
                                                      data-competencyid="<?php echo $rating->competencyid; ?>"
                                                      data-currentrating="<?php echo $rating->rating; ?>"
                                                      onclick="showRatingDropdown(this)"
                                                      title="Clicca per modificare">
                                                    <?php echo $ratingDisplay; ?>
                                                </span>
                                            </div>
                                            <?php else: ?>
                                            <span class="badge badge-<?php echo $ratingClass; ?>" style="font-size: 1.1rem;">
                                                <?php echo $ratingDisplay; ?>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($rating->notes)): ?>
                                                <small class="text-muted"><?php echo s($rating->notes); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">-</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- General Notes -->
            <?php if (!empty($coachEvaluationData->notes)): ?>
                <div class="alert alert-info mt-3">
                    <h6 class="mb-2">📝 Note Generali del Coach:</h6>
                    <?php echo nl2br(s($coachEvaluationData->notes)); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Inline Rating Editor Script -->
    <script>
    let activeDropdown = null;

    function showRatingDropdown(badge) {
        // Remove any existing dropdown
        if (activeDropdown) {
            activeDropdown.remove();
            activeDropdown = null;
        }

        const evaluationId = badge.dataset.evaluationid;
        const competencyId = badge.dataset.competencyid;
        const currentRating = parseInt(badge.dataset.currentrating);

        // Create dropdown
        const dropdown = document.createElement('div');
        dropdown.className = 'rating-dropdown';
        dropdown.style.cssText = 'position: absolute; top: 100%; left: 50%; transform: translateX(-50%); z-index: 1000; background: white; border: 2px solid #dee2e6; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); padding: 8px; display: flex; gap: 4px; margin-top: 4px;';

        // Rating options: N/O (0), 1-6
        const options = [
            {value: 0, label: 'N/O', color: '#6c757d'},
            {value: 1, label: '1', color: '#dc3545'},
            {value: 2, label: '2', color: '#fd7e14'},
            {value: 3, label: '3', color: '#ffc107'},
            {value: 4, label: '4', color: '#20c997'},
            {value: 5, label: '5', color: '#28a745'},
            {value: 6, label: '6', color: '#155724'}
        ];

        options.forEach(opt => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = opt.label;
            btn.style.cssText = `width: 32px; height: 32px; border: 2px solid ${opt.color}; background: ${currentRating === opt.value ? opt.color : 'white'}; color: ${currentRating === opt.value ? 'white' : opt.color}; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 12px;`;
            btn.onclick = function(e) {
                e.stopPropagation();
                saveInlineRating(evaluationId, competencyId, opt.value, badge, dropdown);
            };
            dropdown.appendChild(btn);
        });

        badge.parentElement.appendChild(dropdown);
        activeDropdown = dropdown;

        // Close on click outside
        setTimeout(() => {
            document.addEventListener('click', closeDropdownOnClickOutside);
        }, 10);
    }

    function closeDropdownOnClickOutside(e) {
        if (activeDropdown && !activeDropdown.contains(e.target) && !e.target.classList.contains('rating-badge-clickable')) {
            activeDropdown.remove();
            activeDropdown = null;
            document.removeEventListener('click', closeDropdownOnClickOutside);
        }
    }

    function saveInlineRating(evaluationId, competencyId, rating, badge, dropdown) {
        // Show saving indicator
        badge.textContent = '...';
        badge.style.opacity = '0.5';

        fetch(M.cfg.wwwroot + '/local/competencymanager/ajax_save_evaluation.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                sesskey: M.cfg.sesskey,
                action: 'save_single_rating',
                evaluationid: evaluationId,
                competencyid: competencyId,
                rating: rating
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update badge
                const colors = {
                    0: {bg: '#6c757d', class: 'secondary'},
                    1: {bg: '#dc3545', class: 'danger'},
                    2: {bg: '#fd7e14', class: 'warning'},
                    3: {bg: '#ffc107', class: 'warning'},
                    4: {bg: '#20c997', class: 'success'},
                    5: {bg: '#28a745', class: 'success'},
                    6: {bg: '#155724', class: 'success'}
                };
                const display = rating === 0 ? 'N/O' : rating;
                const colorInfo = colors[rating] || colors[0];

                badge.textContent = display;
                badge.className = `badge badge-${colorInfo.class} rating-badge-clickable`;
                badge.dataset.currentrating = rating;
                badge.style.opacity = '1';

                // Show success toast
                showToast('✅ Valutazione salvata', 'success');
            } else {
                badge.textContent = badge.dataset.currentrating == 0 ? 'N/O' : badge.dataset.currentrating;
                badge.style.opacity = '1';
                showToast('❌ Errore: ' + data.message, 'error');
            }
        })
        .catch(error => {
            badge.textContent = badge.dataset.currentrating == 0 ? 'N/O' : badge.dataset.currentrating;
            badge.style.opacity = '1';
            showToast('❌ Errore di connessione', 'error');
        });

        // Close dropdown
        dropdown.remove();
        activeDropdown = null;
        document.removeEventListener('click', closeDropdownOnClickOutside);
    }

    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.style.cssText = `position: fixed; bottom: 20px; right: 20px; padding: 12px 20px; border-radius: 8px; color: white; font-weight: 500; z-index: 9999; background: ${type === 'success' ? '#28a745' : '#dc3545'};`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    </script>
    <?php endif; ?>

    <?php
    // GAP ANALYSIS
    if ($showGapAnalysis && !empty($gapAnalysisData)):
        $countSopra = count(array_filter($gapAnalysisData, fn($g) => $g['tipo'] === 'sopravvalutazione'));
        $countSotto = count(array_filter($gapAnalysisData, fn($g) => $g['tipo'] === 'sottovalutazione'));
        $countAllineato = count(array_filter($gapAnalysisData, fn($g) => $g['tipo'] === 'allineato'));
    ?>
    <div class="card mt-4">
        <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
            <h5 class="mb-0">📊 Gap Analysis: Autovalutazione vs Performance Reale</h5>
        </div>
        <div class="card-body">
            <!-- INDICAZIONE SOGLIE CONFIGURATE -->
            <div class="alert alert-secondary mb-3 py-2">
                <small>
                    <strong>⚙️ Soglie applicate:</strong> 
                    Gap ≤ <strong><?php echo $sogliaAllineamento; ?>%</strong> = ✅ Allineato | 
                    Gap > <strong><?php echo $sogliaAllineamento; ?>%</strong> = ⚠️/⬆️⬇️ Attenzione | 
                    Gap > <strong><?php echo $sogliaCritico; ?>%</strong> = 🔴 Critico
                </small>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="text-center p-3 rounded" style="background: #fadbd8;">
                        <h3 class="text-danger mb-0"><?php echo $countSopra; ?></h3>
                        <small>⬆️ Sopravvalutazione</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-3 rounded" style="background: #d5f5e3;">
                        <h3 class="text-success mb-0"><?php echo $countAllineato; ?></h3>
                        <small>✅ Allineato</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-3 rounded" style="background: #fef9e7;">
                        <h3 class="text-warning mb-0"><?php echo $countSotto; ?></h3>
                        <small>⬇️ Sottovalutazione</small>
                    </div>
                </div>
            </div>
            
            <table class="table table-bordered">
                <thead>
                    <tr style="background: #34495e; color: white;">
                        <th>Competenza</th>
                        <th class="text-center" style="width: 100px;">🧑 Auto</th>
                        <th class="text-center" style="width: 100px;">📊 Reale</th>
                        <th class="text-center" style="width: 100px;">Gap</th>
                        <th class="text-center" style="width: 150px;">Analisi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gapAnalysisData as $gap): ?>
                    <tr style="background: <?php echo $gap['bg']; ?>;">
                        <td>
                            <strong><?php echo htmlspecialchars($gap['name']); ?></strong><br>
                            <small class="text-muted"><?php echo $gap['idnumber']; ?></small>
                        </td>
                        <td class="text-center"><span class="badge badge-primary"><?php echo round($gap['autovalutazione']); ?>%</span></td>
                        <td class="text-center"><span class="badge badge-success"><?php echo round($gap['performance']); ?>%</span></td>
                        <td class="text-center" style="color: <?php echo $gap['colore']; ?>; font-weight: bold;">
                            <?php echo $gap['icona'] . ' ' . ($gap['differenza'] > 0 ? '+' : '') . round($gap['differenza']) . '%'; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($gap['tipo'] === 'sopravvalutazione'): ?>
                            <span class="badge badge-danger">⬆️ Sopravvalutazione</span>
                            <?php elseif ($gap['tipo'] === 'sottovalutazione'): ?>
                            <span class="badge badge-warning">⬇️ Sottovalutazione</span>
                            <?php else: ?>
                            <span class="badge badge-success">✅ Allineato</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <?php
    // SPUNTI COLLOQUIO
    if ($showSpuntiColloquio && !empty($colloquioHints)):
    ?>
    <div class="card mt-4">
        <div class="card-header" style="background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); color: #333;">
            <h5 class="mb-0">💬 Spunti per il Colloquio</h5>
        </div>
        <div class="card-body">
            <!-- INDICAZIONE SOGLIE CONFIGURATE -->
            <div class="alert alert-secondary mb-3 py-2">
                <small>
                    <strong>⚙️ Classificazione:</strong>
                    🔴 Gap Critico = > <strong><?php echo $sogliaCritico; ?>%</strong> | 
                    ⚠️ Gap Moderato = <strong><?php echo $sogliaAllineamento; ?>% - <?php echo $sogliaCritico; ?>%</strong> | 
                    ✅ Allineato = ≤ <strong><?php echo $sogliaAllineamento; ?>%</strong>
                </small>
            </div>
            
            <?php if (!empty($colloquioHints['critici'])): ?>
            <div class="mb-4">
                <h6 class="text-danger">🔴 Priorità Alta - Gap Critici</h6>
                <?php foreach ($colloquioHints['critici'] as $hint): ?>
                <div class="card mb-2 border-danger">
                    <div class="card-body py-2">
                        <strong><?php echo htmlspecialchars($hint['competenza']); ?></strong>
                        <span class="badge badge-danger ml-2">Gap: <?php echo round($hint['gap']['differenza']); ?>%</span>
                        <p class="mb-1 small"><?php echo $hint['messaggio']; ?></p>
                        <p class="mb-0 small text-primary"><em>💡 "<?php echo $hint['domanda']; ?>"</em></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($colloquioHints['attenzione'])): ?>
            <div class="mb-4">
                <h6 class="text-warning">⚠️ Attenzione - Gap Moderati</h6>
                <?php foreach ($colloquioHints['attenzione'] as $hint): ?>
                <div class="card mb-2 border-warning">
                    <div class="card-body py-2">
                        <strong><?php echo htmlspecialchars($hint['competenza']); ?></strong>
                        <span class="badge badge-warning ml-2">Gap: <?php echo round($hint['gap']['differenza']); ?>%</span>
                        <p class="mb-1 small"><?php echo $hint['messaggio']; ?></p>
                        <p class="mb-0 small text-primary"><em>💡 "<?php echo $hint['domanda']; ?>"</em></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($colloquioHints['positivi'])): ?>
            <div class="mb-4">
                <h6 class="text-success">✅ Punti di Forza</h6>
                <?php foreach (array_slice($colloquioHints['positivi'], 0, 5) as $hint): ?>
                <div class="card mb-2 border-success">
                    <div class="card-body py-2">
                        <strong><?php echo htmlspecialchars($hint['competenza']); ?></strong>
                        <span class="badge badge-success ml-2"><?php echo $hint['gap']['icona']; ?></span>
                        <p class="mb-0 small"><?php echo $hint['messaggio']; ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // ============================================
}

// ========================================
// TAB PIANO D'AZIONE
// ========================================
else if ($tab === 'action') {
    if (!empty($actionPlan['critical'])) {
        echo '<div class="card mb-3 border-danger"><div class="card-header bg-danger text-white"><h5 class="mb-0">🔴 CRITICO - Formazione urgente richiesta</h5></div><div class="card-body p-0"><table class="table table-hover mb-0"><thead><tr><th>Competenza</th><th>Codice</th><th>Risposte</th><th>%</th></tr></thead><tbody>';
        foreach ($actionPlan['critical'] as $item) echo '<tr class="table-danger"><td>' . htmlspecialchars($item['name']) . '</td><td><small>' . $item['code'] . '</small></td><td>' . $item['correct'] . '/' . $item['total'] . '</td><td><span class="badge badge-danger">' . $item['percentage'] . '%</span></td></tr>';
        echo '</tbody></table></div></div>';
    }
    if (!empty($actionPlan['toImprove'])) {
        echo '<div class="card mb-3 border-warning"><div class="card-header bg-warning"><h5 class="mb-0">🟠 DA MIGLIORARE - Richiede attenzione</h5></div><div class="card-body p-0"><table class="table table-hover mb-0"><thead><tr><th>Competenza</th><th>Codice</th><th>Risposte</th><th>%</th></tr></thead><tbody>';
        foreach ($actionPlan['toImprove'] as $item) echo '<tr class="table-warning"><td>' . htmlspecialchars($item['name']) . '</td><td><small>' . $item['code'] . '</small></td><td>' . $item['correct'] . '/' . $item['total'] . '</td><td><span class="badge badge-warning">' . $item['percentage'] . '%</span></td></tr>';
        echo '</tbody></table></div></div>';
    }
    if (!empty($actionPlan['good'])) {
        echo '<div class="card mb-3 border-info"><div class="card-header bg-info text-white"><h5 class="mb-0">✅ ACQUISITE - Consolidare con pratica</h5></div><div class="card-body p-0"><table class="table table-hover mb-0"><thead><tr><th>Competenza</th><th>Codice</th><th>Risposte</th><th>%</th></tr></thead><tbody>';
        foreach ($actionPlan['good'] as $item) echo '<tr class="table-info"><td>' . htmlspecialchars($item['name']) . '</td><td><small>' . $item['code'] . '</small></td><td>' . $item['correct'] . '/' . $item['total'] . '</td><td><span class="badge badge-info">' . $item['percentage'] . '%</span></td></tr>';
        echo '</tbody></table></div></div>';
    }
    if (!empty($actionPlan['excellence'])) {
        echo '<div class="card mb-3 border-success"><div class="card-header bg-success text-white"><h5 class="mb-0">🌟 ECCELLENZA - Padronanza completa</h5></div><div class="card-body p-0"><table class="table table-hover mb-0"><thead><tr><th>Competenza</th><th>Codice</th><th>Risposte</th><th>%</th></tr></thead><tbody>';
        foreach ($actionPlan['excellence'] as $item) echo '<tr class="table-success"><td>' . htmlspecialchars($item['name']) . '</td><td><small>' . $item['code'] . '</small></td><td>' . $item['correct'] . '/' . $item['total'] . '</td><td><span class="badge badge-success">' . $item['percentage'] . '%</span></td></tr>';
        echo '</tbody></table></div></div>';
    }
}

// ========================================
// TAB QUIZ
// ========================================
else if ($tab === 'quiz') {
    echo '<div class="card">';
    echo '<div class="card-header bg-secondary text-white">';
    echo '<h5 class="mb-0">📝 Confronto per Quiz</h5>';
    echo '<small class="d-block mt-1 opacity-75">Clicca sul nome del quiz per vedere domande e risposte dello studente</small>';
    echo '</div>';
    echo '<div class="card-body p-0">';
    echo '<table class="table table-hover mb-0">';
    echo '<thead><tr><th>Quiz</th><th>Tentativo</th><th>Data</th><th>Punteggio</th><th>Competenze</th><th>Azione</th></tr></thead>';
    echo '<tbody>';
    if (!empty($quizComparison)) {
        foreach ($quizComparison as $quiz) {
            foreach ($quiz['attempts'] as $attempt) {
                $badgeClass = $attempt['percentage'] >= 60 ? 'success' : ($attempt['percentage'] >= 40 ? 'warning' : 'danger');
                $reviewUrl = $CFG->wwwroot . '/mod/quiz/review.php?attempt=' . $attempt['attemptid'];
                echo '<tr>';
                echo '<td><a href="' . $reviewUrl . '" target="_blank" title="Clicca per vedere domande e risposte" style="color: #333; text-decoration: none;"><strong>' . format_string($quiz['name']) . '</strong> <small class="text-primary">🔗</small></a></td>';
                echo '<td>#' . $attempt['attempt_number'] . '</td>';
                echo '<td>' . date('d/m/Y H:i', $attempt['timefinish']) . '</td>';
                echo '<td><span class="badge badge-' . $badgeClass . '">' . $attempt['percentage'] . '%</span></td>';
                echo '<td>' . $attempt['competencies'] . '</td>';
                echo '<td><a href="' . $reviewUrl . '" target="_blank" class="btn btn-sm btn-outline-primary" title="Vedi domande e risposte">👁️ Review</a></td>';
                echo '</tr>';
            }
        }
    } else {
        echo '<tr><td colspan="6" class="text-center text-muted">Nessun tentativo quiz completato</td></tr>';
    }
    echo '</tbody></table></div></div>';
}

// ========================================
// TAB PROGRESSI
// ========================================
else if ($tab === 'progress') {
    echo '<div class="card"><div class="card-header bg-primary text-white"><h5 class="mb-0">📈 Progressi nel Tempo</h5></div><div class="card-body">';
    if (!empty($progressData)) {
        echo '<canvas id="progressChart" style="max-height: 300px;"></canvas>';
    } else {
        echo '<p class="text-muted text-center">Non ci sono ancora abbastanza dati per mostrare i progressi. Completa più quiz per vedere l\'andamento.</p>';
    }
    echo '</div></div>';
}

// ========================================
// TAB DETTAGLI
// ========================================
else if ($tab === 'details') {
    $filteredCompetencies = $competencies;
    
    foreach ($filteredCompetencies as &$comp) {
        $code = $comp['idnumber'] ?: $comp['name'];
        $areaInfo = get_area_info($code);
        $comp['area_code'] = $areaInfo['code'];
        $comp['area_name'] = $areaInfo['name'];
        
        $pct = $comp['percentage'];
        if ($pct >= 80) $comp['level'] = 'excellent';
        else if ($pct >= 60) $comp['level'] = 'good';
        else if ($pct >= 50) $comp['level'] = 'sufficient';
        else if ($pct >= 30) $comp['level'] = 'insufficient';
        else $comp['level'] = 'critical';
    }
    unset($comp);
    
    if ($filterLevel !== 'all') {
        $filteredCompetencies = array_filter($filteredCompetencies, fn($c) => $c['level'] === $filterLevel);
    }
    
    if (!empty($filterArea)) {
        $filteredCompetencies = array_filter($filteredCompetencies, fn($c) => $c['area_code'] === $filterArea);
    }
    
    switch ($sortBy) {
        case 'perc_desc': usort($filteredCompetencies, fn($a, $b) => $b['percentage'] <=> $a['percentage']); break;
        case 'perc_asc': usort($filteredCompetencies, fn($a, $b) => $a['percentage'] <=> $b['percentage']); break;
        case 'questions': usort($filteredCompetencies, fn($a, $b) => $b['total_questions'] <=> $a['total_questions']); break;
        default: usort($filteredCompetencies, fn($a, $b) => strcmp($a['area_code'], $b['area_code'])); break;
    }
    
    $levelCounts = ['excellent' => 0, 'good' => 0, 'sufficient' => 0, 'insufficient' => 0, 'critical' => 0];
    foreach ($competencies as $c) {
        $pct = $c['percentage'];
        if ($pct >= 80) $levelCounts['excellent']++;
        else if ($pct >= 60) $levelCounts['good']++;
        else if ($pct >= 50) $levelCounts['sufficient']++;
        else if ($pct >= 30) $levelCounts['insufficient']++;
        else $levelCounts['critical']++;
    }
    
    $uniqueAreas = [];
    foreach ($competencies as $c) {
        $code = $c['idnumber'] ?: $c['name'];
        $areaInfo = get_area_info($code);
        $aCode = $areaInfo['code'];
        if (!isset($uniqueAreas[$aCode])) {
            $uniqueAreas[$aCode] = ['name' => $areaInfo['name'], 'icon' => '📁'];
        }
    }
    ksort($uniqueAreas);
    
    $baseUrl = new moodle_url('/local/competencymanager/student_report.php', [
        'userid' => $userid, 'courseid' => $courseid, 'tab' => 'details'
    ]);
    ?>
    <div class="card">
        <div class="card-header bg-dark text-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h5 class="mb-0">📋 Dettaglio Competenze</h5>
                <span class="badge badge-light"><?php echo count($filteredCompetencies); ?> / <?php echo count($competencies); ?> competenze</span>
            </div>
        </div>
        
        <div class="card-body border-bottom">
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label><strong>Ordina per:</strong></label>
                    <select id="sortSelect" class="form-control form-control-sm">
                        <option value="area" <?php echo $sortBy=='area'?'selected':''; ?>>📁 Per Area</option>
                        <option value="perc_desc" <?php echo $sortBy=='perc_desc'?'selected':''; ?>>📈 % Decrescente</option>
                        <option value="perc_asc" <?php echo $sortBy=='perc_asc'?'selected':''; ?>>📉 % Crescente</option>
                        <option value="questions" <?php echo $sortBy=='questions'?'selected':''; ?>>❓ Per Domande</option>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label><strong>Filtra livello:</strong></label>
                    <select id="filterSelect" class="form-control form-control-sm">
                        <option value="all">Tutti (<?php echo count($competencies); ?>)</option>
                        <option value="excellent" <?php echo $filterLevel=='excellent'?'selected':''; ?>>🌟 Eccellente (<?php echo $levelCounts['excellent']; ?>)</option>
                        <option value="good" <?php echo $filterLevel=='good'?'selected':''; ?>>✅ Buono (<?php echo $levelCounts['good']; ?>)</option>
                        <option value="sufficient" <?php echo $filterLevel=='sufficient'?'selected':''; ?>>⚠️ Sufficiente (<?php echo $levelCounts['sufficient']; ?>)</option>
                        <option value="insufficient" <?php echo $filterLevel=='insufficient'?'selected':''; ?>>⚡ Insufficiente (<?php echo $levelCounts['insufficient']; ?>)</option>
                        <option value="critical" <?php echo $filterLevel=='critical'?'selected':''; ?>>🔴 Critico (<?php echo $levelCounts['critical']; ?>)</option>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label><strong>Filtra area:</strong></label>
                    <select id="filterAreaSelect" class="form-control form-control-sm">
                        <option value="">Tutte le aree</option>
                        <?php foreach ($uniqueAreas as $aCode => $aInfo): ?>
                        <option value="<?php echo $aCode; ?>" <?php echo $filterArea==$aCode?'selected':''; ?>><?php echo $aInfo['icon'] . ' ' . $aInfo['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="button" class="btn btn-primary btn-sm mt-2" onclick="applyFilters()">🔄 Applica Filtri</button>
        </div>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Area</th>
                        <th>Codice</th>
                        <th>Competenza</th>
                        <th class="text-center">Risposte</th>
                        <th class="text-center">%</th>
                        <th>Valutazione</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 1; foreach ($filteredCompetencies as $comp): 
                    $code = $comp['idnumber'] ?: $comp['name'];
                    $aCode = $comp['area_code'];
                    $areaInfo = $areaDescriptions[$aCode] ?? ['icon' => '📁', 'color' => '#95a5a6'];
                    $compInfo = $competencyDescriptions[$code] ?? null;
                    $displayName = $compInfo ? ($compInfo['full_name'] ?? $compInfo['name']) : (!empty($comp['description']) ? $comp['description'] : $code);
                    $band = get_evaluation_band($comp['percentage']);
                ?>
                <tr style="border-left: 4px solid <?php echo $band['color']; ?>;">
                    <td class="text-center"><strong><?php echo $i; ?></strong></td>
                    <td><span class="badge" style="background: <?php echo $areaInfo['color'] ?? '#95a5a6'; ?>; color: white;"><?php echo ($areaInfo['icon'] ?? '📁') . ' ' . $aCode; ?></span></td>
                    <td><small><?php echo $code; ?></small></td>
                    <td><?php echo htmlspecialchars($displayName); ?></td>
                    <td class="text-center"><?php echo $comp['correct_questions']; ?>/<?php echo $comp['total_questions']; ?></td>
                    <td class="text-center"><span class="badge badge-<?php echo $band['class']; ?>"><?php echo $comp['percentage']; ?>%</span></td>
                    <td style="color: <?php echo $band['color']; ?>; font-weight: bold;"><?php echo $band['icon']; ?> <?php echo $band['label']; ?></td>
                </tr>
                <?php $i++; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
    function applyFilters() {
        const sort = document.getElementById('sortSelect').value;
        const filter = document.getElementById('filterSelect').value;
        const filterArea = document.getElementById('filterAreaSelect').value;

        let url = '<?php echo $baseUrl->out(false); ?>';
        url += '&sort=' + sort;
        url += '&filter=' + filter;
        if (filterArea) url += '&filter_area=' + filterArea;

        window.location.href = url;
    }
    </script>

    <!-- ============================================
         MAPPA COMPETENZE PER AREA
         ============================================ -->
    <?php
    // Se $areasData è vuoto, carica aree dal framework competenze (come reports_v2.php)
    $mappaAreasData = $areasData;

    // Mappa icone per codice area (identica a reports_v2.php)
    // Usa solo il codice area (A, B, C oppure LMB, CNC, 1C, etc.)
    $area_icons = [
        'A' => '📋', 'B' => '🔧', 'C' => '🛢️', 'D' => '💨', 'E' => '⚙️',
        'F' => '🛞', 'G' => '💻', 'H' => '📡', 'I' => '❄️', 'J' => '🔋',
        'K' => '🚗', 'L' => '🛡️', 'M' => '👤', 'N' => '✅',
        // MECCANICA
        'LMB' => '🔧', 'LMC' => '⚙️', 'CNC' => '🖥️', 'ASS' => '🔩',
        'MIS' => '📏', 'GEN' => '🏭', 'MAN' => '🔨', 'DT' => '📐',
        'AUT' => '🤖', 'PIAN' => '📋', 'SAQ' => '🛡️', 'CSP' => '🤝', 'PRG' => '💡',
        'LAV' => '🏭', 'MAT' => '🧱', 'CQ' => '✅', 'AREA' => '📦',
        // CHIMFARM
        '1C' => '📜', '1G' => '📦', '1O' => '⚗️', '2M' => '📏',
        '3C' => '🔬', '4S' => '🛡️', '5S' => '🧫', '6P' => '🏭',
        '7S' => '🔧', '8T' => '💻', '9A' => '📊',
        // AUTOMOBILE specifici
        'MAu' => '🚗', 'MR' => '🔧',
    ];

    $area_colors = [
        'A' => '#3498db', 'B' => '#e74c3c', 'C' => '#f39c12', 'D' => '#9b59b6',
        'E' => '#1abc9c', 'F' => '#e67e22', 'G' => '#2ecc71', 'H' => '#00bcd4',
        'I' => '#3f51b5', 'J' => '#ff5722', 'K' => '#795548', 'L' => '#607d8b',
        'M' => '#8bc34a', 'N' => '#009688',
        // MECCANICA
        'LMB' => '#795548', 'LMC' => '#607d8b', 'CNC' => '#00bcd4', 'ASS' => '#f39c12',
        'MIS' => '#1abc9c', 'GEN' => '#9e9e9e', 'MAN' => '#e67e22', 'DT' => '#3498db',
        'AUT' => '#e74c3c', 'PIAN' => '#9b59b6', 'SAQ' => '#c0392b', 'CSP' => '#8e44ad', 'PRG' => '#2ecc71',
        'LAV' => '#9e9e9e', 'MAT' => '#795548', 'CQ' => '#27ae60', 'AREA' => '#7f8c8d',
        // CHIMFARM
        '1C' => '#3498db', '1G' => '#e67e22', '1O' => '#9b59b6', '2M' => '#1abc9c',
        '3C' => '#2ecc71', '4S' => '#e74c3c', '5S' => '#00bcd4', '6P' => '#f39c12',
        '7S' => '#607d8b', '8T' => '#3f51b5', '9A' => '#8bc34a',
        // AUTOMOBILE
        'MAu' => '#3498db', 'MR' => '#e74c3c',
    ];

    // Funzione helper per trovare icona/colore (come reports_v2.php)
    function get_area_icon_info($areaKey, $area_icons, $area_colors) {
        // Estrai il codice area dalla key (es. MECCANICA_ASS -> ASS)
        $parts = explode('_', $areaKey);
        $area_code = $parts[1] ?? $areaKey;

        $icon = $area_icons[$area_code] ?? '📁';
        $color = $area_colors[$area_code] ?? '#95a5a6';

        return [
            'icona' => $icon,
            'colore' => $color
        ];
    }

    if (empty($mappaAreasData)) {
        // Carica competenze dal framework FTM
        $framework_competencies = $DB->get_records_sql("
            SELECT c.id, c.shortname, c.description, c.idnumber
            FROM {competency} c
            JOIN {competency_framework} cf ON c.competencyframeworkid = cf.id
            WHERE cf.shortname LIKE '%FTM%' OR cf.shortname LIKE '%Meccanica%'
            ORDER BY c.idnumber
        ");

        // Organizza per area usando la key completa (SETTORE_AREA)
        foreach ($framework_competencies as $comp) {
            $areaInfo = get_area_info($comp->idnumber);
            $areaKey = $areaInfo['key']; // Es. "MECCANICA_ASS", "AUTOMOBILE_MAu"
            $iconInfo = get_area_icon_info($areaKey, $area_icons, $area_colors);

            // Estrai settore e codice area dalla key
            $keyParts = explode('_', $areaKey);
            $areaSector = strtolower($keyParts[0] ?? 'altro');
            $areaCode = $keyParts[1] ?? $areaKey;

            // Usa il nome dall'area_info se disponibile, altrimenti il codice
            $areaName = $areaInfo['name'] ?? $areaCode;

            if (!isset($mappaAreasData[$areaKey])) {
                $mappaAreasData[$areaKey] = [
                    'code' => $areaKey,
                    'area_code' => $areaCode,
                    'name' => $areaName,
                    'icon' => $iconInfo['icona'],
                    'color' => $iconInfo['colore'],
                    'sector' => $areaSector,
                    'percentage' => 0,
                    'count' => 0,
                    'quiz_count' => 0,
                    'autoval_count' => 0
                ];
            }
            $mappaAreasData[$areaKey]['count']++;
        }

        // Carica risultati quiz per questo studente
        $quiz_results = $DB->get_records_sql("
            SELECT
                qa.questionid,
                qas.fraction,
                q.name as questionname
            FROM {quiz_attempts} quiza
            JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
            JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
            JOIN {question} q ON q.id = qa.questionid
            WHERE quiza.userid = ?
            AND quiza.state = 'finished'
            AND qas.sequencenumber = (
                SELECT MAX(qas2.sequencenumber)
                FROM {question_attempt_steps} qas2
                WHERE qas2.questionattemptid = qa.id
            )
        ", [$userid]);

        // Mappa domande-competenze
        $question_competencies = [];
        if ($DB->get_manager()->table_exists('qbank_competenciesbyquestion')) {
            $mappings = $DB->get_records('qbank_competenciesbyquestion');
            foreach ($mappings as $map) {
                $question_competencies[$map->questionid][] = $map->competencyid;
            }
        }

        // Calcola punteggi per area
        $area_scores = [];
        foreach ($quiz_results as $result) {
            if (!isset($question_competencies[$result->questionid])) continue;

            foreach ($question_competencies[$result->questionid] as $compid) {
                if (!isset($framework_competencies[$compid])) continue;
                $comp = $framework_competencies[$compid];

                $areaInfo = get_area_info($comp->idnumber);
                $areaKey = $areaInfo['key']; // Usa la key completa

                if (!isset($area_scores[$areaKey])) {
                    $area_scores[$areaKey] = ['sum' => 0, 'count' => 0];
                }

                $score = ($result->fraction !== null) ? floatval($result->fraction) * 100 : 0;
                $area_scores[$areaKey]['sum'] += $score;
                $area_scores[$areaKey]['count']++;
            }
        }

        // Applica punteggi alle aree
        foreach ($area_scores as $areaKey => $scores) {
            if (isset($mappaAreasData[$areaKey]) && $scores['count'] > 0) {
                $mappaAreasData[$areaKey]['percentage'] = round($scores['sum'] / $scores['count'], 1);
                $mappaAreasData[$areaKey]['quiz_count'] = $scores['count'];
            }
        }

        ksort($mappaAreasData);
    }
    ?>
    <div class="mappa-competenze-section">
        <div class="mappa-header">
            <h3>🎯 Mappa Competenze per Area</h3>
        </div>
        <div class="mappa-body">
            <!-- Filter Bar -->
            <div class="filter-bar-mappa">
                <span class="filter-label">Filtra per:</span>
                <select id="filterMapSector" onchange="applyMapFilters()">
                    <option value="all">Tutti i settori</option>
                    <option value="meccanica">Meccanica</option>
                    <option value="automobile">Automobile</option>
                    <option value="automazione">Automazione</option>
                    <option value="logistica">Logistica</option>
                    <option value="elettricita">Elettricità</option>
                    <option value="metalcostruzione">Metalcostruzione</option>
                    <option value="chimfarm">Chimico-Farmaceutico</option>
                </select>
                <select id="filterMapStatus" onchange="applyMapFilters()">
                    <option value="all">Tutti gli stati</option>
                    <option value="critical">Solo critici</option>
                    <option value="warning">Solo attenzione</option>
                    <option value="good">Solo buoni</option>
                    <option value="excellent">Solo eccellenti</option>
                </select>
                <button class="btn-reset" onclick="resetMapFilters()">🔄 Reset</button>
            </div>

            <!-- Legenda -->
            <div class="mappa-legend">
                <div class="mappa-legend-item">
                    <div class="mappa-legend-dot" style="background: #28a745;"></div>
                    <span>Eccellente (≥80%)</span>
                </div>
                <div class="mappa-legend-item">
                    <div class="mappa-legend-dot" style="background: #17a2b8;"></div>
                    <span>Buono (60-79%)</span>
                </div>
                <div class="mappa-legend-item">
                    <div class="mappa-legend-dot" style="background: #ffc107;"></div>
                    <span>Sufficiente (50-59%)</span>
                </div>
                <div class="mappa-legend-item">
                    <div class="mappa-legend-dot" style="background: #dc3545;"></div>
                    <span>Critico (<50%)</span>
                </div>
                <div class="mappa-legend-item">
                    <div class="mappa-legend-dot" style="background: #e9ecef;"></div>
                    <span>Non valutato</span>
                </div>
            </div>

            <!-- Grid Aree -->
            <div class="areas-grid">
                <?php foreach ($mappaAreasData as $areaCode => $areaData):
                    $pct = $areaData['percentage'] ?? 0;
                    $quiz_count = $areaData['quiz_count'] ?? 0;

                    // Usa il settore salvato, oppure estrai dal codice area
                    $card_sector = $areaData['sector'] ?? strtolower(explode('_', $areaCode)[0] ?? 'altro');

                    if ($quiz_count == 0) {
                        $status_class = 'nodata';
                        $status_label = 'Non valutato';
                        $status_color = '#adb5bd';
                    } elseif ($pct >= 80) {
                        $status_class = 'excellent';
                        $status_label = 'Eccellente';
                        $status_color = '#28a745';
                    } elseif ($pct >= 60) {
                        $status_class = 'good';
                        $status_label = 'Buono';
                        $status_color = '#17a2b8';
                    } elseif ($pct >= 50) {
                        $status_class = 'warning';
                        $status_label = 'Sufficiente';
                        $status_color = '#ffc107';
                    } else {
                        $status_class = 'critical';
                        $status_label = 'Critico';
                        $status_color = '#dc3545';
                    }
                ?>
                <div class="area-card"
                     data-area="<?php echo $areaCode; ?>"
                     data-sector="<?php echo $card_sector; ?>"
                     data-status="<?php echo $status_class; ?>"
                     onclick="openAreaModal('<?php echo htmlspecialchars($areaCode, ENT_QUOTES); ?>')"
                     style="border-left-color: <?php echo $areaData['color'] ?? '#95a5a6'; ?>; <?php echo $quiz_count == 0 ? 'opacity: 0.6;' : ''; ?>">
                    <div class="area-icon"><?php echo $areaData['icon'] ?? '📁'; ?></div>
                    <div class="area-name"><?php echo $areaData['name'] ?? $areaCode; ?></div>
                    <div class="area-count"><?php echo $areaData['count'] ?? 0; ?> competenze</div>
                    <div class="area-mini-comparison">
                        <div class="mini-bar-container">
                            <div class="mini-bar-label">Quiz <?php echo $quiz_count > 0 ? "($quiz_count)" : ''; ?></div>
                            <div class="mini-bar"><div class="mini-bar-fill quiz" style="width: <?php echo $pct; ?>%;"></div></div>
                        </div>
                    </div>
                    <div class="area-stats">
                        <span class="area-percentage" style="color: <?php echo $status_color; ?>;">
                            <?php echo $quiz_count > 0 ? round($pct) . '%' : '-'; ?>
                        </span>
                        <span class="area-status status-<?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <p style="margin-top: 15px; color: #6c757d; font-size: 0.85em;">
                💡 Clicca su un'area per vedere il dettaglio delle competenze
            </p>
        </div>
    </div>

    <script>
    function filterByArea(areaCode) {
        document.getElementById('filterAreaSelect').value = areaCode;
        applyFilters();
    }

    // Filtri per la Mappa Competenze
    function applyMapFilters() {
        const statusFilter = document.getElementById('filterMapStatus').value;
        const sectorFilter = document.getElementById('filterMapSector').value;

        const cards = document.querySelectorAll('.mappa-competenze-section .area-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const cardStatus = card.dataset.status;
            const cardSector = card.dataset.sector;

            const matchStatus = (statusFilter === 'all' || cardStatus === statusFilter);
            const matchSector = (sectorFilter === 'all' || cardSector === sectorFilter);

            if (matchStatus && matchSector) {
                card.style.display = 'block';
                card.style.animation = 'fadeIn 0.3s ease';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        // Aggiorna contatore
        console.log('🎯 ' + visibleCount + ' aree visualizzate');
    }

    function resetMapFilters() {
        document.getElementById('filterMapStatus').value = 'all';
        document.getElementById('filterMapSector').value = 'all';

        document.querySelectorAll('.mappa-competenze-section .area-card').forEach(card => {
            card.style.display = 'block';
        });

        console.log('🔄 Filtri resettati');
    }
    </script>
    <?php
}

echo '</div>'; // Fine tab-content

// ========================================
// JAVASCRIPT
// ========================================
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ============================================
// NAVIGAZIONE STUDENTE A CASCATA
// ============================================
const navAjaxUrl = '<?php echo new moodle_url('/local/competencymanager/ajax_navigation.php'); ?>';
const navSessionKey = '<?php echo sesskey(); ?>';
const currentUserId = <?php echo $userid; ?>;
const currentCourseid = <?php echo $courseid; ?>;

// Carica colori per coorte selezionata
async function loadColors() {
    const cohortId = document.getElementById('nav_cohort').value;
    const colorSelect = document.getElementById('nav_color');

    colorSelect.innerHTML = '<option value="">Caricamento...</option>';

    try {
        const response = await fetch(navAjaxUrl + '?action=get_colors&cohortid=' + cohortId + '&sesskey=' + navSessionKey);
        const data = await response.json();

        if (data.success) {
            colorSelect.innerHTML = '<option value="">Tutti i colori</option>';
            data.data.forEach(color => {
                const opt = document.createElement('option');
                opt.value = color.color;
                opt.textContent = color.name + ' (' + color.student_count + ')';
                opt.dataset.hex = color.color_hex;
                colorSelect.appendChild(opt);
            });
        } else {
            colorSelect.innerHTML = '<option value="">Nessun colore</option>';
        }
    } catch (e) {
        console.error('Errore caricamento colori:', e);
        colorSelect.innerHTML = '<option value="">Errore</option>';
    }

    // Resetta settimane e studenti
    document.getElementById('nav_week').innerHTML = '<option value="0">Tutte le settimane</option>';
    loadStudents();
}

// Carica settimane per coorte e colore
async function loadWeeks() {
    const cohortId = document.getElementById('nav_cohort').value;
    const color = document.getElementById('nav_color').value;
    const weekSelect = document.getElementById('nav_week');

    weekSelect.innerHTML = '<option value="0">Caricamento...</option>';

    try {
        const response = await fetch(navAjaxUrl + '?action=get_weeks&cohortid=' + cohortId + '&color=' + color + '&sesskey=' + navSessionKey);
        const data = await response.json();

        if (data.success && data.data.length > 0) {
            weekSelect.innerHTML = '<option value="0">Tutte le settimane</option>';
            data.data.forEach(week => {
                const opt = document.createElement('option');
                opt.value = week.calendar_week;
                opt.textContent = week.label + ' (' + week.student_count + ')';
                weekSelect.appendChild(opt);
            });
        } else {
            weekSelect.innerHTML = '<option value="0">Nessuna settimana</option>';
        }
    } catch (e) {
        console.error('Errore caricamento settimane:', e);
        weekSelect.innerHTML = '<option value="0">Errore</option>';
    }

    loadStudents();
}

// Carica studenti filtrati
async function loadStudents() {
    const cohortId = document.getElementById('nav_cohort').value;
    const color = document.getElementById('nav_color').value;
    const week = document.getElementById('nav_week').value;
    const studentSelect = document.getElementById('nav_student');
    const countEl = document.getElementById('student_count');

    studentSelect.innerHTML = '<option value="">Caricamento...</option>';

    try {
        const url = navAjaxUrl + '?action=get_students&cohortid=' + cohortId +
                    '&color=' + encodeURIComponent(color) +
                    '&calendar_week=' + week +
                    '&sesskey=' + navSessionKey;
        const response = await fetch(url);
        const data = await response.json();

        if (data.success && data.data.length > 0) {
            studentSelect.innerHTML = '';
            data.data.forEach(student => {
                const opt = document.createElement('option');
                opt.value = student.id;
                let label = student.fullname;
                if (student.group_color) {
                    label += ' [' + student.group_color.charAt(0).toUpperCase() + student.group_color.slice(1) + ']';
                }
                if (student.current_week > 0) {
                    label += ' - Sett.' + student.current_week;
                }
                opt.textContent = label;
                // Seleziona lo studente corrente se presente
                if (student.id == currentUserId) {
                    opt.selected = true;
                }
                studentSelect.appendChild(opt);
            });

            countEl.textContent = data.count;
        } else {
            studentSelect.innerHTML = '<option value="">Nessuno studente trovato</option>';
            countEl.textContent = '0';
        }
    } catch (e) {
        console.error('Errore caricamento studenti:', e);
        studentSelect.innerHTML = '<option value="">Errore</option>';
        countEl.textContent = '0';
    }

    updateGoButton();
}

// Aggiorna stato pulsante "Vai"
function updateGoButton() {
    const studentId = document.getElementById('nav_student').value;
    const btn = document.getElementById('btn_go');

    btn.disabled = !studentId || studentId == currentUserId;

    if (studentId && studentId != currentUserId) {
        btn.textContent = '📊 Vai al Report';
    } else if (studentId == currentUserId) {
        btn.textContent = '✓ Studente corrente';
    } else {
        btn.textContent = '📊 Seleziona studente';
    }
}

// Naviga al report dello studente selezionato
function goToStudent() {
    const studentId = document.getElementById('nav_student').value;
    if (!studentId) return;

    // Mantieni i parametri attuali
    const url = new URL(window.location.href);
    url.searchParams.set('userid', studentId);
    url.searchParams.set('courseid', currentCourseid);

    window.location.href = url.toString();
}

// Inizializza al caricamento
document.addEventListener('DOMContentLoaded', function() {
    updateGoButton();
});

// ============================================
// FINE NAVIGAZIONE STUDENTE
// ============================================

// Dati per i grafici
const areasData = <?php echo json_encode(array_values($areasData)); ?>;
const competenciesData = <?php echo json_encode(array_values($competencies)); ?>;
const competencyDescriptions = <?php echo json_encode($competencyDescriptions); ?>;
const areaDescriptions = <?php echo json_encode($areaDescriptions); ?>;

// Mappa area key -> competenze (usa i dati già raggruppati dal PHP)
// IMPORTANTE: Usa area.key (es. AUTOMOBILE_A) come identificatore univoco, non area.code (es. A)
// Questo evita conflitti tra aree con stessa lettera di settori diversi
const areaToCompetencies = {};
areasData.forEach(area => {
    // Le competenze sono già raggruppate correttamente dalla funzione aggregate_by_area() in PHP
    // Ogni area ha un array 'competencies' con tutte le sue competenze
    areaToCompetencies[area.key] = area.competencies || [];
});

// Debug: mostra struttura aree per verifica
console.log('📊 Aree caricate:', areasData.length);
areasData.forEach(a => {
    const compCount = (a.competencies || []).length;
    console.log(`   ${a.icon} ${a.name} (key=${a.key}, code=${a.code}): ${compCount} competenze, ${a.percentage}%`);
});

// Stato delle aree visibili (usa key come identificatore)
let visibleAreas = {};
areasData.forEach(a => visibleAreas[a.key] = true);

// Stato drill-down
let isDrillDown = false;
let currentDrillArea = null;

// Chart radar principale
let radarChart = null;

<?php if ($tab === 'overview' && !empty($areasData)): ?>
// Inizializza radar (vista aree)
function initRadarChart() {
    const ctx = document.getElementById('radarAreas');
    if (!ctx) return;

    const visibleData = areasData.filter(a => visibleAreas[a.key]);

    radarChart = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: visibleData.map(a => a.icon + ' ' + a.name),
            datasets: [{
                label: 'Percentuale',
                data: visibleData.map(a => a.percentage),
                backgroundColor: 'rgba(102, 126, 234, 0.2)',
                borderColor: 'rgba(102, 126, 234, 1)',
                borderWidth: 2,
                pointBackgroundColor: visibleData.map(a => a.color),
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            onClick: handleRadarClick,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        stepSize: 20,
                        callback: v => v + '%'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (isDrillDown) {
                                // Mostra nome competenza in drill-down
                                const comps = areaToCompetencies[currentDrillArea] || [];
                                const comp = comps[context.dataIndex];
                                if (comp) {
                                    const desc = competencyDescriptions[comp.idnumber];
                                    const name = desc ? (desc.name || comp.idnumber) : comp.idnumber;
                                    return name + ': ' + comp.percentage + '%';
                                }
                            } else {
                                // Mostra nome area
                                const visibleData = areasData.filter(a => visibleAreas[a.key]);
                                const area = visibleData[context.dataIndex];
                                if (area) {
                                    return area.name + ': ' + area.percentage + '% (' + area.correct_questions + '/' + area.total_questions + ')';
                                }
                            }
                            return '';
                        }
                    }
                }
            }
        }
    });
}

// Gestisce click sul radar
function handleRadarClick(event, elements) {
    if (isDrillDown) return; // Già in drill-down, non fare nulla

    if (elements && elements.length > 0) {
        const index = elements[0].index;
        const visibleData = areasData.filter(a => visibleAreas[a.key]);
        const clickedArea = visibleData[index];
        if (clickedArea) {
            drillDownToArea(clickedArea.key);
        }
    }
}

// Drill-down: mostra competenze di un'area specifica
// NOTA: areaKey è ora SETTORE_LETTERA (es. AUTOMOBILE_A) invece di sola lettera
function drillDownToArea(areaKey) {
    const area = areasData.find(a => a.key === areaKey);
    if (!area) {
        console.error('Area non trovata:', areaKey);
        return;
    }

    // Usa le competenze già raggruppate dal PHP (area.competencies)
    const comps = area.competencies || areaToCompetencies[areaKey] || [];

    console.log(`🔍 Drill-down area ${areaKey}:`, {
        areaName: area.name,
        competencies: comps.length,
        firstComp: comps[0] || 'nessuna'
    });

    if (comps.length === 0) {
        alert('Nessuna competenza trovata per l\'area: ' + area.name + '\n\nVerifica che ci siano quiz completati per questa area.');
        return;
    }

    // Aggiorna stato
    isDrillDown = true;
    currentDrillArea = areaKey;

    // Aggiorna UI
    document.getElementById('radarTitle').innerHTML = area.icon + ' Competenze Area: ' + area.name;
    document.getElementById('radarSubtitle').style.display = 'none';
    document.getElementById('btnBackToAreas').style.display = 'inline-block';
    document.getElementById('areaToggleControls').style.display = 'none';
    document.getElementById('drillDownInfo').style.display = 'block';
    document.getElementById('drillAreaName').innerHTML = area.icon + ' ' + area.name + ' <span class="badge badge-secondary">' + area.percentage + '%</span>';
    document.getElementById('drillCompCount').textContent = comps.length + ' competenze';
    document.getElementById('competencyLegend').style.display = 'block';

    // Sincronizza dropdown
    const dropdown = document.getElementById('areaDetailSelect');
    if (dropdown) dropdown.value = areaKey;

    // Genera lista competenze con descrizioni complete
    // Descrizione SOPRA, codice SOTTO
    let listHtml = '<div class="list-group list-group-flush">';
    comps.forEach((c, idx) => {
        const desc = competencyDescriptions[c.idnumber];
        // Usa full_name per la descrizione completa
        const fullName = desc ? (desc.full_name || desc.description || c.idnumber) : c.idnumber;
        // Se fullName è diverso da idnumber, mostra entrambi; altrimenti solo idnumber
        const hasDescription = fullName && fullName !== c.idnumber;
        const color = c.percentage >= 80 ? '#28a745' : (c.percentage >= 60 ? '#20c997' : (c.percentage >= 40 ? '#ffc107' : '#dc3545'));

        if (hasDescription) {
            listHtml += `<div class="list-group-item py-2 d-flex justify-content-between align-items-center">
                <div style="flex: 1; min-width: 0;">
                    <span style="font-size: 0.9em;"><strong>${idx + 1}.</strong> ${fullName}</span><br>
                    <small class="text-muted">Rif: ${c.idnumber}</small>
                </div>
                <span class="badge ml-2" style="background: ${color}; color: white; white-space: nowrap;">${c.percentage}%</span>
            </div>`;
        } else {
            listHtml += `<div class="list-group-item py-2 d-flex justify-content-between align-items-center">
                <div style="flex: 1; min-width: 0;">
                    <span style="font-size: 0.9em;"><strong>${idx + 1}.</strong> ${c.idnumber}</span>
                </div>
                <span class="badge ml-2" style="background: ${color}; color: white; white-space: nowrap;">${c.percentage}%</span>
            </div>`;
        }
    });
    listHtml += '</div>';
    document.getElementById('competencyList').innerHTML = listHtml;

    // Aggiorna radar con competenze
    updateRadarForCompetencies(comps, area);

    // Aggiorna dettaglio area (panel destro)
    updateAreaDetailContent(areaKey, comps);
}

// Aggiorna radar per mostrare competenze
function updateRadarForCompetencies(comps, area) {
    if (!radarChart) return;

    // Prepara labels e dati
    const labels = comps.map((c, idx) => {
        const desc = competencyDescriptions[c.idnumber];
        const shortName = desc ? (desc.shortname || desc.name || c.idnumber) : c.idnumber;
        // Abbrevia se troppo lungo
        return shortName.length > 15 ? shortName.substring(0, 12) + '...' : shortName;
    });

    const data = comps.map(c => c.percentage);
    const colors = comps.map(c => {
        if (c.percentage >= 80) return '#28a745';
        if (c.percentage >= 60) return '#20c997';
        if (c.percentage >= 40) return '#ffc107';
        return '#dc3545';
    });

    // Aggiorna chart
    radarChart.data.labels = labels;
    radarChart.data.datasets[0].data = data;
    radarChart.data.datasets[0].pointBackgroundColor = colors;
    radarChart.data.datasets[0].backgroundColor = area.color + '33'; // 20% opacity
    radarChart.data.datasets[0].borderColor = area.color;
    radarChart.data.datasets[0].label = area.name;

    // Rimuovi onClick in drill-down
    radarChart.options.onClick = null;

    radarChart.update();
}

// Torna alla vista aree
function backToAreasView() {
    if (!isDrillDown) return;

    // Reset stato
    isDrillDown = false;
    currentDrillArea = null;

    // Reset UI
    document.getElementById('radarTitle').innerHTML = '📊 Radar Aree di Competenza';
    document.getElementById('radarSubtitle').style.display = 'inline';
    document.getElementById('btnBackToAreas').style.display = 'none';
    document.getElementById('areaToggleControls').style.display = 'block';
    document.getElementById('drillDownInfo').style.display = 'none';
    document.getElementById('competencyLegend').style.display = 'none';

    // Reset dropdown
    const dropdown = document.getElementById('areaDetailSelect');
    if (dropdown) dropdown.value = '';

    // Reset dettaglio area
    document.getElementById('areaDetailContent').innerHTML = '<p class="text-muted text-center">Seleziona un\'area per vedere il dettaglio delle competenze</p>';

    // Ricostruisci radar aree
    rebuildAreasRadar();
}

// Ricostruisce radar con vista aree
function rebuildAreasRadar() {
    if (!radarChart) return;

    const visibleData = areasData.filter(a => visibleAreas[a.key]);

    radarChart.data.labels = visibleData.map(a => a.icon + ' ' + a.name);
    radarChart.data.datasets[0].data = visibleData.map(a => a.percentage);
    radarChart.data.datasets[0].pointBackgroundColor = visibleData.map(a => a.color);
    radarChart.data.datasets[0].backgroundColor = 'rgba(102, 126, 234, 0.2)';
    radarChart.data.datasets[0].borderColor = 'rgba(102, 126, 234, 1)';
    radarChart.data.datasets[0].label = 'Percentuale';

    // Ripristina onClick
    radarChart.options.onClick = handleRadarClick;

    radarChart.update();
}

// Toggle area (solo in vista aree) - usa areaKey come identificatore
function toggleArea(areaKey) {
    if (isDrillDown) return;

    visibleAreas[areaKey] = !visibleAreas[areaKey];

    const toggle = document.querySelector(`.area-toggle[data-area="${areaKey}"]`);
    if (toggle) {
        toggle.classList.toggle('disabled', !visibleAreas[areaKey]);
    }

    updateRadarChart();
}

// Toggle tutte le aree
function toggleAllAreas(show) {
    if (isDrillDown) return;

    Object.keys(visibleAreas).forEach(key => {
        visibleAreas[key] = show;
        const toggle = document.querySelector(`.area-toggle[data-area="${key}"]`);
        if (toggle) {
            toggle.classList.toggle('disabled', !show);
        }
    });
    updateRadarChart();
}

// Aggiorna radar (solo vista aree)
function updateRadarChart() {
    if (!radarChart || isDrillDown) return;

    const visibleData = areasData.filter(a => visibleAreas[a.key]);

    radarChart.data.labels = visibleData.map(a => a.icon + ' ' + a.name);
    radarChart.data.datasets[0].data = visibleData.map(a => a.percentage);
    radarChart.data.datasets[0].pointBackgroundColor = visibleData.map(a => a.color);
    radarChart.update();
}

// Aggiorna contenuto dettaglio area (panel destro) con descrizioni complete
// Descrizione SOPRA, codice SOTTO
function updateAreaDetailContent(areaCode, comps) {
    const container = document.getElementById('areaDetailContent');
    if (!container) return;

    if (!comps || comps.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">Nessuna competenza per questa area</p>';
        return;
    }

    let html = '<table class="table table-sm table-hover">';
    html += '<thead class="thead-light"><tr><th style="width: 30px;">#</th><th>COMPETENZA</th><th style="width: 60px;" class="text-right">%</th></tr></thead><tbody>';

    comps.forEach((c, idx) => {
        const desc = competencyDescriptions[c.idnumber];
        // Usa full_name per la descrizione completa
        const fullName = desc ? (desc.full_name || desc.description || c.idnumber) : c.idnumber;
        // Se fullName è diverso da idnumber, mostra entrambi
        const hasDescription = fullName && fullName !== c.idnumber;
        const color = c.percentage >= 80 ? '#28a745' : (c.percentage >= 60 ? '#20c997' : (c.percentage >= 40 ? '#ffc107' : '#dc3545'));

        if (hasDescription) {
            html += `<tr>
                <td><small class="text-muted">${idx + 1}</small></td>
                <td>
                    <span style="font-size: 0.9em;">${fullName}</span><br>
                    <small class="text-muted">Rif: ${c.idnumber}</small>
                </td>
                <td class="text-right"><span class="badge" style="background: ${color}; color: white;">${c.percentage}%</span></td>
            </tr>`;
        } else {
            html += `<tr>
                <td><small class="text-muted">${idx + 1}</small></td>
                <td><span style="font-size: 0.9em;">${c.idnumber}</span></td>
                <td class="text-right"><span class="badge" style="background: ${color}; color: white;">${c.percentage}%</span></td>
            </tr>`;
        }
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}

// Event listener per dropdown Dettaglio Area -> trigger drill-down
document.getElementById('areaDetailSelect')?.addEventListener('change', function() {
    const areaCode = this.value;

    if (!areaCode) {
        if (isDrillDown) {
            backToAreasView();
        } else {
            document.getElementById('areaDetailContent').innerHTML = '<p class="text-muted text-center">Seleziona un\'area per vedere il dettaglio</p>';
        }
        return;
    }

    // Trigger drill-down
    drillDownToArea(areaCode);
});

// Inizializza
initRadarChart();

// ============================================
// RADAR ADDITIVI (CoachManager)
// ============================================
<?php if ($showDualRadar && !empty($autovalutazioneAreas)): ?>
const autovalutazioneAreas = <?php echo json_encode(array_values($autovalutazioneAreas)); ?>;

// ============================================
// SINCRONIZZAZIONE LABELS PER CONFRONTO RADAR
// Entrambi i radar usano lo STESSO ordine di labels
// ============================================

// Crea un ordine unificato basato su autovalutazioneAreas
const unifiedLabels = autovalutazioneAreas.map(a => ({
    key: a.key || a.name,
    label: a.icon + ' ' + a.name,
    icon: a.icon,
    name: a.name
}));

// Funzione per trovare il valore di una competenza per chiave
function findValueByKey(dataArray, key, fallback = 0) {
    const found = dataArray.find(item => (item.key || item.name) === key);
    return found ? found.percentage : fallback;
}

// Funzione per abbreviare i labels (OPZIONE C + D)
function abbreviateLabel(label, maxLength = 28) {
    // Estrai la lettera/codice iniziale (es. "A.", "B.", "📁 A.")
    const match = label.match(/^([📁🔧⚙️🛠️💡🔌📊🎯]*\s*[A-Z]\.?\s*)/i);
    const prefix = match ? match[1] : '';
    const rest = label.substring(prefix.length);

    // Calcola spazio disponibile per il nome
    const availableLength = maxLength - prefix.length;

    if (rest.length <= availableLength) {
        return label;
    }

    // Abbrevia il nome
    return prefix + rest.substring(0, availableLength - 3) + '...';
}

// Prepara dati sincronizzati
const syncedAutoData = unifiedLabels.map(l => findValueByKey(autovalutazioneAreas, l.key));
const syncedPerfData = unifiedLabels.map(l => findValueByKey(areasData, l.key));
// Labels abbreviate per evitare troncamento
const syncedLabels = unifiedLabels.map(l => abbreviateLabel(l.label, 28));

// Opzioni comuni per entrambi i radar (OPZIONE C + D: font piccolo, più padding)
const radarOptions = {
    responsive: true,
    maintainAspectRatio: false,  // Permette controllo altezza
    scales: {
        r: {
            beginAtZero: true,
            max: 100,
            ticks: {
                stepSize: 20,
                font: { size: 10 },
                callback: function(value) { return value + '%'; },
                backdropColor: 'rgba(255, 255, 255, 0.8)'
            },
            pointLabels: {
                font: { size: 10, weight: '500' },  // Font più piccolo
                padding: 20,  // Più spazio tra label e grafico
                centerPointLabels: false
            },
            grid: {
                color: 'rgba(0, 0, 0, 0.08)'
            },
            angleLines: {
                color: 'rgba(0, 0, 0, 0.08)'
            }
        }
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: function(context) {
                    // Mostra nome completo nel tooltip
                    const fullName = unifiedLabels[context.dataIndex]?.name || '';
                    return fullName + ': ' + context.raw.toFixed(1) + '%';
                }
            }
        }
    },
    layout: {
        padding: {
            top: 20,
            bottom: 20,
            left: 30,
            right: 30
        }
    }
};

// Radar Autovalutazione (SOPRA)
const ctxAuto = document.getElementById('radarAutovalutazione');
if (ctxAuto && syncedLabels.length > 0) {
    new Chart(ctxAuto, {
        type: 'radar',
        data: {
            labels: syncedLabels,
            datasets: [{
                label: 'Autovalutazione',
                data: syncedAutoData,
                backgroundColor: 'rgba(102, 126, 234, 0.25)',
                borderColor: 'rgba(102, 126, 234, 1)',
                borderWidth: 3,
                pointBackgroundColor: '#667eea',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: radarOptions
    });
}

// Radar Performance (SOTTO) - USA GLI STESSI LABELS!
const ctxPerfDual = document.getElementById('radarPerformanceDual');
if (ctxPerfDual && syncedLabels.length > 0) {
    new Chart(ctxPerfDual, {
        type: 'radar',
        data: {
            labels: syncedLabels,  // STESSO ordine di labels!
            datasets: [{
                label: 'Performance',
                data: syncedPerfData,
                backgroundColor: 'rgba(40, 167, 69, 0.25)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 3,
                pointBackgroundColor: '#28a745',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: radarOptions
    });
}

console.log('Radar sincronizzati - Labels:', syncedLabels.length, 'Auto:', syncedAutoData, 'Perf:', syncedPerfData);
<?php endif; ?>
// ============================================

<?php endif; ?>

<?php if ($tab === 'progress' && !empty($progressData)): ?>
// Grafico progressi
const ctxProgress = document.getElementById('progressChart');
if (ctxProgress) {
    new Chart(ctxProgress, {
        type: 'line',
        data: {
            labels: [<?php echo implode(',', array_map(function($p) { return "'" . ($p['date'] ?? date('d/m', $p['datetime'] ?? time())) . "'"; }, $progressData)); ?>],
            datasets: [{
                label: 'Punteggio %',
                data: [<?php echo implode(',', array_map(function($p) { return $p['percentage'] ?? 0; }, $progressData)); ?>],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { callback: v => v + '%' }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: ctx => 'Punteggio: ' + ctx.parsed.y + '%'
                    }
                }
            }
        }
    });
}
<?php endif; ?>
</script>

<?php
// ========================================
// PREPARAZIONE DATI PER MODAL
// ========================================

// Mappa icone e colori per il modal (duplicata per garantire disponibilità)
$modal_area_icons = [
    'A' => '📋', 'B' => '🔧', 'C' => '🛢️', 'D' => '💨', 'E' => '⚙️',
    'F' => '🛞', 'G' => '💻', 'H' => '📡', 'I' => '❄️', 'J' => '🔋',
    'K' => '🚗', 'L' => '🛡️', 'M' => '👤', 'N' => '✅',
    'LMB' => '🔧', 'LMC' => '⚙️', 'CNC' => '🖥️', 'ASS' => '🔩',
    'MIS' => '📏', 'GEN' => '🏭', 'MAN' => '🔨', 'DT' => '📐',
    'AUT' => '🤖', 'PIAN' => '📋', 'SAQ' => '🛡️', 'CSP' => '🤝', 'PRG' => '💡',
    'LAV' => '🏭', 'MAT' => '🧱', 'CQ' => '✅', 'AREA' => '📦',
    '1C' => '📜', '1G' => '📦', '1O' => '⚗️', '2M' => '📏',
    '3C' => '🔬', '4S' => '🛡️', '5S' => '🧫', '6P' => '🏭',
    '7S' => '🔧', '8T' => '💻', '9A' => '📊',
    'MAu' => '🚗', 'MR' => '🔧',
];

$modal_area_colors = [
    'A' => '#3498db', 'B' => '#e74c3c', 'C' => '#f39c12', 'D' => '#9b59b6',
    'E' => '#1abc9c', 'F' => '#e67e22', 'G' => '#2ecc71', 'H' => '#00bcd4',
    'I' => '#3f51b5', 'J' => '#ff5722', 'K' => '#795548', 'L' => '#607d8b',
    'M' => '#8bc34a', 'N' => '#009688',
    'LMB' => '#795548', 'LMC' => '#607d8b', 'CNC' => '#00bcd4', 'ASS' => '#f39c12',
    'MIS' => '#1abc9c', 'GEN' => '#9e9e9e', 'MAN' => '#e67e22', 'DT' => '#3498db',
    'AUT' => '#e74c3c', 'PIAN' => '#9b59b6', 'SAQ' => '#c0392b', 'CSP' => '#8e44ad', 'PRG' => '#2ecc71',
    'LAV' => '#9e9e9e', 'MAT' => '#795548', 'CQ' => '#27ae60', 'AREA' => '#7f8c8d',
    '1C' => '#3498db', '1G' => '#e67e22', '1O' => '#9b59b6', '2M' => '#1abc9c',
    '3C' => '#2ecc71', '4S' => '#e74c3c', '5S' => '#00bcd4', '6P' => '#f39c12',
    '7S' => '#607d8b', '8T' => '#3f51b5', '9A' => '#8bc34a',
    'MAu' => '#3498db', 'MR' => '#e74c3c',
];

// Helper per icone modal
function get_modal_icon_info($areaKey, $icons, $colors) {
    $parts = explode('_', $areaKey);
    $area_code = $parts[1] ?? $areaKey;
    return [
        'icona' => $icons[$area_code] ?? '📁',
        'colore' => $colors[$area_code] ?? '#95a5a6'
    ];
}

// Livelli Bloom per autovalutazione
function get_bloom_levels_modal() {
    return [
        1 => ['nome' => 'RICORDO', 'descrizione' => 'Riesco a ricordare le informazioni base', 'colore' => '#e74c3c'],
        2 => ['nome' => 'COMPRENDO', 'descrizione' => 'Comprendo i concetti fondamentali', 'colore' => '#e67e22'],
        3 => ['nome' => 'APPLICO', 'descrizione' => 'So applicare le conoscenze in situazioni note', 'colore' => '#f1c40f'],
        4 => ['nome' => 'ANALIZZO', 'descrizione' => 'Riesco ad analizzare problemi complessi', 'colore' => '#27ae60'],
        5 => ['nome' => 'VALUTO', 'descrizione' => 'Posso valutare situazioni complesse e proporre soluzioni', 'colore' => '#3498db'],
        6 => ['nome' => 'CREO', 'descrizione' => 'Sono in grado di creare soluzioni innovative', 'colore' => '#9b59b6']
    ];
}

// Carica competenze organizzate per area per il modal
$modal_areas_data = [];

if (true) { // Carica sempre i dati per il modal
    // Carica competenze dal framework
    $all_comps = $DB->get_records_sql("
        SELECT c.id, c.shortname, c.description, c.idnumber
        FROM {competency} c
        JOIN {competency_framework} cf ON c.competencyframeworkid = cf.id
        WHERE cf.shortname LIKE '%FTM%' OR cf.shortname LIKE '%Meccanica%'
        ORDER BY c.idnumber
    ");

    // Carica quiz results per questo studente con dettagli
    // Usa qa.id come prima colonna per evitare duplicati (questionid può ripetersi in quiz diversi)
    $quiz_details = $DB->get_records_sql("
        SELECT
            qa.id as qa_id,
            qa.questionid,
            qas.fraction,
            q.name as question_name,
            quiz.name as quiz_name,
            quiza.id as attempt_id,
            quiza.timefinish
        FROM {quiz_attempts} quiza
        JOIN {quiz} quiz ON quiz.id = quiza.quiz
        JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
        JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
        JOIN {question} q ON q.id = qa.questionid
        WHERE quiza.userid = ?
        AND quiza.state = 'finished'
        AND qas.sequencenumber = (
            SELECT MAX(qas2.sequencenumber)
            FROM {question_attempt_steps} qas2
            WHERE qas2.questionattemptid = qa.id
        )
    ", [$userid]);

    // Mappa domande -> competenze
    $q_to_comp = [];
    if ($DB->get_manager()->table_exists('qbank_competenciesbyquestion')) {
        $qmaps = $DB->get_records('qbank_competenciesbyquestion');
        foreach ($qmaps as $qm) {
            $q_to_comp[$qm->questionid][] = $qm->competencyid;
        }
    }

    // Carica autovalutazioni (stesso ordine di reports_v2.php)
    $autoval_table = null;
    if ($DB->get_manager()->table_exists('local_autovalutazione')) {
        $autoval_table = 'local_autovalutazione';
    } elseif ($DB->get_manager()->table_exists('local_selfassessment')) {
        $autoval_table = 'local_selfassessment';
    }

    $autoval_by_comp = [];
    $bloom_levels = get_bloom_levels_modal();

    // Debug info per autovalutazione
    $autoval_debug = ['table' => 'nessuna', 'count' => 0, 'userid' => $userid, 'comp_ids' => [], 'all_comp_ids' => []];

    // Raccogli tutti gli ID delle competenze caricate
    foreach ($all_comps as $c) {
        $autoval_debug['all_comp_ids'][] = $c->id;
    }

    if ($autoval_table) {
        $autovalutazioni = $DB->get_records($autoval_table, ['userid' => $userid]);
        $autoval_debug['table'] = $autoval_table;
        $autoval_debug['count'] = count($autovalutazioni);

        foreach ($autovalutazioni as $av) {
            if (isset($av->competencyid)) {
                $autoval_by_comp[$av->competencyid] = $av;
                $autoval_debug['comp_ids'][] = $av->competencyid;
            }
        }

        // Trova quante corrispondenze ci sono
        $autoval_debug['matches'] = count(array_intersect($autoval_debug['comp_ids'], $autoval_debug['all_comp_ids']));
    }

    // Organizza competenze per area
    foreach ($all_comps as $comp) {
        $aInfo = get_area_info($comp->idnumber);
        $aKey = $aInfo['key'];

        if (!isset($modal_areas_data[$aKey])) {
            $iconI = get_modal_icon_info($aKey, $modal_area_icons, $modal_area_colors);
            $kParts = explode('_', $aKey);
            $modal_areas_data[$aKey] = [
                'key' => $aKey,
                'name' => $aInfo['name'] ?? ($kParts[1] ?? $aKey),
                'icon' => $iconI['icona'],
                'color' => $iconI['colore'],
                'sector' => strtolower($kParts[0] ?? 'altro'),
                'competencies' => []
            ];
        }

        // Trova quiz per questa competenza
        $comp_quizzes = [];
        foreach ($quiz_details as $qd) {
            if (isset($q_to_comp[$qd->questionid])) {
                foreach ($q_to_comp[$qd->questionid] as $cid) {
                    if ($cid == $comp->id) {
                        $comp_quizzes[] = [
                            'quiz_name' => $qd->quiz_name,
                            'score' => round(($qd->fraction ?? 0) * 100),
                            'attempt_id' => $qd->attempt_id,
                            'date' => $qd->timefinish ? date('d/m/Y', $qd->timefinish) : '-'
                        ];
                    }
                }
            }
        }

        // Calcola media quiz per competenza
        $quiz_sum = 0;
        $quiz_cnt = count($comp_quizzes);
        foreach ($comp_quizzes as $cq) {
            $quiz_sum += $cq['score'];
        }
        $quiz_media = $quiz_cnt > 0 ? round($quiz_sum / $quiz_cnt) : 0;

        // Dati autovalutazione per competenza
        $autoval_data = null;
        if (isset($autoval_by_comp[$comp->id])) {
            $av = $autoval_by_comp[$comp->id];
            $level = isset($av->level) ? intval($av->level) : 0;
            $percentage = round(($level / 6) * 100);
            $bloom = $bloom_levels[$level] ?? ['nome' => 'N/D', 'descrizione' => '', 'colore' => '#ccc'];

            $autoval_data = [
                'level' => $level,
                'percentage' => $percentage,
                'bloom_nome' => $bloom['nome'],
                'bloom_desc' => $bloom['descrizione'],
                'bloom_colore' => $bloom['colore']
            ];
        }

        // Calcola gap se entrambi i dati sono presenti
        $gap = null;
        if ($autoval_data && $quiz_cnt > 0) {
            $gap = $autoval_data['percentage'] - $quiz_media;
        }

        $modal_areas_data[$aKey]['competencies'][] = [
            'id' => $comp->id,
            'shortname' => $comp->shortname,
            'description' => $comp->description,
            'idnumber' => $comp->idnumber,
            'quiz_media' => $quiz_media,
            'quiz_count' => $quiz_cnt,
            'quizzes' => $comp_quizzes,
            'autoval' => $autoval_data,
            'gap' => $gap
        ];
    }

    // Calcola statistiche per area
    $autoval_debug['areas_with_autoval'] = [];
    foreach ($modal_areas_data as $aKey => &$aData) {
        $total_quiz = 0;
        $sum_quiz = 0;
        $total_autoval = 0;
        $sum_autoval = 0;
        foreach ($aData['competencies'] as $c) {
            if ($c['quiz_count'] > 0) {
                $total_quiz++;
                $sum_quiz += $c['quiz_media'];
            }
            if ($c['autoval'] !== null) {
                $total_autoval++;
                $sum_autoval += $c['autoval']['percentage'];
            }
        }
        $aData['quiz_media'] = $total_quiz > 0 ? round($sum_quiz / $total_quiz) : 0;
        $aData['autoval_media'] = $total_autoval > 0 ? round($sum_autoval / $total_autoval) : null;
        $aData['total'] = count($aData['competencies']);
        $aData['autoval_count'] = $total_autoval;

        // Debug: traccia aree con autovalutazione
        if ($total_autoval > 0) {
            $autoval_debug['areas_with_autoval'][] = $aKey . ' (' . $total_autoval . ')';
        }
    }
    unset($aData);
}
?>

<!-- ============================================
     MODAL DETTAGLIO AREA
     ============================================ -->
<div class="modal-overlay" id="areaModal" onclick="closeAreaModal(event)">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header" id="modalHeader">
            <h2 id="modalTitle">Dettaglio Area</h2>
            <button class="modal-close" onclick="closeAreaModal()">×</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Contenuto dinamico -->
        </div>
    </div>
</div>

<script>
// Dati aree per il modal
const modalAreasData = <?php echo json_encode($modal_areas_data); ?>;
// Debug autovalutazione
const autovalDebug = <?php echo json_encode($autoval_debug); ?>;
console.log('DEBUG Autovalutazione:', autovalDebug);

function openAreaModal(areaKey) {
    const area = modalAreasData[areaKey];
    if (!area) {
        console.error('Area non trovata:', areaKey);
        return;
    }

    document.getElementById('modalTitle').innerHTML = area.icon + ' ' + area.name;
    document.getElementById('modalHeader').style.background = 'linear-gradient(135deg, ' + area.color + ', ' + area.color + 'cc)';

    let bodyHtml = '';

    // DEBUG: mostra info autovalutazione (rimuovere in produzione)
    bodyHtml += '<div style="background: #fff3cd; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.85rem;">';
    bodyHtml += '⚙️ DEBUG: Tabella=' + autovalDebug.table + ', Autoval=' + autovalDebug.count + ', UserID=' + autovalDebug.userid + '<br>';
    bodyHtml += '📊 Competenze framework=' + (autovalDebug.all_comp_ids ? autovalDebug.all_comp_ids.length : 0);
    bodyHtml += ', MATCHES=' + (autovalDebug.matches || 0) + '<br>';
    bodyHtml += '✅ <b>AREE CON AUTOVAL:</b> ' + (autovalDebug.areas_with_autoval ? autovalDebug.areas_with_autoval.join(', ') : 'nessuna');
    bodyHtml += '<br>📍 <b>QUESTA AREA:</b> ' + areaKey + ' (autoval: ' + (area.autoval_count || 0) + ')';
    bodyHtml += '</div>';

    // Header con statistiche area
    bodyHtml += '<p style="margin-bottom: 15px; color: #6c757d;">' + area.total + ' competenze - Settore: ' + area.sector.toUpperCase() + '</p>';

    // Stats area
    bodyHtml += '<div style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap;">';
    bodyHtml += '<div class="critical-value-box"><div class="label">Quiz Media</div><div class="value" style="color: ' + getScoreColor(area.quiz_media) + ';">' + area.quiz_media + '%</div></div>';
    if (area.autoval_media !== null) {
        bodyHtml += '<div class="critical-value-box"><div class="label">Autovalutazione</div><div class="value" style="color: ' + getScoreColor(area.autoval_media) + ';">' + area.autoval_media + '%</div></div>';
    }
    bodyHtml += '</div>';

    // Lista competenze
    bodyHtml += '<h4 style="margin-bottom: 15px;">📋 Competenze in quest\'area:</h4>';

    if (area.competencies && area.competencies.length > 0) {
        area.competencies.forEach((comp, idx) => {
            const compId = 'comp_' + areaKey.replace(/[^a-zA-Z0-9]/g, '_') + '_' + idx;
            const hasQuiz = comp.quizzes && comp.quizzes.length > 0;
            const quizMedia = comp.quiz_media || 0;

            bodyHtml += '<div class="competency-item" id="' + compId + '">';

            // HEADER COMPETENZA
            bodyHtml += '<div class="competency-header" onclick="toggleCompetency(\'' + compId + '\')">';
            bodyHtml += '<div class="competency-info">';
            // Nome completo (description) sopra, codice (idnumber) sotto
            const fullName = comp.description || comp.shortname || comp.idnumber;
            bodyHtml += '<div class="competency-name">' + fullName + '</div>';
            bodyHtml += '<div class="competency-code" style="font-size: 0.8rem; color: #6c757d; margin-top: 2px;">' + comp.idnumber + '</div>';
            bodyHtml += '</div>';

            // Values boxes
            bodyHtml += '<div class="competency-values">';
            if (hasQuiz) {
                bodyHtml += '<div class="value-box"><div class="label">Quiz</div><div class="value" style="color: ' + getScoreColor(quizMedia) + ';">' + quizMedia + '%</div></div>';
            } else {
                bodyHtml += '<div class="value-box"><div class="label">Quiz</div><div class="value" style="color: #6c757d;">-</div></div>';
            }
            // Autovalutazione box
            if (comp.autoval) {
                bodyHtml += '<div class="value-box"><div class="label">Autoval.</div><div class="value" style="color: ' + getScoreColor(comp.autoval.percentage) + ';">' + comp.autoval.percentage + '%</div></div>';
            }
            bodyHtml += '</div>';

            bodyHtml += '<div class="competency-toggle">▼</div>';
            bodyHtml += '</div>';

            // DETTAGLI COMPETENZA (espandibile)
            bodyHtml += '<div class="competency-details">';

            // Sezione Autovalutazione (se presente)
            if (comp.autoval) {
                bodyHtml += '<div class="detail-section autoval-section" style="background: linear-gradient(135deg, ' + comp.autoval.bloom_colore + '15, transparent); border-left: 4px solid ' + comp.autoval.bloom_colore + ';">';
                bodyHtml += '<div class="detail-section-title">🧑 Autovalutazione</div>';
                bodyHtml += '<div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">';
                bodyHtml += '<span class="bloom-badge" style="background: ' + comp.autoval.bloom_colore + '; color: white; padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 0.9rem;">Livello ' + comp.autoval.level + ' - ' + comp.autoval.bloom_nome + '</span>';
                bodyHtml += '<span style="color: #495057; font-style: italic;">"' + comp.autoval.bloom_desc + '"</span>';
                bodyHtml += '</div>';
                // Gap analysis
                if (comp.gap !== null) {
                    let gapColor = comp.gap > 15 ? '#e74c3c' : (comp.gap < -15 ? '#27ae60' : '#6c757d');
                    let gapIcon = comp.gap > 15 ? '⚠️' : (comp.gap < -15 ? '✅' : '➖');
                    let gapText = comp.gap > 0 ? '+' + comp.gap : comp.gap;
                    bodyHtml += '<div style="margin-top: 12px; padding: 10px; background: rgba(0,0,0,0.03); border-radius: 6px;">';
                    bodyHtml += '<span style="font-weight: 600;">Gap Autoval/Quiz:</span> ';
                    bodyHtml += '<span style="color: ' + gapColor + '; font-weight: 600;">' + gapIcon + ' ' + gapText + '%</span>';
                    if (comp.gap > 15) {
                        bodyHtml += '<span style="margin-left: 10px; font-size: 0.85rem; color: #e74c3c;">Autopercezione superiore ai risultati</span>';
                    } else if (comp.gap < -15) {
                        bodyHtml += '<span style="margin-left: 10px; font-size: 0.85rem; color: #27ae60;">Risultati migliori dell\'autopercezione</span>';
                    }
                    bodyHtml += '</div>';
                }
                bodyHtml += '</div>';
            }

            // Sezione Quiz
            bodyHtml += '<div class="detail-section">';
            bodyHtml += '<div class="detail-section-title">📝 Quiz Tecnici</div>';

            if (hasQuiz) {
                comp.quizzes.forEach(quiz => {
                    let scoreClass = 'good';
                    if (quiz.score < 50) scoreClass = 'bad';
                    else if (quiz.score < 70) scoreClass = 'warning';

                    bodyHtml += '<div class="quiz-item">';
                    bodyHtml += '<div class="quiz-header">';
                    bodyHtml += '<span class="quiz-name">' + quiz.quiz_name + '</span>';
                    bodyHtml += '<span class="quiz-score ' + scoreClass + '">' + quiz.score + '%</span>';
                    bodyHtml += '</div>';
                    bodyHtml += '<div class="quiz-details">';
                    bodyHtml += '<span>📅 ' + quiz.date + '</span>';
                    bodyHtml += '<a href="/mod/quiz/review.php?attempt=' + quiz.attempt_id + '" class="quiz-link" target="_blank">🔍 Vedi tentativo</a>';
                    bodyHtml += '</div>';
                    bodyHtml += '</div>';
                });
            } else {
                bodyHtml += '<div class="no-data-message" style="padding: 15px;">📭 Nessun quiz svolto per questa competenza</div>';
            }
            bodyHtml += '</div>';

            bodyHtml += '</div>'; // competency-details
            bodyHtml += '</div>'; // competency-item
        });
    } else {
        bodyHtml += '<div class="no-data-message" style="padding: 20px;">Nessuna competenza in quest\'area</div>';
    }

    document.getElementById('modalBody').innerHTML = bodyHtml;
    document.getElementById('areaModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeAreaModal(event) {
    if (!event || event.target.classList.contains('modal-overlay')) {
        document.getElementById('areaModal').classList.remove('active');
        document.body.style.overflow = '';
    }
}

function toggleCompetency(compId) {
    const item = document.getElementById(compId);
    if (item) {
        item.classList.toggle('open');
    }
}

function getScoreColor(score) {
    if (score >= 80) return '#28a745';
    if (score >= 60) return '#17a2b8';
    if (score >= 50) return '#ffc107';
    return '#dc3545';
}

// ============================================
// FINAL RATING INLINE EDITOR
// ============================================
let activeFinalRatingDropdown = null;

function showFinalRatingDropdown(element) {
    // Chiudi dropdown precedente
    if (activeFinalRatingDropdown) {
        activeFinalRatingDropdown.remove();
        activeFinalRatingDropdown = null;
    }

    const area = element.dataset.area;
    const method = element.dataset.method;
    const currentValue = parseInt(element.dataset.value);
    const calculatedValue = parseInt(element.dataset.calculated) || 0;
    const isModified = element.dataset.modified === '1';

    // Crea dropdown
    const dropdown = document.createElement('div');
    dropdown.className = 'final-rating-dropdown';
    dropdown.style.cssText = `
        position: absolute;
        z-index: 1050;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        padding: 10px;
        min-width: 180px;
    `;

    // Header con valore calcolato
    let headerHtml = `<div style="font-size: 0.75rem; color: #6c757d; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 5px;">
        Valore calcolato: <strong>${calculatedValue}%</strong>
    </div>`;

    // Input per nuovo valore
    headerHtml += `
        <div style="margin-bottom: 8px;">
            <label style="font-size: 0.75rem; font-weight: bold;">Nuovo valore (0-100):</label>
            <input type="number" id="finalRatingInput" min="0" max="100" value="${currentValue}"
                   style="width: 100%; padding: 5px; border: 1px solid #ced4da; border-radius: 4px; font-size: 1rem;">
        </div>
        <div style="display: flex; gap: 5px;">
            <button onclick="saveFinalRating('${area}', '${method}', ${calculatedValue})"
                    style="flex: 1; padding: 6px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">
                💾 Salva
            </button>
            <button onclick="resetFinalRating('${area}', '${method}', ${calculatedValue})"
                    style="padding: 6px 10px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem;"
                    title="Ripristina valore calcolato">
                ↩️
            </button>
        </div>
    `;

    // Pulsante storico se modificato
    if (isModified) {
        headerHtml += `
            <div style="margin-top: 8px; border-top: 1px solid #eee; padding-top: 8px;">
                <button onclick="showFinalRatingHistory('${area}', '${method}', this)"
                        style="width: 100%; padding: 5px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">
                    📜 Mostra storico modifiche
                </button>
                <div id="historyContainer_${area}_${method}"></div>
            </div>
        `;
    }

    dropdown.innerHTML = headerHtml;

    // Posiziona dropdown
    const rect = element.getBoundingClientRect();
    dropdown.style.left = (rect.left + window.scrollX - 50) + 'px';
    dropdown.style.top = (rect.bottom + window.scrollY + 5) + 'px';

    document.body.appendChild(dropdown);
    activeFinalRatingDropdown = dropdown;

    // Focus sull'input
    setTimeout(() => {
        const input = document.getElementById('finalRatingInput');
        if (input) {
            input.focus();
            input.select();
        }
    }, 100);

    // Chiudi cliccando fuori
    setTimeout(() => {
        document.addEventListener('click', closeFinalRatingDropdownOnClickOutside);
    }, 100);
}

function closeFinalRatingDropdownOnClickOutside(e) {
    if (activeFinalRatingDropdown && !activeFinalRatingDropdown.contains(e.target) && !e.target.classList.contains('final-rating-editable')) {
        activeFinalRatingDropdown.remove();
        activeFinalRatingDropdown = null;
        document.removeEventListener('click', closeFinalRatingDropdownOnClickOutside);
    }
}

function saveFinalRating(area, method, calculatedValue) {
    const input = document.getElementById('finalRatingInput');
    const newValue = parseInt(input.value);

    if (isNaN(newValue) || newValue < 0 || newValue > 100) {
        alert('Il valore deve essere tra 0 e 100');
        return;
    }

    // Parametri dal PHP
    const studentid = <?php echo json_encode($userid); ?>;
    const courseid = <?php echo json_encode($courseid); ?>;
    const sector = <?php echo json_encode($currentSector ?? ''); ?>;
    const sesskey = M.cfg.sesskey;

    fetch('<?php echo $CFG->wwwroot; ?>/local/competencymanager/ajax_save_final_rating.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            sesskey: sesskey,
            action: 'save_final_rating',
            studentid: studentid,
            courseid: courseid,
            sector: sector,
            areacode: area,
            method: method,
            manualvalue: newValue,
            calculatedvalue: calculatedValue
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Aggiorna il badge nella tabella
            const badge = document.querySelector(`.final-rating-editable[data-area="${area}"][data-method="${method}"]`);
            if (badge) {
                badge.dataset.value = newValue;
                badge.dataset.modified = '1';

                // Aggiorna colore e testo
                let badgeClass = newValue >= 60 ? 'success' : (newValue >= 40 ? 'warning' : 'danger');
                if (method === 'soloCoach') badgeClass = 'info';

                badge.className = `badge badge-${badgeClass} final-rating-editable`;
                badge.innerHTML = `${newValue}% <span style="font-size: 0.7em;">✏️</span>`;
                badge.style.cursor = 'pointer';
            }

            // Chiudi dropdown
            if (activeFinalRatingDropdown) {
                activeFinalRatingDropdown.remove();
                activeFinalRatingDropdown = null;
            }

            // Toast di conferma
            showToast('✅ Valore salvato: ' + newValue + '%');
        } else {
            alert('Errore: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore di connessione');
    });
}

function resetFinalRating(area, method, calculatedValue) {
    document.getElementById('finalRatingInput').value = calculatedValue;
    saveFinalRating(area, method, calculatedValue);
}

function showFinalRatingHistory(area, method, button) {
    const container = document.getElementById(`historyContainer_${area}_${method}`);
    if (!container) return;

    // Toggle visibility
    if (container.innerHTML !== '') {
        container.innerHTML = '';
        return;
    }

    container.innerHTML = '<div style="padding: 10px; text-align: center;"><small>Caricamento...</small></div>';

    const studentid = <?php echo json_encode($userid); ?>;
    const courseid = <?php echo json_encode($courseid); ?>;
    const sector = <?php echo json_encode($currentSector ?? ''); ?>;
    const sesskey = M.cfg.sesskey;

    fetch('<?php echo $CFG->wwwroot; ?>/local/competencymanager/ajax_save_final_rating.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            sesskey: sesskey,
            action: 'get_rating_history',
            studentid: studentid,
            courseid: courseid,
            sector: sector,
            areacode: area,
            method: method
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data.hasHistory) {
            let html = '<div style="margin-top: 8px; font-size: 0.75rem; max-height: 150px; overflow-y: auto;">';
            html += '<table style="width: 100%; font-size: 0.7rem;">';
            html += '<tr style="background: #f8f9fa;"><th style="padding: 3px;">Da</th><th style="padding: 3px;">A</th><th style="padding: 3px;">Chi</th><th style="padding: 3px;">Quando</th></tr>';

            data.data.history.forEach(h => {
                html += `<tr>
                    <td style="padding: 3px; text-align: center;">${h.oldValue}</td>
                    <td style="padding: 3px; text-align: center; font-weight: bold;">${h.newValue}</td>
                    <td style="padding: 3px;">${h.modifiedBy}</td>
                    <td style="padding: 3px;">${h.date} ${h.time}</td>
                </tr>`;
            });

            html += '</table></div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div style="padding: 5px; font-size: 0.75rem; color: #6c757d;">Nessuna modifica precedente</div>';
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        container.innerHTML = '<div style="padding: 5px; color: #dc3545;">Errore caricamento</div>';
    });
}

function showToast(message) {
    // Rimuovi toast esistenti
    document.querySelectorAll('.ftm-toast').forEach(t => t.remove());

    const toast = document.createElement('div');
    toast.className = 'ftm-toast';
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #28a745;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 9999;
        font-weight: bold;
        animation: slideIn 0.3s ease-out;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}

// CSS per animazioni toast e tooltip
const toastStyle = document.createElement('style');
toastStyle.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
    .final-rating-editable:hover {
        transform: scale(1.05);
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    .history-tooltip {
        position: absolute;
        z-index: 1060;
        background: #343a40;
        color: white;
        padding: 10px 12px;
        border-radius: 6px;
        font-size: 0.75rem;
        max-width: 250px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        pointer-events: none;
    }
    .history-tooltip::before {
        content: "";
        position: absolute;
        top: -6px;
        left: 50%;
        transform: translateX(-50%);
        border-left: 6px solid transparent;
        border-right: 6px solid transparent;
        border-bottom: 6px solid #343a40;
    }
`;
document.head.appendChild(toastStyle);

// Tooltip hover per valori modificati
let hoverTimeout = null;
let activeTooltip = null;

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.final-rating-editable[data-modified="1"]').forEach(badge => {
        badge.addEventListener('mouseenter', function(e) {
            const area = this.dataset.area;
            const method = this.dataset.method;

            hoverTimeout = setTimeout(() => {
                showHistoryTooltip(this, area, method);
            }, 600); // Mostra dopo 600ms
        });

        badge.addEventListener('mouseleave', function() {
            if (hoverTimeout) {
                clearTimeout(hoverTimeout);
                hoverTimeout = null;
            }
            if (activeTooltip) {
                activeTooltip.remove();
                activeTooltip = null;
            }
        });
    });
});

function showHistoryTooltip(element, area, method) {
    if (activeTooltip) {
        activeTooltip.remove();
    }

    const tooltip = document.createElement('div');
    tooltip.className = 'history-tooltip';
    tooltip.innerHTML = '<small>Caricamento...</small>';

    const rect = element.getBoundingClientRect();
    tooltip.style.left = (rect.left + window.scrollX + rect.width/2 - 100) + 'px';
    tooltip.style.top = (rect.bottom + window.scrollY + 10) + 'px';

    document.body.appendChild(tooltip);
    activeTooltip = tooltip;

    const studentid = <?php echo json_encode($userid); ?>;
    const courseid = <?php echo json_encode($courseid); ?>;
    const sector = <?php echo json_encode($currentSector ?? ''); ?>;
    const sesskey = M.cfg.sesskey;

    fetch('<?php echo $CFG->wwwroot; ?>/local/competencymanager/ajax_save_final_rating.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            sesskey: sesskey,
            action: 'get_rating_history',
            studentid: studentid,
            courseid: courseid,
            sector: sector,
            areacode: area,
            method: method
        })
    })
    .then(response => response.json())
    .then(data => {
        if (activeTooltip && data.success && data.data.hasHistory && data.data.history.length > 0) {
            const lastMod = data.data.history[0];
            activeTooltip.innerHTML = `
                <div style="font-weight: bold; margin-bottom: 5px;">📜 Ultima modifica</div>
                <div><strong>${lastMod.oldValue}</strong> → <strong>${lastMod.newValue}</strong></div>
                <div style="margin-top: 3px; opacity: 0.8;">
                    👤 ${lastMod.modifiedBy}<br>
                    📅 ${lastMod.date} alle ${lastMod.time}
                </div>
            `;
        } else if (activeTooltip) {
            activeTooltip.innerHTML = '<small>Nessuna modifica</small>';
        }
    })
    .catch(() => {
        if (activeTooltip) {
            activeTooltip.innerHTML = '<small>Errore</small>';
        }
    });
}
</script>

<?php
echo $OUTPUT->footer();
