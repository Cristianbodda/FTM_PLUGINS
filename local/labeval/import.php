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
 * Import template from Excel file
 *
 * @package    local_labeval
 * @copyright  2024 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/lib.php');

// Require login and capability
require_login();
$context = context_system::instance();
require_capability('local/labeval:importtemplates', $context);

// Page setup
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/labeval/import.php'));
$PAGE->set_title(get_string('importtemplate', 'local_labeval'));
$PAGE->set_heading(get_string('importtemplate', 'local_labeval'));
$PAGE->set_pagelayout('standard');

// Handle download example
$downloadexample = optional_param('downloadexample', 0, PARAM_INT);
if ($downloadexample) {
    // Generate example Excel
    require_once($CFG->libdir . '/excellib.class.php');
    
    $filename = 'template_esempio_labeval.xlsx';
    $workbook = new \MoodleExcelWorkbook('-');
    $workbook->send($filename);
    
    $sheet = $workbook->add_worksheet('Comportamenti');
    
    // Headers
    $headers = ['Comportamento osservabile / Indicatore HARD', 'Codice competenza F2', 'Descrizione competenza F2 (estesa)', 'Punteggio massimo relazione (1–3)'];
    $col = 0;
    foreach ($headers as $header) {
        $sheet->write_string(0, $col++, $header);
    }
    
    // Example data
    $examples = [
        ['Identifica correttamente il pezzo da controllare sul disegno', 'MECCANICA_DT_01', 'Legge e interpreta disegni tecnici 2D.', 3],
        ['', 'MECCANICA_MIS_04', 'Interpreta disegni e tolleranze geometriche.', 1],
        ['Sceglie lo strumento adeguato alla quota', 'MECCANICA_MIS_01', 'Utilizza strumenti di misura tradizionali.', 3],
        ['', 'MECCANICA_MIS_02', 'Esegue controlli dimensionali e funzionali.', 1],
        ['Azzera lo strumento prima di misurare', 'MECCANICA_MIS_01', 'Utilizza strumenti di misura tradizionali.', 3],
    ];
    
    $row = 1;
    foreach ($examples as $data) {
        $col = 0;
        foreach ($data as $value) {
            if (is_numeric($value)) {
                $sheet->write_number($row, $col++, $value);
            } else {
                $sheet->write_string($row, $col++, $value);
            }
        }
        $row++;
    }
    
    $workbook->close();
    exit;
}

// Form definition
class import_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;
        
        // Template name
        $mform->addElement('text', 'templatename', get_string('templatename', 'local_labeval'));
        $mform->setType('templatename', PARAM_TEXT);
        $mform->addRule('templatename', get_string('required'), 'required', null, 'client');
        
        // Description
        $mform->addElement('textarea', 'description', get_string('templatedesc', 'local_labeval'), ['rows' => 3]);
        $mform->setType('description', PARAM_TEXT);
        
        // Sector code
        $mform->addElement('text', 'sectorcode', get_string('sectorcode', 'local_labeval'));
        $mform->setType('sectorcode', PARAM_ALPHANUMEXT);
        $mform->setDefault('sectorcode', 'MECCANICA');
        
        // File upload
        $mform->addElement('filepicker', 'excelfile', get_string('selectfile', 'local_labeval'), null, [
            'accepted_types' => ['.xlsx', '.xls']
        ]);
        $mform->addRule('excelfile', get_string('required'), 'required', null, 'client');
        
        // Buttons
        $this->add_action_buttons(true, get_string('importconfirm', 'local_labeval'));
    }
}

