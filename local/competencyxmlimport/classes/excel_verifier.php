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
 * Verifica Word vs Excel
 * 
 * Confronta le domande estratte dal Word con i dati dell'Excel di controllo.
 *
 * @package    local_competencyxmlimport
 * @copyright  2025 FTM - Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_competencyxmlimport;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/excel_reader.php');

/**
 * Classe per verificare Word vs Excel
 */
class excel_verifier {
    
    /** @var excel_reader Lettore Excel */
    private $reader = null;
    
    /** @var array Dati Excel caricati */
    private $excel_data = [];
    
    /** @var array Mapping colonne */
    private $column_mapping = [];
    
    /** @var string Nome foglio selezionato */
    private $selected_sheet = '';
    
    /**
     * Carica un file Excel
     * 
     * @param string $filepath Percorso al file .xlsx
     * @return array ['success' => bool, 'sheets' => array, 'error' => string]
     */
    public function load_excel($filepath) {
        $this->reader = new excel_reader();
        
        if (!$this->reader->load($filepath)) {
            return [
                'success' => false,
                'error' => 'Impossibile aprire il file Excel. Verifica che sia un .xlsx valido.'
            ];
        }
        
        $sheets_info = $this->reader->get_sheets_info();
        
        return [
            'success' => true,
            'sheets' => $sheets_info,
            'sheet_names' => $this->reader->get_sheet_names()
        ];
    }
    
    /**
     * Seleziona un foglio e rileva le colonne
     * 
     * @param string|int $sheet Nome o indice del foglio
     * @return array Info sul foglio con auto-detect colonne
     */
    public function select_sheet($sheet) {
        $this->selected_sheet = $sheet;
        
        $auto_detect = $this->reader->auto_detect_columns($sheet);
        $data = $this->reader->get_sheet_data($sheet);
        
        return [
            'success' => true,
            'headers' => $auto_detect['headers'],
            'auto_detect' => [
                'question_col' => $auto_detect['question_col'],
                'competency_col' => $auto_detect['competency_col'],
                'answer_col' => $auto_detect['answer_col']
            ],
            'row_count' => count($data) - 1, // escludi header
            'preview' => array_slice($data, 0, 5) // prime 5 righe per preview
        ];
    }
    
    /**
     * Imposta il mapping delle colonne
     * 
     * @param int $question_col Indice colonna domanda
     * @param int $competency_col Indice colonna competenza
     * @param int $answer_col Indice colonna risposta
     */
    public function set_column_mapping($question_col, $competency_col, $answer_col) {
        $this->column_mapping = [
            'question' => (int)$question_col,
            'competency' => (int)$competency_col,
            'answer' => (int)$answer_col
        ];
        
        // Carica dati Excel con mapping
        $this->load_excel_data();
    }
    
    /**
     * Carica i dati Excel nel formato per la verifica
     */
    private function load_excel_data() {
        $data = $this->reader->get_sheet_data($this->selected_sheet);
        $this->excel_data = [];
        
        // Salta header
        for ($i = 1; $i < count($data); $i++) {
            $row = $data[$i];
            
            $question = isset($row[$this->column_mapping['question']]) 
                ? $this->normalize_question_num($row[$this->column_mapping['question']]) 
                : '';
            
            $competency = isset($row[$this->column_mapping['competency']]) 
                ? strtoupper(trim($row[$this->column_mapping['competency']])) 
                : '';
            
            $answer = isset($row[$this->column_mapping['answer']]) 
                ? strtoupper(trim($row[$this->column_mapping['answer']])) 
                : '';
            
            if (!empty($question)) {
                $this->excel_data[$question] = [
                    'competency' => $competency,
                    'answer' => $answer
                ];
            }
        }
    }
    
