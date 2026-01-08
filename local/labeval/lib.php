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
 * Library functions for local_labeval
 *
 * @package    local_labeval
 * @copyright  2024 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add navigation nodes to the navigation tree
 *
 * @param global_navigation $navigation
 */
function local_labeval_extend_navigation(global_navigation $navigation) {
    global $CFG, $USER;
    
    if (!isloggedin() || isguestuser()) {
        return;
    }
    
    $context = context_system::instance();
    if (!has_capability('local/labeval:view', $context)) {
        return;
    }
    
    // Aggiungi nodo di navigazione
    $node = $navigation->add(
        get_string('pluginname', 'local_labeval'),
        new moodle_url('/local/labeval/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_labeval',
        new pix_icon('i/settings', '')
    );
    $node->showinflatnavigation = true;
}

/**
 * Get competency full info from code
 * Returns description and area name
 *
 * @param string $code Competency code (e.g., MECCANICA_MIS_01)
 * @return array ['description' => string, 'area' => string, 'areacode' => string]
 */
function local_labeval_get_competency_info($code) {
    global $DB;
    
    // Try to get from Moodle competency table first
    $competency = $DB->get_record('competency', ['idnumber' => $code], 'id, shortname, description', IGNORE_MULTIPLE);
    
    $description = '';
    if ($competency) {
        $description = $competency->shortname ?: strip_tags($competency->description);
    }
    
    // If no description from DB, try to get from behavior_comp mapping
    if (empty($description)) {
        // Get description from the import (stored in behavior_comp via behavior)
        $sql = "SELECT bc.competencycode, b.description as behaviordesc
                FROM {local_labeval_behavior_comp} bc
                JOIN {local_labeval_behaviors} b ON b.id = bc.behaviorid
                WHERE bc.competencycode = ?
                LIMIT 1";
        $mapping = $DB->get_record_sql($sql, [$code]);
        // We don't have comp description stored, so leave empty
    }
    
    // Extract area code from competency code (e.g., MIS from MECCANICA_MIS_01)
    $areacode = '';
    $parts = explode('_', $code);
    if (count($parts) >= 2) {
        $areacode = $parts[1]; // Second part is area code
    }
    
    // Map area codes to readable names
    $areanames = [
        'MIS' => 'Misure e Controlli',
        'DT' => 'Disegno Tecnico',
        'PIAN' => 'Pianificazione',
        'SAQ' => 'Sicurezza e Qualità',
        'CSP' => 'Competenze Trasversali',
        'LAV' => 'Lavorazioni',
        'TECN' => 'Tecnologia',
        'MANUT' => 'Manutenzione',
        'GEST' => 'Gestione',
        'COM' => 'Comunicazione',
    ];
    
    $area = $areanames[$areacode] ?? $areacode;
    
    return [
        'description' => $description,
        'area' => $area,
        'areacode' => $areacode
    ];
}

/**
 * Get rating label and class
 *
 * @param int $rating Rating value (0, 1, 3)
 * @return array ['label' => string, 'class' => string, 'stars' => string]
 */
function local_labeval_get_rating_info($rating) {
    switch ($rating) {
        case 0:
            return [
                'label' => get_string('rating0', 'local_labeval'),
                'class' => 'secondary',
                'stars' => '○',
                'color' => '#6c757d'
            ];
        case 1:
            return [
                'label' => get_string('rating1', 'local_labeval'),
                'class' => 'warning',
                'stars' => '★',
                'color' => '#ffc107'
            ];
        case 3:
            return [
                'label' => get_string('rating3', 'local_labeval'),
                'class' => 'success',
                'stars' => '★★★',
                'color' => '#28a745'
            ];
        default:
            return [
                'label' => '-',
                'class' => 'secondary',
                'stars' => '-',
                'color' => '#6c757d'
            ];
    }
}

/**
 * Get status badge HTML
 *
 * @param string $status Status value
 * @return string HTML badge
 */
function local_labeval_get_status_badge($status) {
    $labels = [
        'pending' => ['text' => get_string('pending', 'local_labeval'), 'class' => 'warning'],
        'completed' => ['text' => get_string('completed', 'local_labeval'), 'class' => 'success'],
        'expired' => ['text' => get_string('expired', 'local_labeval'), 'class' => 'danger'],
        'draft' => ['text' => 'Bozza', 'class' => 'info'],
        'active' => ['text' => 'Attivo', 'class' => 'success'],
        'archived' => ['text' => 'Archiviato', 'class' => 'secondary'],
    ];
    
    $info = $labels[$status] ?? ['text' => $status, 'class' => 'secondary'];
    
    return '<span class="badge badge-' . $info['class'] . '">' . $info['text'] . '</span>';
}

/**
 * Calculate competency scores from a session
 *
 * @param int $sessionid Session ID
 * @return array Competency scores indexed by competency code
 */
function local_labeval_calculate_competency_scores($sessionid) {
    global $DB;
    
    // Get all ratings for this session with their behavior-competency mappings
    // Use bc.id as first column to ensure uniqueness (each behavior-competency mapping is unique)
    $sql = "SELECT bc.id as bcid, r.behaviorid, r.rating, 
                   bc.competencyid, bc.competencycode, bc.weight
            FROM {local_labeval_ratings} r
            JOIN {local_labeval_behavior_comp} bc ON bc.behaviorid = r.behaviorid
            WHERE r.sessionid = ?";
    
    $ratings = $DB->get_records_sql($sql, [$sessionid]);
    
    // Group by competency CODE (not ID) and calculate scores
    $competencies = [];
    
    foreach ($ratings as $rating) {
        // Use competencycode as the key, skip empty codes
        $code = trim($rating->competencycode);
        if (empty($code)) {
            continue;
        }
        
        if (!isset($competencies[$code])) {
            $competencies[$code] = [
                'competencyid' => $rating->competencyid ?: 0,
                'competencycode' => $code,
                'score' => 0,
                'maxscore' => 0,
                'count' => 0
            ];
        }
        
        // Score = rating * weight
        $competencies[$code]['score'] += $rating->rating * $rating->weight;
        // Max score = 3 * weight (max rating is 3)
        $competencies[$code]['maxscore'] += 3 * $rating->weight;
        $competencies[$code]['count']++;
    }
    
    // Calculate percentages
    foreach ($competencies as $code => &$comp) {
        if ($comp['maxscore'] > 0) {
            $comp['percentage'] = round(($comp['score'] / $comp['maxscore']) * 100, 2);
        } else {
            $comp['percentage'] = 0;
        }
    }
    
    return $competencies;
}

/**
 * Save competency scores to cache table
 *
 * @param int $sessionid Session ID
 * @param array $scores Competency scores
 */
function local_labeval_save_competency_scores($sessionid, $scores) {
    global $DB;
    
    // Delete existing scores for this session
    $DB->delete_records('local_labeval_comp_scores', ['sessionid' => $sessionid]);
    
    // Insert new scores - use competencycode as unique key
    $inserted = [];
    foreach ($scores as $code => $data) {
        // Skip if we already inserted this competencycode (avoid duplicates)
        $uniquekey = $data['competencycode'];
        if (isset($inserted[$uniquekey])) {
            // Merge scores for same competency
            continue;
        }
        
        // Skip empty competency codes
        if (empty($data['competencycode'])) {
            continue;
        }
        
        $record = new stdClass();
        $record->sessionid = $sessionid;
        $record->competencyid = $data['competencyid'] ?: 0;
        $record->competencycode = $data['competencycode'];
        $record->score = $data['score'];
        $record->maxscore = $data['maxscore'];
        $record->percentage = $data['percentage'];
        
        try {
            $DB->insert_record('local_labeval_comp_scores', $record);
            $inserted[$uniquekey] = true;
        } catch (Exception $e) {
            // If duplicate, try to update instead
            $existing = $DB->get_record('local_labeval_comp_scores', [
                'sessionid' => $sessionid,
                'competencycode' => $data['competencycode']
            ]);
            if ($existing) {
                $existing->score += $data['score'];
                $existing->maxscore += $data['maxscore'];
                $existing->percentage = ($existing->maxscore > 0) ? 
                    round(($existing->score / $existing->maxscore) * 100, 2) : 0;
                $DB->update_record('local_labeval_comp_scores', $existing);
            }
        }
    }
}

/**
 * Update session totals
 *
 * @param int $sessionid Session ID
 */
function local_labeval_update_session_totals($sessionid) {
    global $DB;
    
    // Get all ratings
    $sql = "SELECT SUM(r.rating * bc.weight) as totalscore,
                   SUM(3 * bc.weight) as maxscore
            FROM {local_labeval_ratings} r
            JOIN {local_labeval_behavior_comp} bc ON bc.behaviorid = r.behaviorid
            WHERE r.sessionid = ?";
    
    $totals = $DB->get_record_sql($sql, [$sessionid]);
    
    $session = $DB->get_record('local_labeval_sessions', ['id' => $sessionid]);
    $session->totalscore = $totals->totalscore ?? 0;
    $session->maxscore = $totals->maxscore ?? 0;
    
    if ($session->maxscore > 0) {
        $session->percentage = round(($session->totalscore / $session->maxscore) * 100, 2);
    } else {
        $session->percentage = 0;
    }
    
    $DB->update_record('local_labeval_sessions', $session);
}

/**
 * Check if student is authorized to view reports
 *
 * @param int $studentid Student ID
 * @return bool
 */
function local_labeval_is_student_authorized($studentid) {
    global $DB;
    return $DB->record_exists('local_labeval_auth', ['studentid' => $studentid]);
}

/**
 * Authorize student to view reports
 *
 * @param int $studentid Student ID
 * @param int $authorizedby User who authorized
 * @return bool
 */
function local_labeval_authorize_student($studentid, $authorizedby) {
    global $DB;
    
    if (local_labeval_is_student_authorized($studentid)) {
        return true;
    }
    
    $record = new stdClass();
    $record->studentid = $studentid;
    $record->authorizedby = $authorizedby;
    $record->timecreated = time();
    
    return $DB->insert_record('local_labeval_auth', $record);
}

/**
 * Remove student authorization
 *
 * @param int $studentid Student ID
 * @return bool
 */
function local_labeval_unauthorize_student($studentid) {
    global $DB;
    return $DB->delete_records('local_labeval_auth', ['studentid' => $studentid]);
}

/**
 * Get common CSS styles for labeval pages
 * Matches coachmanager style
 *
 * @return string CSS
 */
function local_labeval_get_common_styles() {
    return '
<style>
.labeval-container {
    max-width: 1400px;
    margin: 0 auto;
}

.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    overflow: hidden;
    margin-bottom: 20px;
}

.card-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
}

