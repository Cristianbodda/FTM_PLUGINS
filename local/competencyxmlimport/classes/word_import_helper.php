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
 * Helper functions per import Word in setup_universale.php
 * 
 * Include questo file dopo le altre require:
 * require_once(__DIR__ . '/classes/word_import_helper.php');
 *
 * @package    local_competencyxmlimport
 * @copyright  2025 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/word_parser.php');

/**
 * Processa un file uploadato (XML o Word)
 * 
 * @param array $file_data Dati file da $_FILES
 * @param string $sector Settore corrente
 * @param int $frameworkid ID del framework
 * @param string $xml_dir Directory per salvare file
 * @return array Risultato con 'type', 'success', 'data', 'error'
 */
function process_uploaded_file($file_data, $sector, $frameworkid, $xml_dir) {
    global $DB, $SESSION;
    
    $filename = $file_data['name'];
    $tmp_name = $file_data['tmp_name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // Determina tipo file
    if ($ext === 'xml') {
        return process_xml_upload($file_data, $sector, $xml_dir);
    } elseif ($ext === 'docx') {
        return process_word_upload($file_data, $sector, $frameworkid);
    } else {
        return [
            'type' => 'unknown',
            'success' => false,
            'error' => "Formato non supportato: .$ext. Usa .xml o .docx"
        ];
    }
}

/**
 * Processa upload XML (comportamento esistente)
 */
function process_xml_upload($file_data, $sector, $xml_dir) {
    $filename = $file_data['name'];
    $tmp_name = $file_data['tmp_name'];
    
    // Verifica contenuto XML valido
    $content = file_get_contents($tmp_name);
    if (strpos($content, '<question') === false) {
        return [
            'type' => 'xml',
            'success' => false,
            'error' => "File XML non contiene domande valide"
        ];
    }
    
    // Salva il file
    $destination = $xml_dir . $filename;
    if (move_uploaded_file($tmp_name, $destination)) {
        return [
            'type' => 'xml',
            'success' => true,
            'filename' => $filename,
            'path' => $destination
        ];
    } else {
        return [
            'type' => 'xml',
            'success' => false,
            'error' => "Errore nel salvataggio del file"
        ];
    }
}

/**
 * Processa upload Word
 */
function process_word_upload($file_data, $sector, $frameworkid) {
    global $DB, $SESSION;
    
    $filename = $file_data['name'];
    $tmp_name = $file_data['tmp_name'];
    
    // Ottieni competenze valide dal framework
    $valid_competencies = get_framework_competencies($frameworkid, $sector);
    
    // Crea parser
    $parser = new \local_competencyxmlimport\word_parser($sector, $valid_competencies);
    
    // Parsa il file
    $result = $parser->parse_file($tmp_name);
    
    if (!$result['success'] && $result['total_questions'] == 0) {
        return [
            'type' => 'word',
            'success' => false,
            'error' => "Impossibile estrarre domande dal file Word. " . 
                       implode(' ', $result['errors'])
        ];
    }
    
    // Genera nome suggerito DAL NOME FILE ORIGINALE (non dal temp)
    $suggested_name = generate_quiz_name_from_filename($filename);

    // Salva in sessione per revisione
    $SESSION->word_import_questions = $result['questions'];
    $SESSION->word_import_filename = $filename;
    $SESSION->word_import_valid_competencies = $valid_competencies;
    $SESSION->word_import_sector = $sector;
    $SESSION->word_import_suggested_name = $suggested_name;

    return [
        'type' => 'word',
        'success' => true,
        'filename' => $filename,
        'result' => $result,
        'suggested_name' => $suggested_name
    ];
}

/**
 * Genera il nome quiz suggerito dal nome file originale
 *
 * @param string $filename Nome file originale (es. "AUTOVEICOLO_Quiz_Base_V2.docx")
 * @return string Nome quiz pulito (es. "AUTOVEICOLO Quiz Base")
 */
function generate_quiz_name_from_filename($filename) {
    $name = $filename;

    // Rimuovi estensione
    $name = preg_replace('/\.docx?$/i', '', $name);

    // Sostituisci underscore e trattini con spazi
    $name = str_replace(['_', '-', '.'], ' ', $name);

    // Rimuovi versioni e suffissi comuni
    $name = preg_replace('/\s*(V\d+|GOLD|F2|LOGSTYLE|NOMI|FINAL|DRAFT)\s*/i', ' ', $name);

    // Normalizza spazi multipli
    $name = preg_replace('/\s+/', ' ', $name);

    return trim($name);
}

/**
 * Mapping alias settori (nome nel file → nome nel database)
 */
function get_sector_aliases_helper() {
    return [
        // Automobile (NON usare AUTO - ambiguo con AUTOMAZIONE)
        'AUTOVEICOLO' => 'AUTOMOBILE',

        // Automazione
        'AUTOM' => 'AUTOMAZIONE',
        'AUTOMAZ' => 'AUTOMAZIONE',

        // Meccanica
        'MECC' => 'MECCANICA',

        // Metalcostruzione (settore separato da Meccanica)
        'METAL' => 'METALCOSTRUZIONE',

        // Chimica/Farmaceutica
        'CHIM' => 'CHIMFARM',
        'CHIMICA' => 'CHIMFARM',
        'FARMACEUTICA' => 'CHIMFARM',

        // Elettricità
        'ELETTR' => 'ELETTRICITA',
        'ELETT' => 'ELETTRICITA',

        // Elettronica (diverso da Elettricità)
        'ELETTRO' => 'ELETTRONICA',

        // Altri settori
        'LOG' => 'LOGISTICA',
        'INFO' => 'INFORMATICA',
        'IT' => 'INFORMATICA',
    ];
}

/**
 * Normalizza un codice competenza convertendo alias in nomi standard
 * Es: AUTOVEICOLO_MR_A1 → AUTOMOBILE_MR_A1
 * Es: automobile_mau_h2 → AUTOMOBILE_MAU_H2 (case-insensitive)
 */
function normalize_competency_code_helper($code) {
    // Prima converti tutto in maiuscolo per uniformità
    $code = strtoupper(trim($code));

    $aliases = get_sector_aliases_helper();
    foreach ($aliases as $alias => $standard) {
        if (strpos($code, $alias . '_') === 0) {
            return $standard . substr($code, strlen($alias));
        }
    }
    return $code;
}

/**
 * Ottiene tutte le competenze di un settore dal framework
 */
function get_framework_competencies($frameworkid, $sector) {
    global $DB;

    $competencies = [];

    // Ottieni tutte le competenze del framework
    $records = $DB->get_records('competency', ['competencyframeworkid' => $frameworkid]);

    foreach ($records as $comp) {
        if (!empty($comp->idnumber)) {
            // Filtra per settore se specificato
            if (empty($sector) || strpos($comp->idnumber, $sector . '_') === 0) {
                $competencies[] = $comp->idnumber;
            }
        }
    }

    return $competencies;
}

/**
 * Verifica se un codice competenza è valido (case-insensitive, con alias)
 */
function is_valid_competency($code, $valid_competencies) {
    // Normalizza il codice (maiuscolo + alias)
    $normalized = normalize_competency_code_helper($code);

    // Crea array di competenze valide in maiuscolo per confronto
    $valid_upper = array_map('strtoupper', $valid_competencies);

    return in_array($normalized, $valid_upper);
}

/**
 * Trova il codice competenza corretto nel framework (case-insensitive, con alias)
 */
function find_matching_competency($code, $valid_competencies) {
    // Normalizza il codice (maiuscolo + alias)
    $normalized = normalize_competency_code_helper($code);

    // Cerca in modo case-insensitive
    foreach ($valid_competencies as $valid) {
        if (strtoupper($valid) === $normalized) {
            return $valid; // Ritorna il codice originale dal framework
        }
    }

    return null;
}

/**
 * Genera HTML per la tabella di revisione domande Word
 */
function render_word_review_table($questions, $courseid) {
    global $SESSION;
    
    $valid_count = 0;
    $warning_count = 0;
    $error_count = 0;
    
    foreach ($questions as $q) {
        switch ($q['status']) {
            case 'ok': $valid_count++; break;
            case 'warning': $warning_count++; break;
            case 'error': $error_count++; break;
        }
    }
    
    $html = '
    <div class="word-review-container">
        <div class="word-review-header">
            <h3>📋 Revisione Domande</h3>
            <p>File: <strong>' . htmlspecialchars($SESSION->word_import_filename) . '</strong></p>
            <div class="word-review-stats">
                <span class="stat-ok">✅ ' . $valid_count . ' valide</span>
                <span class="stat-warning">⚠️ ' . $warning_count . ' da verificare</span>
                <span class="stat-error">❌ ' . $error_count . ' errori</span>
            </div>
        </div>
        
        <div class="word-review-list">';
    
    foreach ($questions as $index => $q) {
        $status_class = 'status-' . $q['status'];
        $status_icon = ['ok' => '✅', 'warning' => '⚠️', 'error' => '❌'][$q['status']];
        
        $html .= '
            <div class="question-review-item ' . $status_class . '" data-index="' . $index . '">
                <div class="question-header">
                    <span class="question-status">' . $status_icon . '</span>
                    <span class="question-num">Q' . $q['num'] . '</span>
                    <span class="question-competency">' . htmlspecialchars($q['competency'] ?: 'NON TROVATA') . '</span>';
        
        if ($q['status'] !== 'ok') {
            $html .= '<button type="button" class="btn-correct" onclick="openCorrectionModal(' . $index . ')">Correggi</button>';
        }
        
        $html .= '
                </div>
                <div class="question-text">' . htmlspecialchars(substr($q['text'], 0, 100)) . '...</div>
                <div class="question-answers">
                    Risposte: ' . count($q['answers']) . ' | Corretta: <strong>' . ($q['correct_answer'] ?: '?') . '</strong>
                </div>';
        
        if (!empty($q['issues'])) {
            $html .= '<div class="question-issues">' . implode(' • ', array_map('htmlspecialchars', $q['issues'])) . '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '
        </div>
    </div>';
    
    return $html;
}

/**
 * Genera HTML per il modal di correzione
 */
function render_correction_modal($courseid) {
    global $SESSION;
    
    $suggestions_json = json_encode($SESSION->word_import_valid_competencies ?? []);
    
    return '
    <div id="correctionModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Correggi Domanda <span id="modalQuestionNum"></span></h3>
                <button type="button" class="modal-close" onclick="closeCorrectionModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Testo domanda:</label>
                    <div id="modalQuestionText" class="question-preview"></div>
                </div>
                
                <div class="form-group">
                    <label>Risposte trovate:</label>
                    <div id="modalAnswers" class="answers-preview"></div>
                </div>
                
                <hr>
                
                <div class="form-group">
                    <label for="modalCompetency">Competenza:</label>
                    <input type="text" id="modalCompetency" class="form-control" 
                           placeholder="es. CHIMFARM_7S_01" autocomplete="off">
                    <div id="competencySuggestions" class="suggestions-list"></div>
                </div>
                
                <div class="form-group">
                    <label>Risposta corretta:</label>
                    <div id="modalCorrectAnswer" class="answer-options">
                        <label><input type="radio" name="correctAnswer" value="A"> A</label>
                        <label><input type="radio" name="correctAnswer" value="B"> B</label>
                        <label><input type="radio" name="correctAnswer" value="C"> C</label>
                        <label><input type="radio" name="correctAnswer" value="D"> D</label>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCorrectionModal()">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="saveCorrection()">✓ Salva correzione</button>
            </div>
        </div>
    </div>
    
    <script>
    var validCompetencies = ' . $suggestions_json . ';
    var currentQuestionIndex = null;
    var questionsData = ' . json_encode($SESSION->word_import_questions ?? []) . ';
    
    function openCorrectionModal(index) {
        currentQuestionIndex = index;
        var q = questionsData[index];
        
        document.getElementById("modalQuestionNum").textContent = "Q" + q.num;
        document.getElementById("modalQuestionText").textContent = q.text;
        document.getElementById("modalCompetency").value = q.competency || "";
        
        // Mostra risposte
        var answersHtml = "";
        for (var letter in q.answers) {
            answersHtml += "<div><strong>" + letter + ")</strong> " + q.answers[letter] + "</div>";
        }
        document.getElementById("modalAnswers").innerHTML = answersHtml;
        
        // Seleziona risposta corretta
        var radios = document.querySelectorAll(\'input[name="correctAnswer"]\');
        radios.forEach(function(r) {
            r.checked = (r.value === q.correct_answer);
        });
        
        document.getElementById("correctionModal").style.display = "flex";
    }
    
    function closeCorrectionModal() {
        document.getElementById("correctionModal").style.display = "none";
        currentQuestionIndex = null;
    }
    
    function saveCorrection() {
        if (currentQuestionIndex === null) return;
        
        var competency = document.getElementById("modalCompetency").value.trim().toUpperCase();
        var correctAnswer = document.querySelector(\'input[name="correctAnswer"]:checked\');
        
        if (!competency) {
            alert("Inserisci il codice competenza");
            return;
        }
        if (!correctAnswer) {
            alert("Seleziona la risposta corretta");
            return;
        }
        
        // Invia via AJAX
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "ajax/word_import.php?sesskey=" + M.cfg.sesskey);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onload = function() {
            if (xhr.status === 200) {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    // Aggiorna dati locali
                    questionsData[currentQuestionIndex] = resp.question;
                    // Aggiorna UI
                    updateQuestionRow(currentQuestionIndex, resp.question);
                    closeCorrectionModal();
                } else {
                    alert("Errore: " + resp.error);
                }
            }
        };
        xhr.send("action=savecorrection&index=" + currentQuestionIndex + 
                 "&competency=" + encodeURIComponent(competency) + 
                 "&correct_answer=" + correctAnswer.value);
    }
    
    function updateQuestionRow(index, question) {
        var row = document.querySelector(\'.question-review-item[data-index="\' + index + \'"]\');
        if (!row) return;
        
        var statusIcons = {ok: "✅", warning: "⚠️", error: "❌"};
        row.className = "question-review-item status-" + question.status;
        row.querySelector(".question-status").textContent = statusIcons[question.status];
        row.querySelector(".question-competency").textContent = question.competency || "NON TROVATA";
        
        var btnCorrect = row.querySelector(".btn-correct");
        if (question.status === "ok" && btnCorrect) {
            btnCorrect.style.display = "none";
        }
        
        var issuesDiv = row.querySelector(".question-issues");
        if (issuesDiv) {
            issuesDiv.textContent = question.issues.join(" • ");
        }
        
        // Aggiorna contatori
        updateStats();
    }
    
    function updateStats() {
        var valid = 0, warning = 0, error = 0;
        questionsData.forEach(function(q) {
            if (q.status === "ok") valid++;
            else if (q.status === "warning") warning++;
            else error++;
        });
        
        document.querySelector(".stat-ok").textContent = "✅ " + valid + " valide";
        document.querySelector(".stat-warning").textContent = "⚠️ " + warning + " da verificare";
        document.querySelector(".stat-error").textContent = "❌ " + error + " errori";
    }
    
    // Autocomplete competenze
    document.getElementById("modalCompetency").addEventListener("input", function() {
        var val = this.value.toUpperCase();
        var suggestionsDiv = document.getElementById("competencySuggestions");
        
        if (val.length < 2) {
            suggestionsDiv.innerHTML = "";
            return;
        }
        
        var matches = validCompetencies.filter(function(c) {
            return c.indexOf(val) !== -1;
        }).slice(0, 8);
        
        if (matches.length > 0) {
            suggestionsDiv.innerHTML = matches.map(function(m) {
                return \'<div class="suggestion-item" onclick="selectCompetency(this)">\' + m + \'</div>\';
            }).join("");
        } else {
            suggestionsDiv.innerHTML = \'<div class="no-suggestions">Nessun suggerimento</div>\';
        }
    });
    
    function selectCompetency(el) {
        document.getElementById("modalCompetency").value = el.textContent;
        document.getElementById("competencySuggestions").innerHTML = "";
    }
    </script>';
}

/**
 * CSS per il review Word
 */
function get_word_review_css() {
    return '
    <style>
    /* Container revisione Word */
    .word-review-container {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    
    .word-review-header {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .word-review-header h3 {
        margin: 0 0 10px 0;
    }
    
    .word-review-stats {
        display: flex;
        gap: 20px;
    }
    
    .stat-ok { color: #10b981; }
    .stat-warning { color: #f59e0b; }
    .stat-error { color: #ef4444; }
    
    /* Lista domande */
    .word-review-list {
        max-height: 500px;
        overflow-y: auto;
    }
    
    .question-review-item {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 10px;
        border-left: 4px solid #ccc;
        background: #f9fafb;
    }
    
    .question-review-item.status-ok {
        border-left-color: #10b981;
        background: #f0fdf4;
    }
    
    .question-review-item.status-warning {
        border-left-color: #f59e0b;
        background: #fffbeb;
    }
    
    .question-review-item.status-error {
        border-left-color: #ef4444;
        background: #fef2f2;
    }
    
    .question-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }
    
    .question-num {
        font-weight: bold;
        color: #374151;
    }
    
    .question-competency {
        font-family: monospace;
        background: #e5e7eb;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 13px;
    }
    
    .btn-correct {
        margin-left: auto;
        background: #667eea;
        color: white;
        border: none;
        padding: 5px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }
    
    .btn-correct:hover {
        background: #5a67d8;
    }
    
    .question-text {
        color: #4b5563;
        font-size: 14px;
        margin-bottom: 5px;
    }
    
    .question-answers {
        font-size: 13px;
        color: #6b7280;
    }
    
    .question-issues {
        margin-top: 8px;
        font-size: 12px;
        color: #dc2626;
        font-style: italic;
    }
    
    /* Modal correzione */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }
    
    .modal-content {
        background: white;
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 50px rgba(0,0,0,0.3);
    }
    
    .modal-header {
        padding: 20px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-header h3 {
        margin: 0;
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #6b7280;
    }
    
    .modal-body {
        padding: 20px;
    }
    
    .modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        font-weight: 500;
        margin-bottom: 5px;
    }
    
    .form-control {
        width: 100%;
        padding: 10px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
    }
    
    .question-preview {
        background: #f3f4f6;
        padding: 10px;
        border-radius: 6px;
        font-size: 14px;
        max-height: 100px;
        overflow-y: auto;
    }
    
    .answers-preview {
        background: #f3f4f6;
        padding: 10px;
        border-radius: 6px;
        font-size: 13px;
    }
    
    .answers-preview div {
        margin-bottom: 5px;
    }
    
    .answer-options {
        display: flex;
        gap: 20px;
    }
    
    .answer-options label {
        display: flex;
        align-items: center;
        gap: 5px;
        cursor: pointer;
    }
    
    .suggestions-list {
        border: 1px solid #d1d5db;
        border-top: none;
        border-radius: 0 0 6px 6px;
        max-height: 150px;
        overflow-y: auto;
    }
    
    .suggestion-item {
        padding: 8px 10px;
        cursor: pointer;
        font-family: monospace;
        font-size: 13px;
    }
    
    .suggestion-item:hover {
        background: #667eea;
        color: white;
    }
    
    .no-suggestions {
        padding: 8px 10px;
        color: #6b7280;
        font-style: italic;
    }
    
    .btn {
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
    }
    
    .btn-secondary {
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        color: #374151;
    }
    
    .btn-primary {
        background: #667eea;
        border: none;
        color: white;
    }
    
    .btn-primary:hover {
        background: #5a67d8;
    }
    </style>';
}

/**
 * Converte domande Word parsate in formato XML Moodle
 * 
 * @param array $questions Array di domande dal parser Word
 * @param string $quiz_name Nome del quiz
 * @return string XML Moodle
 */
function convert_word_questions_to_xml($questions, $quiz_name) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<quiz>' . "\n";
    $xml .= '<!-- Convertito automaticamente da Word -->' . "\n\n";
    
    foreach ($questions as $q) {
        // Salta domande con errori gravi
        if ($q['status'] === 'error' && empty($q['competency'])) {
            continue;
        }
        
        $name = htmlspecialchars($quiz_name . '_Q' . $q['num'] . ' - ' . $q['competency']);
        $text = htmlspecialchars($q['text']);
        
        $xml .= '<question type="multichoice">' . "\n";
        $xml .= '    <name><text>' . $name . '</text></name>' . "\n";
        $xml .= '    <questiontext format="html"><text>&lt;p&gt;' . $text . '&lt;/p&gt;</text></questiontext>' . "\n";
        $xml .= '    <generalfeedback format="html"><text></text></generalfeedback>' . "\n";
        $xml .= '    <defaultgrade>1.0000000</defaultgrade>' . "\n";
        $xml .= '    <penalty>0.3333333</penalty>' . "\n";
        $xml .= '    <hidden>0</hidden>' . "\n";
        $xml .= '    <single>true</single>' . "\n";
        $xml .= '    <shuffleanswers>true</shuffleanswers>' . "\n";
        $xml .= '    <answernumbering>abc</answernumbering>' . "\n";
        
        // Risposte
        foreach ($q['answers'] as $letter => $answer_text) {
            $fraction = ($letter === $q['correct_answer']) ? '100' : '0';
            $feedback = ($letter === $q['correct_answer']) ? 'Corretto!' : 'Non corretto.';
            $answer_escaped = htmlspecialchars($answer_text);
            
            $xml .= '    <answer fraction="' . $fraction . '" format="html"><text>&lt;p&gt;' . $answer_escaped . '&lt;/p&gt;</text><feedback format="html"><text>&lt;p&gt;' . $feedback . '&lt;/p&gt;</text></feedback></answer>' . "\n";
        }
        
        $xml .= '    <correctfeedback format="html"><text>&lt;p&gt;Risposta corretta!&lt;/p&gt;</text></correctfeedback>' . "\n";
        $xml .= '    <incorrectfeedback format="html"><text>&lt;p&gt;Risposta non corretta.&lt;/p&gt;</text></incorrectfeedback>' . "\n";
        $xml .= '</question>' . "\n\n";
    }
    
    $xml .= '</quiz>';
    
    return $xml;
}

/**
 * Salva le domande Word come file XML
 */
function save_word_questions_as_xml($questions, $quiz_name, $xml_dir) {
    $xml_content = convert_word_questions_to_xml($questions, $quiz_name);
    
    // Nome file sicuro
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $quiz_name);
    $filename = $safe_name . '_' . date('Ymd_His') . '.xml';
    $filepath = $xml_dir . $filename;
    
    if (file_put_contents($filepath, $xml_content)) {
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $filepath
        ];
    }
    
    return [
        'success' => false,
        'error' => 'Impossibile salvare il file XML'
    ];
}