    /**
     * Normalizza il numero della domanda (Q01, Q1, 01, 1, LOG_APPR01_Q01 -> Q01)
     *
     * @param string $q Numero domanda
     * @return string Numero normalizzato
     */
    private function normalize_question_num($q) {
        $q = trim($q);

        // Prima cerca pattern _Q## alla fine (es. LOG_APPR01_Q01 -> Q01)
        if (preg_match('/_Q(\d+)$/i', $q, $matches)) {
            return 'Q' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        }

        // Poi cerca Q## in qualsiasi posizione
        if (preg_match('/Q(\d+)/i', $q, $matches)) {
            return 'Q' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        }

        // Fallback: prendi solo numeri (per formati come "01", "1")
        $num = preg_replace('/[^0-9]/', '', $q);
        if (!empty($num)) {
            return 'Q' . str_pad($num, 2, '0', STR_PAD_LEFT);
        }

        return 'Q00'; // fallback
    }
    
    /**
     * Verifica le domande Word contro l'Excel
     * 
     * @param array $word_questions Array di domande dal parser Word
     * @return array Risultato della verifica
     */
    public function verify($word_questions) {
        $results = [
            'total_word' => count($word_questions),
            'total_excel' => count($this->excel_data),
            'matches' => 0,
            'discrepancies' => [],
            'missing_in_excel' => [],
            'missing_in_word' => [],
            'details' => []
        ];
        
        $excel_used = [];
        
        foreach ($word_questions as $index => $q) {
            $q_num = 'Q' . str_pad($q['num'], 2, '0', STR_PAD_LEFT);
            $word_competency = strtoupper(trim($q['competency'] ?? ''));
            $word_answer = strtoupper(trim($q['correct_answer'] ?? ''));
            
            $detail = [
                'index' => $index,
                'question' => $q_num,
                'word_competency' => $word_competency,
                'word_answer' => $word_answer,
                'excel_competency' => '',
                'excel_answer' => '',
                'status' => 'ok',
                'issues' => []
            ];
            
            if (isset($this->excel_data[$q_num])) {
                $excel_used[$q_num] = true;
                $excel_competency = $this->excel_data[$q_num]['competency'];
                $excel_answer = $this->excel_data[$q_num]['answer'];
                
                $detail['excel_competency'] = $excel_competency;
                $detail['excel_answer'] = $excel_answer;
                
                // Confronta competenza
                if ($word_competency !== $excel_competency && !empty($excel_competency)) {
                    $detail['issues'][] = 'competency';
                    $detail['status'] = 'discrepancy';
                    $results['discrepancies'][] = [
                        'type' => 'competency',
                        'question' => $q_num,
                        'index' => $index,
                        'word_value' => $word_competency,
                        'excel_value' => $excel_competency
                    ];
                }
                
                // Confronta risposta
                if ($word_answer !== $excel_answer && !empty($excel_answer)) {
                    $detail['issues'][] = 'answer';
                    $detail['status'] = 'discrepancy';
                    $results['discrepancies'][] = [
                        'type' => 'answer',
                        'question' => $q_num,
                        'index' => $index,
                        'word_value' => $word_answer,
                        'excel_value' => $excel_answer
                    ];
                }
                
                if ($detail['status'] === 'ok') {
                    $results['matches']++;
                }
                
            } else {
                $detail['status'] = 'missing_excel';
                $results['missing_in_excel'][] = $q_num;
            }
            
            $results['details'][] = $detail;
        }
        
        // Trova domande in Excel ma non in Word
        foreach ($this->excel_data as $q_num => $data) {
            if (!isset($excel_used[$q_num])) {
                $results['missing_in_word'][] = $q_num;
            }
        }
        
        // Calcola statistiche
        $results['match_percentage'] = $results['total_word'] > 0 
            ? round(($results['matches'] / $results['total_word']) * 100, 1) 
            : 0;
        
        $results['has_discrepancies'] = count($results['discrepancies']) > 0;
        $results['all_resolved'] = count($results['discrepancies']) === 0;
        
        return $results;
    }
    