$form = new import_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/labeval/templates.php'));
    
} else if ($data = $form->get_data()) {
    // Process import
    require_once($CFG->libdir . '/excellib.class.php');
    
    $content = $form->get_file_content('excelfile');
    $filename = $form->get_new_filename('excelfile');
    
    // Save temp file
    $tempfile = tempnam(sys_get_temp_dir(), 'labeval_import_');
    file_put_contents($tempfile, $content);
    
    try {
        // Use PhpSpreadsheet
        require_once($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');
        
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempfile);
        
        // Try to find the correct sheet - prioritize "Traduttore_per_Comportamenti"
        $sheetNames = $spreadsheet->getSheetNames();
        $sheet = null;
        
        // Look for the behaviors sheet
        foreach ($sheetNames as $name) {
            if (stripos($name, 'Traduttore') !== false || stripos($name, 'Comportament') !== false) {
                $sheet = $spreadsheet->getSheetByName($name);
                break;
            }
        }
        
        // Fallback to first sheet
        if (!$sheet) {
            $sheet = $spreadsheet->getActiveSheet();
        }
        
        $rows = $sheet->toArray();
        
        // Skip header row
        array_shift($rows);
        
        // Create template
        $template = new stdClass();
        $template->name = $data->templatename;
        $template->description = $data->description;
        $template->sectorcode = strtoupper($data->sectorcode);
        $template->status = 'active';
        $template->createdby = $USER->id;
        $template->timecreated = time();
        $template->timemodified = time();
        
        $templateid = $DB->insert_record('local_labeval_templates', $template);
        
        // Process rows according to FTM Excel format:
        // Column A (0): Behavior description (empty = additional competency for previous behavior)
        // Column B (1): Competency code (e.g., MECCANICA_DT_01)
        // Column C (2): Competency description (for reference)
        // Column D (3): Weight (1-3)
        
        $currentbehaviorid = null;
        $behaviorcount = 0;
        $compcount = 0;
        $sortorder = 0;
        
        foreach ($rows as $row) {
            $behaviordesc = trim($row[0] ?? '');
            $compcode = trim($row[1] ?? '');
            $compdesc = trim($row[2] ?? '');
            $weight = intval($row[3] ?? 1);
            
            // Skip empty rows
            if (empty($compcode) && empty($behaviordesc)) {
                continue;
            }
            
            // Validate weight (must be 1 or 3, default to 1)
            if ($weight < 1) $weight = 1;
            if ($weight > 3) $weight = 3;
            if ($weight == 2) $weight = 1; // No "2" in our scale
            
            // If Column A has value, this is a NEW behavior
            if (!empty($behaviordesc)) {
                $behavior = new stdClass();
                $behavior->templateid = $templateid;
                $behavior->description = $behaviordesc;
                $behavior->sortorder = $sortorder++;
                
                $currentbehaviorid = $DB->insert_record('local_labeval_behaviors', $behavior);
                $behaviorcount++;
            }
            
            // Add competency mapping (if we have a behavior and a competency code)
            if ($currentbehaviorid && !empty($compcode)) {
                // Try to find competency in Moodle by idnumber (use IGNORE_MULTIPLE for duplicate idnumbers)
                $competency = $DB->get_record('competency', ['idnumber' => $compcode], '*', IGNORE_MULTIPLE);
                $competencyid = $competency ? $competency->id : 0;
                
                // Check if this mapping already exists (avoid duplicates)
                $existing = $DB->get_record('local_labeval_behavior_comp', [
                    'behaviorid' => $currentbehaviorid,
                    'competencycode' => $compcode
                ], '*', IGNORE_MISSING);
                
                if (!$existing) {
                    $mapping = new stdClass();
                    $mapping->behaviorid = $currentbehaviorid;
                    $mapping->competencyid = $competencyid;
                    $mapping->competencycode = $compcode;
                    $mapping->weight = $weight;
                    
                    $DB->insert_record('local_labeval_behavior_comp', $mapping);
                    $compcount++;
                }
            }
        }
        
        // Cleanup
        unlink($tempfile);
        
        // Success message
        $message = get_string('importsuccess', 'local_labeval', (object)[
            'behaviors' => $behaviorcount,
            'competencies' => $compcount
        ]);
        
        redirect(new moodle_url('/local/labeval/template_view.php', ['id' => $templateid]),
            $message, null, \core\output\notification::NOTIFY_SUCCESS);
        
    } catch (Exception $e) {
        if (file_exists($tempfile)) {
            unlink($tempfile);
        }
        redirect($PAGE->url, get_string('importerror', 'local_labeval', $e->getMessage()),
            null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Output
echo $OUTPUT->header();

// Navigation tabs
$tabs = [
    new tabobject('dashboard', new moodle_url('/local/labeval/index.php'), get_string('dashboard', 'local_labeval')),
    new tabobject('templates', new moodle_url('/local/labeval/templates.php'), get_string('templates', 'local_labeval')),
    new tabobject('assignments', new moodle_url('/local/labeval/assignments.php'), get_string('assignments', 'local_labeval')),
    new tabobject('reports', new moodle_url('/local/labeval/reports.php'), get_string('reports', 'local_labeval')),
];
echo $OUTPUT->tabtree($tabs, 'templates');

echo local_labeval_get_common_styles();
?>

<div class="labeval-container">
    
    <div class="card">
        <div class="card-header info">
            <h2 style="margin: 0;">📥 <?php echo get_string('importtemplate', 'local_labeval'); ?></h2>
            <p style="margin: 10px 0 0; opacity: 0.9;">Importa un template di prova pratica da file Excel</p>
        </div>
        <div class="card-body">
            
            <!-- Instructions -->
            <div class="alert alert-info">
                <h4 style="margin-top: 0;">📋 Formato Excel Richiesto</h4>
                <p>Il file Excel deve avere le seguenti colonne:</p>
                <ol>
                    <li><strong>Comportamento osservabile</strong> - Descrizione del comportamento (lasciare vuoto per aggiungere altre competenze allo stesso comportamento)</li>
                    <li><strong>Codice competenza</strong> - Es. MECCANICA_MIS_01</li>
                    <li><strong>Descrizione competenza</strong> - (opzionale, per riferimento)</li>
                    <li><strong>Peso (1-3)</strong> - 1 = secondario, 3 = principale</li>
                </ol>
                <p>
                    <a href="?downloadexample=1" class="btn btn-success">
                        📥 Scarica Excel di Esempio
                    </a>
                </p>
            </div>
            
            <!-- Form -->
            <?php $form->display(); ?>
            
        </div>
    </div>
    
    <!-- Preview section (will be shown after upload via JS in future) -->
    
</div>

<?php
echo $OUTPUT->footer();