.card-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.card-body {
    padding: 20px;
}

.card-header.primary {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.card-header.info {
    background: linear-gradient(135deg, #17a2b8, #0dcaf0);
    color: white;
}

.card-header.purple {
    background: linear-gradient(135deg, #9b59b6, #8e44ad);
    color: white;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th, .table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
    font-size: 13px;
}

.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}

.badge-success { background: #28a745; color: white; }
.badge-warning { background: #ffc107; color: #333; }
.badge-danger { background: #dc3545; color: white; }
.badge-secondary { background: #6c757d; color: white; }
.badge-info { background: #17a2b8; color: white; }
.badge-primary { background: #0d6efd; color: white; }

.btn {
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 13px;
    border: none;
    cursor: pointer;
    display: inline-block;
    transition: all 0.2s;
}

.btn:hover {
    transform: translateY(-2px);
    text-decoration: none;
}

.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn-lg { padding: 12px 24px; font-size: 15px; }

.btn-primary { background: linear-gradient(135deg, #0d6efd, #0dcaf0); color: white; }
.btn-success { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
.btn-info { background: linear-gradient(135deg, #17a2b8, #0dcaf0); color: white; }
.btn-warning { background: linear-gradient(135deg, #ffc107, #fd7e14); color: #333; }
.btn-danger { background: linear-gradient(135deg, #dc3545, #c82333); color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-purple { background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; }
.btn-outline { background: white; border: 1px solid #0d6efd; color: #0d6efd; }

.btn-group {
    display: flex;
    gap: 5px;
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success { background: #d4edda; border: 1px solid #28a745; color: #155724; }
.alert-warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; }
.alert-danger { background: #f8d7da; border: 1px solid #dc3545; color: #721c24; }
.alert-info { background: #d1ecf1; border: 1px solid #17a2b8; color: #0c5460; }

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border-top: 4px solid #28a745;
}

.stat-card .number { font-size: 2rem; font-weight: 700; color: #28a745; }
.stat-card .label { font-size: 0.85rem; color: #666; margin-top: 5px; }
.stat-card.info { border-top-color: #17a2b8; }
.stat-card.info .number { color: #17a2b8; }
.stat-card.warning { border-top-color: #ffc107; }
.stat-card.warning .number { color: #f39c12; }
.stat-card.purple { border-top-color: #9b59b6; }
.stat-card.purple .number { color: #9b59b6; }

.progress-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(135deg, #28a745, #20c997);
    border-radius: 4px;
    transition: width 0.3s;
}

.rating-selector {
    display: flex;
    gap: 10px;
}

.rating-option {
    cursor: pointer;
}

.rating-option input {
    display: none;
}

.rating-label {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 16px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.2s;
}

.rating-option input:checked + .rating-label {
    border-color: #28a745;
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.rating-option.rating-0 input:checked + .rating-label {
    border-color: #6c757d;
    background: #6c757d;
}

.rating-option.rating-1 input:checked + .rating-label {
    border-color: #ffc107;
    background: linear-gradient(135deg, #ffc107, #fd7e14);
    color: #333;
}

.rating-label:hover {
    border-color: #28a745;
    transform: scale(1.05);
}

.no-data {
    text-align: center;
    padding: 40px;
    color: #888;
}

.section-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #333;
    margin: 25px 0 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e0e0e0;
}

.filters-bar {
    margin-bottom: 20px;
}

.filters-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 500;
    margin-bottom: 8px;
}

.form-control {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.form-control:focus {
    border-color: #28a745;
    outline: none;
    box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.config-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.config-section h4 {
    margin: 0 0 15px;
    font-size: 15px;
    color: #333;
}

.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.checkbox-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

.radio-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.radio-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.radio-item input[type="radio"] {
    width: 18px;
    height: 18px;
}

@media (max-width: 768px) {
    .filters-row { flex-direction: column; align-items: stretch; }
    .rating-selector { flex-wrap: wrap; }
    .stats-row { grid-template-columns: repeat(2, 1fr); }
}
</style>
';
}