    /**
     * Ottiene i dati Excel per una domanda specifica
     * 
     * @param string $question_num Numero domanda (es. Q01)
     * @return array|null
     */
    public function get_excel_data_for_question($question_num) {
        $q_num = $this->normalize_question_num($question_num);
        return isset($this->excel_data[$q_num]) ? $this->excel_data[$q_num] : null;
    }
    
    /**
     * Ottiene tutti i dati Excel caricati
     * 
     * @return array
     */
    public function get_all_excel_data() {
        return $this->excel_data;
    }
}


/**
 * Funzioni helper per la verifica Excel da usare in setup_universale.php
 */

/**
 * Genera HTML per la sezione di verifica Excel
 */
function render_excel_verification_section($courseid, $show = false) {
    global $SESSION;
    
    $checked = $show ? 'checked' : '';
    $display = $show ? 'block' : 'none';
    
    $html = '
    <div class="excel-verification-section">
        <div class="excel-toggle">
            <label>
                <input type="checkbox" id="enableExcelVerify" ' . $checked . ' onchange="toggleExcelVerify()">
                <strong>🔍 Verifica con file Excel di controllo</strong>
            </label>
            <a href="download_template.php?type=excel_verify" class="template-link">📥 Scarica Template</a>
        </div>
        
        <div id="excelVerifyPanel" style="display: ' . $display . ';">
            <div class="excel-upload-area">
                <label>📊 Carica file Excel di verifica:</label>
                <input type="file" name="excel_verify" id="excelVerifyFile" accept=".xlsx" onchange="uploadExcelVerify()">
            </div>
            
            <div id="excelSheetSelector" style="display: none;">
                <label>Seleziona foglio:</label>
                <select id="excelSheet" onchange="selectExcelSheet()">
                    <option value="">-- Seleziona foglio --</option>
                </select>
            </div>
            
            <div id="excelColumnMapping" style="display: none;">
                <h4>Mapping Colonne</h4>
                <div class="column-mapping-row">
                    <label>Colonna Domanda:</label>
                    <select id="colQuestion"></select>
                </div>
                <div class="column-mapping-row">
                    <label>Colonna Competenza:</label>
                    <select id="colCompetency"></select>
                </div>
                <div class="column-mapping-row">
                    <label>Colonna Risposta:</label>
                    <select id="colAnswer"></select>
                </div>
                <button type="button" class="btn btn-primary" onclick="runExcelVerify()">🔍 Esegui Verifica</button>
            </div>
            
            <div id="excelVerifyResults" style="display: none;">
                <!-- Risultati verifica inseriti via JS -->
            </div>
        </div>
    </div>';
    
    return $html;
}

/**
 * CSS per la sezione verifica Excel
 */
function get_excel_verification_css() {
    return '
    <style>
    .excel-verification-section {
        margin: 20px 0;
        padding: 15px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }
    
    .excel-toggle {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .excel-toggle label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }
    
    .template-link {
        color: #667eea;
        text-decoration: none;
        font-size: 13px;
    }
    
    .template-link:hover {
        text-decoration: underline;
    }
    
    #excelVerifyPanel {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e2e8f0;
    }
    
    .excel-upload-area {
        margin-bottom: 15px;
    }
    
    .excel-upload-area input[type="file"] {
        margin-top: 5px;
    }
    
    #excelSheetSelector, #excelColumnMapping {
        margin-top: 15px;
        padding: 15px;
        background: white;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
    }
    
    #excelColumnMapping h4 {
        margin: 0 0 15px 0;
        font-size: 14px;
    }
    
    .column-mapping-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    
    .column-mapping-row label {
        width: 150px;
        font-weight: 500;
    }
    
    .column-mapping-row select {
        flex: 1;
        padding: 8px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
    }
    
    /* Risultati verifica */
    #excelVerifyResults {
        margin-top: 20px;
    }
    
    .verify-summary {
        display: flex;
        gap: 20px;
        margin-bottom: 15px;
    }
    
    .verify-stat {
        padding: 10px 15px;
        border-radius: 6px;
        font-weight: 500;
    }
    
    .verify-stat.ok { background: #d1fae5; color: #065f46; }
    .verify-stat.warning { background: #fef3c7; color: #92400e; }
    .verify-stat.error { background: #fee2e2; color: #991b1b; }
    
    .discrepancy-list {
        max-height: 300px;
        overflow-y: auto;
    }
    
    .discrepancy-item {
        display: flex;
        align-items: center;
        padding: 10px;
        margin-bottom: 8px;
        background: #fffbeb;
        border: 1px solid #fcd34d;
        border-radius: 6px;
    }
    
    .discrepancy-item .question-num {
        font-weight: bold;
        margin-right: 15px;
        min-width: 50px;
    }
    
    .discrepancy-item .values {
        flex: 1;
        display: flex;
        gap: 20px;
    }
    
    .discrepancy-item .word-val, .discrepancy-item .excel-val {
        padding: 3px 8px;
        border-radius: 4px;
        font-family: monospace;
        font-size: 13px;
    }
    
    .discrepancy-item .word-val {
        background: #fee2e2;
    }
    
    .discrepancy-item .excel-val {
        background: #d1fae5;
    }
    
    .discrepancy-item .actions {
        display: flex;
        gap: 5px;
    }
    
    .btn-use-excel, .btn-keep-word {
        padding: 5px 10px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }
    
    .btn-use-excel {
        background: #10b981;
        color: white;
    }
    
    .btn-keep-word {
        background: #6b7280;
        color: white;
    }
    
    .discrepancy-item.resolved {
        background: #f0fdf4;
        border-color: #86efac;
        opacity: 0.7;
    }
    </style>';
}

/**
 * JavaScript per la verifica Excel
 */
function get_excel_verification_js($courseid) {
    return '
    <script>
    var excelVerifyData = {
        sheets: [],
        selectedSheet: null,
        headers: [],
        autoDetect: {}
    };
    
    function toggleExcelVerify() {
        var panel = document.getElementById("excelVerifyPanel");
        var checkbox = document.getElementById("enableExcelVerify");
        panel.style.display = checkbox.checked ? "block" : "none";
    }
    
    function uploadExcelVerify() {
        var fileInput = document.getElementById("excelVerifyFile");
        if (!fileInput.files.length) return;
        
        var formData = new FormData();
        formData.append("excel_file", fileInput.files[0]);
        formData.append("action", "load_excel");
        formData.append("sesskey", M.cfg.sesskey);
        
        fetch("ajax/excel_verify.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                excelVerifyData.sheets = data.sheets;
                showSheetSelector(data.sheets);
            } else {
                alert("Errore: " + data.error);
            }
        })
        .catch(err => alert("Errore caricamento: " + err));
    }
    
    function showSheetSelector(sheets) {
        var selector = document.getElementById("excelSheetSelector");
        var select = document.getElementById("excelSheet");
        
        select.innerHTML = \'<option value="">-- Seleziona foglio --</option>\';
        sheets.forEach(function(sheet, index) {
            select.innerHTML += \'<option value="\' + index + \'">\' + 
                sheet.name + \' (\' + sheet.rows + \' righe)</option>\';
        });
        
        selector.style.display = "block";
    }
    
    function selectExcelSheet() {
        var select = document.getElementById("excelSheet");
        var sheetIndex = select.value;
        
        if (!sheetIndex) {
            document.getElementById("excelColumnMapping").style.display = "none";
            return;
        }
        
        var formData = new FormData();
        formData.append("action", "select_sheet");
        formData.append("sheet_index", sheetIndex);
        formData.append("sesskey", M.cfg.sesskey);
        
        fetch("ajax/excel_verify.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                excelVerifyData.headers = data.headers;
                excelVerifyData.autoDetect = data.auto_detect;
                showColumnMapping(data.headers, data.auto_detect);
            } else {
                alert("Errore: " + data.error);
            }
        });
    }
    
    function showColumnMapping(headers, autoDetect) {
        var panel = document.getElementById("excelColumnMapping");
        var selects = ["colQuestion", "colCompetency", "colAnswer"];
        var autoValues = [autoDetect.question_col, autoDetect.competency_col, autoDetect.answer_col];
        
        selects.forEach(function(selectId, i) {
            var select = document.getElementById(selectId);
            select.innerHTML = \'<option value="">-- Seleziona --</option>\';
            
            headers.forEach(function(header, index) {
                if (header) {
                    var selected = (autoValues[i] === index) ? " selected" : "";
                    select.innerHTML += \'<option value="\' + index + \'"\' + selected + \'>\' + header + \'</option>\';
                }
            });
        });
        
        panel.style.display = "block";
    }
    
    function runExcelVerify() {
        var colQuestion = document.getElementById("colQuestion").value;
        var colCompetency = document.getElementById("colCompetency").value;
        var colAnswer = document.getElementById("colAnswer").value;
        
        if (!colQuestion || !colCompetency) {
            alert("Seleziona almeno le colonne Domanda e Competenza");
            return;
        }
        
        var formData = new FormData();
        formData.append("action", "verify");
        formData.append("col_question", colQuestion);
        formData.append("col_competency", colCompetency);
        formData.append("col_answer", colAnswer);
        formData.append("sesskey", M.cfg.sesskey);
        
        fetch("ajax/excel_verify.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showVerifyResults(data.results);
            } else {
                alert("Errore: " + data.error);
            }
        });
    }
    
    function showVerifyResults(results) {
        var container = document.getElementById("excelVerifyResults");

        var html = \'<div class="verify-summary">\';

        // Mostra match con colore appropriato
        var matchClass = results.matches === results.total_word ? "ok" : (results.matches > 0 ? "warning" : "error");
        html += \'<div class="verify-stat \' + matchClass + \'">✅ \' + results.matches + \'/\' + results.total_word + \' corrispondono</div>\';

        if (results.discrepancies.length > 0) {
            html += \'<div class="verify-stat warning">⚠️ \' + results.discrepancies.length + \' discrepanze</div>\';
        }

        // Mostra warning per domande non trovate in Excel
        if (results.missing_in_excel && results.missing_in_excel.length > 0) {
            html += \'<div class="verify-stat error">❌ \' + results.missing_in_excel.length + \' non trovate in Excel</div>\';
        }
        html += \'</div>\';

        // Mostra competenze mancanti dal DB
        if (results.competency_check && !results.competency_check.all_valid) {
            html += \'<div class="verify-stat warning" style="margin-top: 10px;">⚠️ Competenze non trovate nel DB: \';
            results.competency_check.missing_competencies.forEach(function(c) {
                html += \'<code>\' + c.code + \'</code> \';
            });
            html += \'</div>\';
        } else if (results.competency_check && results.competency_check.all_valid) {
            html += \'<div class="verify-stat ok" style="margin-top: 10px;">🎯 Tutte le \' + results.competency_check.total_valid + \' competenze esistono nel DB</div>\';
        }

        // Mostra elenco domande non trovate in Excel
        if (results.missing_in_excel && results.missing_in_excel.length > 0) {
            html += \'<div class="discrepancy-list" style="margin-top: 15px; background: #fee2e2; padding: 10px; border-radius: 6px;">\';
            html += \'<p><strong>❌ Domande Word non trovate in Excel:</strong></p>\';
            html += \'<p style="font-family: monospace;">\' + results.missing_in_excel.join(\', \') + \'</p>\';
            html += \'<p style="font-size: 12px; color: #666;">Verifica che la colonna "Domanda" dell\\\'Excel contenga gli stessi identificativi del Word (es. Q01, Q02, LOG_APPR01_Q01, ecc.)</p>\';
            html += \'</div>\';
        }

        if (results.discrepancies.length > 0) {
            html += \'<div class="discrepancy-list">\';
            html += \'<p><strong>Risolvi le discrepanze:</strong></p>\';

            results.discrepancies.forEach(function(d) {
                var typeLabel = d.type === "competency" ? "Competenza" : "Risposta";
                html += \'<div class="discrepancy-item" id="disc-\' + d.index + \'-\' + d.type + \'">\';
                html += \'<span class="question-num">\' + d.question + \'</span>\';
                html += \'<span class="type-label">\' + typeLabel + \':</span>\';
                html += \'<span class="values">\';
                html += \'<span class="word-val">Word: \' + d.word_value + \'</span>\';
                html += \'<span class="excel-val">Excel: \' + d.excel_value + \'</span>\';
                html += \'</span>\';
                html += \'<span class="actions">\';
                html += \'<button class="btn-use-excel" onclick="useExcelValue(\' + d.index + \', \\\'\' + d.type + \'\\\', \\\'\' + d.excel_value + \'\\\')">Usa Excel</button>\';
                html += \'<button class="btn-keep-word" onclick="keepWordValue(\' + d.index + \', \\\'\' + d.type + \'\\\')">Mantieni Word</button>\';
                html += \'</span>\';
                html += \'</div>\';
            });

            html += \'</div>\';
            html += \'<p id="discrepancyWarning" class="verify-stat error">❌ Risolvi tutte le discrepanze prima di procedere</p>\';
        } else if (results.missing_in_excel && results.missing_in_excel.length === 0) {
            html += \'<p class="verify-stat ok">✅ Tutte le domande corrispondono!</p>\';
        }

        container.innerHTML = html;
        container.style.display = "block";

        updateConvertButton();
    }
    
    function useExcelValue(index, type, value) {
        // Aggiorna il valore nella sessione
        var formData = new FormData();
        formData.append("action", "use_excel_value");
        formData.append("index", index);
        formData.append("type", type);
        formData.append("value", value);
        formData.append("sesskey", M.cfg.sesskey);
        
        fetch("ajax/excel_verify.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                markResolved(index, type, "excel");
            }
        });
    }
    
    function keepWordValue(index, type) {
        // Segna come risolto senza cambiare
        var formData = new FormData();
        formData.append("action", "keep_word_value");
        formData.append("index", index);
        formData.append("type", type);
        formData.append("sesskey", M.cfg.sesskey);
        
        fetch("ajax/excel_verify.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                markResolved(index, type, "word");
            }
        });
    }
    
    function markResolved(index, type, choice) {
        var item = document.getElementById("disc-" + index + "-" + type);
        if (item) {
            item.classList.add("resolved");
            item.querySelector(".actions").innerHTML = \'<span style="color: #10b981;">✓ \' + 
                (choice === "excel" ? "Usato Excel" : "Mantenuto Word") + \'</span>\';
        }
        
        // Controlla se tutte risolte
        checkAllResolved();
    }
    
    function checkAllResolved() {
        var items = document.querySelectorAll(".discrepancy-item:not(.resolved)");
        var warning = document.getElementById("discrepancyWarning");
        
        if (items.length === 0) {
            if (warning) warning.style.display = "none";
            updateConvertButton();
        }
    }
    
    function updateConvertButton() {
        var btn = document.querySelector(\'button[type="submit"]\');
        var unresolvedItems = document.querySelectorAll(".discrepancy-item:not(.resolved)");
        var enableVerify = document.getElementById("enableExcelVerify");
        
        if (btn) {
            // Se verifica non attiva o tutte risolte, abilita
            if (!enableVerify.checked || unresolvedItems.length === 0) {
                btn.disabled = false;
                btn.style.opacity = "1";
            } else {
                btn.disabled = true;
                btn.style.opacity = "0.5";
            }
        }
    }
    </script>';
}
