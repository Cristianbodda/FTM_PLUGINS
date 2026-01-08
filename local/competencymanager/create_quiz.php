<?php
/**
 * Create Quiz Wizard - Competency Manager
 * VERSIONE COMPLETA con tutte le funzionalità
 * VERSIONE CON DEBUG per identificare problemi di parsing XML
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once(__DIR__ . '/classes/manager.php');

use local_competencymanager\manager;

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'form', PARAM_ALPHA);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$source = optional_param('source', 'xml', PARAM_ALPHA);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/competencymanager/create_quiz.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Crea Quiz - ' . $course->shortname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

$frameworks = $DB->get_records('competency_framework', [], 'shortname ASC');
$frameworkCompetencies = [];
if ($frameworkid > 0) {
    $frameworkCompetencies = manager::get_framework_competencies($frameworkid);
}

// Get categories
$categories = $DB->get_records('question_categories', ['contextid' => $context->id], 'name ASC');
if (empty($categories)) {
    $defaultcat = new stdClass();
    $defaultcat->name = 'Default per ' . $course->shortname;
    $defaultcat->contextid = $context->id;
    $defaultcat->info = 'Categoria predefinita';
    $defaultcat->infoformat = FORMAT_HTML;
    $defaultcat->stamp = make_unique_id_code();
    $defaultcat->parent = 0;
    $defaultcat->sortorder = 999;
    $defaultcat->id = $DB->insert_record('question_categories', $defaultcat);
    $categories = [$defaultcat->id => $defaultcat];
}

/**
 * Parse XML and extract questions manually (without qformat_xml issues)
 * VERSIONE CON DEBUG
 */
function parse_moodle_xml($xmlcontent, $debug = false) {
    $debugInfo = [];
    
    // DEBUG: Log dimensione contenuto
    $debugInfo['content_length'] = strlen($xmlcontent);
    $debugInfo['content_first_100'] = substr($xmlcontent, 0, 100);
    
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlcontent);
    
    if ($xml === false) {
        $errors = [];
        foreach (libxml_get_errors() as $error) {
            $errors[] = "Linea {$error->line}: " . trim($error->message);
        }
        libxml_clear_errors();
        return [
            'success' => false, 
            'error' => 'XML non valido: ' . implode('; ', $errors),
            'debug' => $debugInfo
        ];
    }
    
    // DEBUG: Log struttura XML
    $debugInfo['xml_root'] = $xml->getName();
    $debugInfo['xml_children_count'] = count($xml->children());
    
    $questions = [];
    $index = 0;
    
    foreach ($xml->question as $q) {
        $type = (string) $q['type'];
        if ($type === 'category') continue;
        
        // Get name - support both <name><text> and <n><text> formats
        $name = '';
        if (isset($q->name->text)) {
            $name = (string) $q->name->text;
        } elseif (isset($q->n->text)) {
            $name = (string) $q->n->text;
        } elseif (isset($q->name)) {
            $name = (string) $q->name;
        } elseif (isset($q->n)) {
            $name = (string) $q->n;
        }
        
        $questiontext = '';
        if (isset($q->questiontext->text)) {
            $questiontext = (string) $q->questiontext->text;
        }
        
        // Extract competency code from name (pattern: SETTORE_AREA_NN)
        // Supporta caratteri accentati (À, È, etc.) e formato con o senza prefisso
        $code = null;
        if (preg_match('/(LOGISTICA_LO_[A-Z0-9]+|AUTOMOBILE_[A-Z]+_[A-Z0-9]+|MECCANICA_[A-Z]+_[A-Z0-9]+|CHIMFARM_[A-Z0-9]+_[A-Z0-9]+|AUTOMAZIONE_[A-Z]+_[A-Z0-9]+|ELETTRICITA_[A-Z]+_[A-Z0-9]+|METALCOSTRUZIONE_[A-Z]+_[A-Z0-9]+)/i', $name, $m)) {
            $code = $m[1];
        }
        
        // Clean preview
        $preview = strip_tags(html_entity_decode($questiontext, ENT_QUOTES, 'UTF-8'));
        $preview = preg_replace('/\s+/', ' ', trim($preview));
        if (strlen($preview) > 100) {
            $preview = substr($preview, 0, 100) . '...';
        }
        
        // Get answers for multichoice
        $answers = [];
        foreach ($q->answer as $ans) {
            $answers[] = [
                'text' => isset($ans->text) ? (string)$ans->text : '',
                'fraction' => (float)$ans['fraction']
            ];
        }
        
        $questions[] = [
            'index' => $index,
            'name' => $name,
            'type' => $type,
            'questiontext' => $questiontext,
            'preview' => $preview,
            'code' => $code,
            'answers' => $answers,
            'defaultgrade' => isset($q->defaultgrade) ? (float)$q->defaultgrade : 1,
            'penalty' => isset($q->penalty) ? (float)$q->penalty : 0.3333333,
            'single' => isset($q->single) ? ((string)$q->single === 'true') : true,
            'shuffleanswers' => isset($q->shuffleanswers) ? ((string)$q->shuffleanswers === 'true' || (string)$q->shuffleanswers === '1') : true
        ];
        $index++;
    }
    
    // DEBUG: Log numero domande
    $debugInfo['questions_parsed'] = count($questions);
    
    $result = ['success' => true, 'questions' => $questions, 'count' => count($questions)];
    
    if ($debug) {
        $result['debug'] = $debugInfo;
    }
    
    return $result;
}

// ============================================================================
// AJAX: Parse XML - VERSIONE CON DEBUG COMPLETO
// ============================================================================
if ($action === 'parsexml' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ========== INIZIO SEZIONE DEBUG ==========
    // Cattura tutti gli errori PHP
    error_reporting(E_ALL);
    ini_set('display_errors', 0);  // Non mostrare errori nell'output
    ini_set('log_errors', 1);      // Ma loggali
    
    // Buffer di output per catturare eventuali errori
    ob_start();
    
    // Variabile per tracciare lo stato
    $debugLog = [
        'step' => 'init',
        'timestamp' => date('Y-m-d H:i:s'),
        'memory_start' => memory_get_usage(true),
    ];
    
    try {
        // ========== FINE SEZIONE DEBUG INIZIALE ==========
        
        header('Content-Type: application/json; charset=utf-8');
        
        $debugLog['step'] = 'check_file';
        
        if (empty($_FILES['xmlfile']['tmp_name'])) {
            $debugLog['error'] = 'No file uploaded';
            ob_end_clean();
            echo json_encode([
                'success' => false, 
                'error' => 'Nessun file caricato',
                'debug' => $debugLog
            ]);
            exit;
        }
        
        // DEBUG: Info sul file caricato
        $debugLog['step'] = 'file_info';
        $debugLog['file_name'] = $_FILES['xmlfile']['name'];
        $debugLog['file_size'] = $_FILES['xmlfile']['size'];
        $debugLog['file_type'] = $_FILES['xmlfile']['type'];
        $debugLog['file_error'] = $_FILES['xmlfile']['error'];
        $debugLog['tmp_name'] = $_FILES['xmlfile']['tmp_name'];
        $debugLog['tmp_exists'] = file_exists($_FILES['xmlfile']['tmp_name']);
        
        // Verifica errori di upload
        if ($_FILES['xmlfile']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'File troppo grande (limite php.ini)',
                UPLOAD_ERR_FORM_SIZE => 'File troppo grande (limite form)',
                UPLOAD_ERR_PARTIAL => 'File caricato parzialmente',
                UPLOAD_ERR_NO_FILE => 'Nessun file caricato',
                UPLOAD_ERR_NO_TMP_DIR => 'Cartella temporanea mancante',
                UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere su disco',
                UPLOAD_ERR_EXTENSION => 'Upload bloccato da estensione PHP',
            ];
            $errorMsg = $uploadErrors[$_FILES['xmlfile']['error']] ?? 'Errore sconosciuto';
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Errore upload: ' . $errorMsg,
                'debug' => $debugLog
            ]);
            exit;
        }
        
        $debugLog['step'] = 'read_file';
        $xmlcontent = file_get_contents($_FILES['xmlfile']['tmp_name']);
        
        if ($xmlcontent === false) {
            $debugLog['error'] = 'Cannot read file';
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Impossibile leggere il file',
                'debug' => $debugLog
            ]);
            exit;
        }
        
        $debugLog['content_length_original'] = strlen($xmlcontent);
        
        // ========== FIX ENCODING UTF-8 ==========
        // Rileva e converti encoding non-UTF-8
        $debugLog['step'] = 'fix_encoding';
        
        // Rimuovi BOM se presente
        if (substr($xmlcontent, 0, 3) === "\xEF\xBB\xBF") {
            $xmlcontent = substr($xmlcontent, 3);
            $debugLog['bom_removed'] = true;
        }
        
        // Verifica se il contenuto è già UTF-8 valido
        if (!mb_check_encoding($xmlcontent, 'UTF-8')) {
            $debugLog['original_encoding'] = 'non-UTF-8';
            
            // Prova a rilevare l'encoding
            $detected = mb_detect_encoding($xmlcontent, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ISO-8859-15'], true);
            $debugLog['detected_encoding'] = $detected ?: 'unknown';
            
            if ($detected && $detected !== 'UTF-8') {
                // Converti a UTF-8
                $xmlcontent = mb_convert_encoding($xmlcontent, 'UTF-8', $detected);
                $debugLog['converted_to'] = 'UTF-8';
            } else {
                // Fallback: forza conversione da Windows-1252 (comune in file da Windows/Excel)
                $xmlcontent = mb_convert_encoding($xmlcontent, 'UTF-8', 'Windows-1252');
                $debugLog['fallback_conversion'] = 'Windows-1252 -> UTF-8';
            }
        } else {
            $debugLog['original_encoding'] = 'UTF-8 (valid)';
        }
        
        // Pulisci caratteri non validi residui
        // Rimuovi caratteri di controllo (eccetto newline, tab, carriage return)
        $xmlcontent = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $xmlcontent);
        
        // Assicurati che i caratteri speciali siano UTF-8 validi
        $xmlcontent = iconv('UTF-8', 'UTF-8//IGNORE', $xmlcontent);
        
        $debugLog['content_length'] = strlen($xmlcontent);
        $debugLog['content_start'] = substr($xmlcontent, 0, 200);
        
        $filename = $_FILES['xmlfile']['name'];
        
        $debugLog['step'] = 'parse_xml';
        $debugLog['memory_before_parse'] = memory_get_usage(true);
        
        // Chiama il parser con debug attivo
        $result = parse_moodle_xml($xmlcontent, true);
        
        $debugLog['memory_after_parse'] = memory_get_usage(true);
        $debugLog['step'] = 'parse_complete';
        
        if (!$result['success']) {
            $debugLog['parse_error'] = $result['error'];
            if (isset($result['debug'])) {
                $debugLog['parse_debug'] = $result['debug'];
            }
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'error' => $result['error'],
                'debug' => $debugLog
            ]);
            exit;
        }
        
        $debugLog['step'] = 'store_session';
        $debugLog['questions_count'] = $result['count'];
        
        // Store in session
        $_SESSION['competencymanager_xml'] = $xmlcontent;
        $_SESSION['competencymanager_xmlfile'] = $filename;
        $_SESSION['competencymanager_questions'] = $result['questions'];
        
        $debugLog['step'] = 'prepare_response';
        $debugLog['memory_end'] = memory_get_usage(true);
        
        // Prepara risposta
        $response = [
            'success' => true,
            'filename' => $filename,
            'count' => $result['count'],
            'questions' => $result['questions']
        ];
        
        // Aggiungi debug info se presente
        if (isset($result['debug'])) {
            $response['parse_debug'] = $result['debug'];
        }
        $response['debug'] = $debugLog;
        
        $debugLog['step'] = 'json_encode';
        
        // Pulisci buffer e invia risposta
        $output = ob_get_clean();
        if (!empty($output)) {
            // C'era output inaspettato (probabilmente errori/warning)
            $response['unexpected_output'] = $output;
        }
        
        // ========== SANITIZZA DATI PER JSON ==========
        // Funzione ricorsiva per pulire stringhe non-UTF-8
        function sanitize_for_json($data) {
            if (is_string($data)) {
                // Converti a UTF-8 e rimuovi caratteri non validi
                if (!mb_check_encoding($data, 'UTF-8')) {
                    $data = mb_convert_encoding($data, 'UTF-8', 'Windows-1252');
                }
                // Rimuovi caratteri non validi UTF-8 residui
                $data = iconv('UTF-8', 'UTF-8//IGNORE', $data);
                // Rimuovi caratteri di controllo
                $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
                return $data;
            } elseif (is_array($data)) {
                return array_map('sanitize_for_json', $data);
            } elseif (is_object($data)) {
                foreach ($data as $key => $value) {
                    $data->$key = sanitize_for_json($value);
                }
                return $data;
            }
            return $data;
        }
        
        // Sanitizza la risposta
        $response = sanitize_for_json($response);
        
        // Prova a codificare in JSON
        $jsonResponse = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        
        if ($jsonResponse === false) {
            // Errore di encoding JSON - prova con flag più permissivo
            $jsonResponse = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            
            if ($jsonResponse === false) {
                // Ultimo tentativo: encoding base senza unicode
                $jsonError = json_last_error_msg();
                echo json_encode([
                    'success' => false,
                    'error' => 'Errore codifica JSON: ' . $jsonError,
                    'debug' => $debugLog
                ], JSON_INVALID_UTF8_IGNORE);
                exit;
            }
        }
        
        echo $jsonResponse;
        exit;
        
    } catch (Exception $e) {
        // Cattura eccezioni standard
        $debugLog['step'] = 'exception';
        $debugLog['exception_message'] = $e->getMessage();
        $debugLog['exception_file'] = $e->getFile();
        $debugLog['exception_line'] = $e->getLine();
        $debugLog['exception_trace'] = $e->getTraceAsString();
        
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Eccezione: ' . $e->getMessage(),
            'debug' => $debugLog
        ]);
        exit;
        
    } catch (Error $e) {
        // Cattura errori fatali PHP 7+
        $debugLog['step'] = 'fatal_error';
        $debugLog['error_message'] = $e->getMessage();
        $debugLog['error_file'] = $e->getFile();
        $debugLog['error_line'] = $e->getLine();
        $debugLog['error_trace'] = $e->getTraceAsString();
        
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Errore fatale PHP: ' . $e->getMessage(),
            'debug' => $debugLog
        ]);
        exit;
    }
}

// ACTION: Create from XML
if ($action === 'createfromxml' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    
    $quizname = required_param('quizname', PARAM_TEXT);
    $quizdescription = optional_param('quizdescription', '', PARAM_RAW);
    $difficultylevel = required_param('difficultylevel', PARAM_INT);
    $categoryid = required_param('categoryid', PARAM_INT);
    $assigncompetencies = optional_param('assigncompetencies', 0, PARAM_INT);
    $selectedindexes = optional_param('selectedindexes', '', PARAM_RAW);
    
    if (empty($_SESSION['competencymanager_questions'])) {
        redirect(new moodle_url('/local/competencymanager/create_quiz.php', ['courseid' => $courseid]),
            'Sessione scaduta. Ricarica il file XML.', null, \core\output\notification::NOTIFY_ERROR);
    }
    
    $xmlfilename = $_SESSION['competencymanager_xmlfile'] ?? 'import.xml';
    $allQuestions = $_SESSION['competencymanager_questions'];
    $selectedIndexes = !empty($selectedindexes) ? json_decode($selectedindexes, true) : [];
    
    // Filter by selected indexes
    $questionsToImport = [];
    if (!empty($selectedIndexes)) {
        foreach ($allQuestions as $q) {
            if (in_array($q['index'], $selectedIndexes)) {
                $questionsToImport[] = $q;
            }
        }
    } else {
        $questionsToImport = $allQuestions;
    }
    
    if (empty($questionsToImport)) {
        redirect(new moodle_url('/local/competencymanager/create_quiz.php', ['courseid' => $courseid]),
            'Nessuna domanda selezionata.', null, \core\output\notification::NOTIFY_ERROR);
    }
    
    // Get competencies lookup
    $compLookup = [];
    if ($frameworkid > 0 && $assigncompetencies) {
        $comps = $DB->get_records('competency', ['competencyframeworkid' => $frameworkid]);
        foreach ($comps as $c) {
            $compLookup[$c->idnumber] = $c;
        }
    }
    
    // Import questions manually
    $questionIds = [];
    $importedCount = 0;
    $assignedCount = 0;
    
    foreach ($questionsToImport as $qdata) {
        // Create question object
        $question = new stdClass();
        $question->category = $categoryid;
        $question->name = $qdata['name'];
        $question->questiontext = $qdata['questiontext'];
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = '';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = $qdata['defaultgrade'];
        $question->penalty = $qdata['penalty'];
        $question->qtype = $qdata['type'];
        $question->length = 1;
        $question->stamp = make_unique_id_code();
        $question->version = make_unique_id_code();
        $question->hidden = 0;
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $USER->id;
        $question->modifiedby = $USER->id;
        
        $question->id = $DB->insert_record('question', $question);
        
        // Create question bank entry
        $entry = new stdClass();
        $entry->questioncategoryid = $categoryid;
        $entry->idnumber = null;
        $entry->ownerid = $USER->id;
        $entry->id = $DB->insert_record('question_bank_entries', $entry);
        
        // Create version
        $version = new stdClass();
        $version->questionbankentryid = $entry->id;
        $version->questionid = $question->id;
        $version->version = 1;
        $version->status = 'ready';
        $DB->insert_record('question_versions', $version);
        
        // Insert answers for multichoice
        if ($qdata['type'] === 'multichoice' && !empty($qdata['answers'])) {
            // Insert multichoice options
            $options = new stdClass();
            $options->questionid = $question->id;
            $options->single = $qdata['single'] ? 1 : 0;
            $options->shuffleanswers = $qdata['shuffleanswers'] ? 1 : 0;
            $options->correctfeedback = '';
            $options->correctfeedbackformat = FORMAT_HTML;
            $options->partiallycorrectfeedback = '';
            $options->partiallycorrectfeedbackformat = FORMAT_HTML;
            $options->incorrectfeedback = '';
            $options->incorrectfeedbackformat = FORMAT_HTML;
            $options->answernumbering = 'abc';
            $options->shownumcorrect = 0;
            $options->showstandardinstruction = 0;
            $DB->insert_record('qtype_multichoice_options', $options);
            
            foreach ($qdata['answers'] as $ans) {
                $answer = new stdClass();
                $answer->question = $question->id;
                $answer->answer = $ans['text'];
                $answer->answerformat = FORMAT_HTML;
                $answer->fraction = $ans['fraction'] / 100; // Convert percentage
                $answer->feedback = '';
                $answer->feedbackformat = FORMAT_HTML;
                $DB->insert_record('question_answers', $answer);
            }
        }
        
        $questionIds[] = $question->id;
        $importedCount++;
        
        // Assign competency
        if ($assigncompetencies && $frameworkid > 0 && !empty($qdata['code'])) {
            if (isset($compLookup[$qdata['code']])) {
                manager::assign_competency($question->id, $compLookup[$qdata['code']]->id, $difficultylevel);
                $assignedCount++;
            }
        }
    }
    
    // Create quiz using Moodle's proper API
    require_once($CFG->dirroot . '/course/modlib.php');
    
    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'quiz';
    $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => 'quiz']);
    $moduleinfo->name = $quizname;
    $moduleinfo->intro = $quizdescription;
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->course = $courseid;
    $moduleinfo->section = 0; // First section
    $moduleinfo->visible = 1;
    $moduleinfo->visibleoncoursepage = 1;
    $moduleinfo->cmidnumber = '';
    $moduleinfo->groupmode = 0;
    $moduleinfo->groupingid = 0;
    
    // Quiz specific settings
    $moduleinfo->timeopen = 0;
    $moduleinfo->timeclose = 0;
    $moduleinfo->timelimit = 0;
    $moduleinfo->overduehandling = 'autosubmit';
    $moduleinfo->graceperiod = 0;
    $moduleinfo->preferredbehaviour = 'deferredfeedback';
    $moduleinfo->canredoquestions = 0;
    $moduleinfo->attempts = 0;
    $moduleinfo->attemptonlast = 0;
    $moduleinfo->grademethod = QUIZ_GRADEHIGHEST;
    $moduleinfo->decimalpoints = 2;
    $moduleinfo->questiondecimalpoints = -1;
    $moduleinfo->reviewattempt = 0x1F111;
    $moduleinfo->reviewcorrectness = 0x1F111;
    $moduleinfo->reviewmarks = 0x1F111;
    $moduleinfo->reviewspecificfeedback = 0x1F111;
    $moduleinfo->reviewgeneralfeedback = 0x1F111;
    $moduleinfo->reviewrightanswer = 0x1F111;
    $moduleinfo->reviewoverallfeedback = 0x1F111;
    $moduleinfo->questionsperpage = 1;
    $moduleinfo->navmethod = 'free';
    $moduleinfo->shuffleanswers = 1;
    $moduleinfo->sumgrades = 0;
    $moduleinfo->grade = 10;
    $moduleinfo->quizpassword = '';
    $moduleinfo->subnet = '';
    $moduleinfo->browsersecurity = '-';
    $moduleinfo->allowofflineattempts = 0;
    
    // Completion settings
    $moduleinfo->completion = 0;
    $moduleinfo->completionview = 0;
    $moduleinfo->completionexpected = 0;
    
    // Create the module
    $moduleinfo = add_moduleinfo($moduleinfo, $course);
    
    $cm = get_coursemodule_from_id('quiz', $moduleinfo->coursemodule, $courseid);
    $quizid = $moduleinfo->instance;
    
    // Add questions to quiz
    $slot = 0;
    foreach ($questionIds as $questionid) {
        $slot++;
        $sql = "SELECT qbe.id as entryid FROM {question_bank_entries} qbe 
                JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id 
                WHERE qv.questionid = ? ORDER BY qv.version DESC LIMIT 1";
        $entry = $DB->get_record_sql($sql, [$questionid]);
        if (!$entry) continue;
        
        $slotdata = new stdClass();
        $slotdata->quizid = $quizid;
        $slotdata->slot = $slot;
        $slotdata->page = $slot;
        $slotdata->maxmark = 1;
        $slotdata->id = $DB->insert_record('quiz_slots', $slotdata);
        
        $ref = new stdClass();
        $ref->usingcontextid = context_module::instance($cm->id)->id;
        $ref->component = 'mod_quiz';
        $ref->questionarea = 'slot';
        $ref->itemid = $slotdata->id;
        $ref->questionbankentryid = $entry->entryid;
        $ref->version = null;
        $DB->insert_record('question_references', $ref);
    }
    
    $DB->set_field('quiz', 'sumgrades', $slot, ['id' => $quizid]);
    rebuild_course_cache($courseid, true);
    
    // Clear session
    unset($_SESSION['competencymanager_xml']);
    unset($_SESSION['competencymanager_xmlfile']);
    unset($_SESSION['competencymanager_questions']);
    
    manager::log_import($courseid, $xmlfilename, $importedCount, 0, $assignedCount, []);
    
    // Show success page
    echo $OUTPUT->header();
    echo '<div style="max-width:700px;margin:40px auto;padding:20px">';
    echo '<a href="dashboard.php?courseid='.$courseid.'" style="color:#28a745;text-decoration:none">← Dashboard</a>';
    echo '<div style="background:linear-gradient(135deg,#d4edda,#c3e6cb);border-radius:12px;padding:30px;margin-top:20px;text-align:center">';
    echo '<div style="font-size:60px;margin-bottom:15px">✅</div>';
    echo '<h2 style="color:#155724;margin:0 0 20px">Quiz Creato con Successo!</h2>';
    echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin:20px 0">';
    echo '<div style="background:#fff;padding:15px;border-radius:8px"><div style="font-size:28px;font-weight:700;color:#28a745">'.$importedCount.'</div><div style="font-size:12px;color:#666">Domande</div></div>';
    echo '<div style="background:#fff;padding:15px;border-radius:8px"><div style="font-size:28px;font-weight:700;color:#17a2b8">'.$assignedCount.'</div><div style="font-size:12px;color:#666">Competenze</div></div>';
    echo '<div style="background:#fff;padding:15px;border-radius:8px"><div style="font-size:28px;font-weight:700;color:#6f42c1">'.str_repeat('⭐',$difficultylevel).'</div><div style="font-size:12px;color:#666">Livello</div></div>';
    echo '</div>';
    echo '<p><strong>Quiz:</strong> '.format_string($quizname).'</p>';
    echo '<p><strong>File:</strong> '.format_string($xmlfilename).'</p>';
    echo '</div>';
    echo '<div style="margin-top:20px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap">';
    echo '<a href="'.$CFG->wwwroot.'/mod/quiz/view.php?id='.$moduleinfo->coursemodule.'" style="background:#28a745;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:500">📝 Vai al Quiz</a>';
    echo '<a href="create_quiz.php?courseid='.$courseid.'&source=xml" style="background:#667eea;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:500">➕ Altro Quiz</a>';
    echo '<a href="dashboard.php?courseid='.$courseid.'" style="background:#6c757d;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:500">🏠 Dashboard</a>';
    echo '</div></div>';
    echo $OUTPUT->footer();
    exit;
}

// AJAX: Preview question
if ($action === 'preview') {
    $questionid = required_param('questionid', PARAM_INT);
    $question = $DB->get_record('question', ['id' => $questionid]);
    header('Content-Type: application/json');
    if ($question) {
        echo json_encode(['success' => true, 'name' => $question->name, 'text' => format_text($question->questiontext, $question->questiontextformat)]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Load questions for course mode
$questions = [];
if ($source === 'course') {
    $questions = manager::get_course_questions($context->id, $frameworkid);
}

$stats = ['total' => count($questions), 'found' => 0, 'not_found' => 0, 'no_code' => 0];
foreach ($questions as $q) {
    if (!empty($q->competency_code)) {
        if ($q->competency_status === 'found') {
            $stats['found']++;
        } else {
            $stats['not_found']++;
        }
    } else {
        $stats['no_code']++;
    }
}

echo $OUTPUT->header();
?>

<style>
.cq-wizard { max-width: 1100px; margin: 0 auto; padding: 20px; }
.cq-header { background: linear-gradient(135deg, #28a745, #20c997); color: #fff; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
.cq-header h2 { margin: 0 0 8px; }
.cq-header p { margin: 0; opacity: 0.9; }
.cq-section { background: #fff; border-radius: 12px; margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; overflow: hidden; }
.cq-section-header { background: #f8f9fa; padding: 15px 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
.cq-section-header:hover { background: #e9ecef; }
.cq-section-header h3 { margin: 0; font-size: 16px; }
.cq-section-content { padding: 20px; }
.cq-section.collapsed .cq-section-content { display: none; }
.source-tabs { display: flex; gap: 5px; margin-bottom: 15px; flex-wrap: wrap; }
.source-tab { padding: 12px 24px; background: #f0f0f0; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 14px; transition: all 0.2s; }
.source-tab:hover { background: #e0e0e0; transform: translateY(-1px); }
.source-tab.active { background: linear-gradient(135deg, #28a745, #20c997); color: #fff; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 500; }
.form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 14px; }
.form-group input:focus, .form-group select:focus { border-color: #28a745; outline: none; box-shadow: 0 0 0 3px rgba(40,167,69,0.1); }
.level-options { display: flex; gap: 15px; flex-wrap: wrap; }
.level-option { flex: 1; min-width: 120px; padding: 20px 15px; border: 2px solid #e0e0e0; border-radius: 12px; cursor: pointer; text-align: center; transition: all 0.2s; }
.level-option:hover { border-color: #28a745; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(40,167,69,0.15); }
.level-option.selected { border-color: #28a745; background: linear-gradient(135deg, #e8f5e9, #c8e6c9); }
.level-option input { display: none; }
.level-option .stars { font-size: 24px; margin-bottom: 5px; }
.level-option .level-name { font-weight: 600; color: #333; }
.stats-box { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 15px 0; }
.stat-item { background: #f8f9fa; padding: 15px; border-radius: 10px; text-align: center; }
.stat-item .number { font-size: 28px; font-weight: 700; }
.stat-item .label { font-size: 11px; color: #666; margin-top: 5px; }
.stat-item.success { background: linear-gradient(135deg, #d4edda, #c3e6cb); }
.stat-item.success .number { color: #155724; }
.stat-item.danger { background: linear-gradient(135deg, #f8d7da, #f5c6cb); }
.stat-item.danger .number { color: #721c24; }
.stat-item.warning { background: linear-gradient(135deg, #fff3cd, #ffeeba); }
.stat-item.warning .number { color: #856404; }

/* Upload Area */
.upload-area { border: 3px dashed #28a745; border-radius: 16px; padding: 50px 30px; text-align: center; background: linear-gradient(135deg, #f8fff8, #e8f5e9); cursor: pointer; transition: all 0.3s; }
.upload-area:hover { background: linear-gradient(135deg, #e8f5e9, #c8e6c9); transform: scale(1.01); }
.upload-area.dragover { background: linear-gradient(135deg, #c8e6c9, #a5d6a7); border-color: #1e7e34; }
.upload-area input[type=file] { display: none; }
.upload-area .icon { font-size: 64px; margin-bottom: 15px; }
.upload-area .title { font-size: 20px; font-weight: 600; color: #28a745; margin-bottom: 8px; }
.upload-area .subtitle { color: #666; font-size: 14px; }

/* XML Result */
.xml-result { margin-top: 20px; padding: 25px; background: linear-gradient(135deg, #d4edda, #c3e6cb); border-radius: 12px; display: none; }
.xml-result.error { background: linear-gradient(135deg, #f8d7da, #f5c6cb); }
.xml-result h4 { margin: 0 0 15px; color: #155724; }
.xml-questions-list { max-height: 400px; overflow-y: auto; background: #fff; border: 1px solid #ddd; border-radius: 10px; margin-top: 15px; }

/* Question Items */
.question-item { display: flex; align-items: flex-start; padding: 15px; border-bottom: 1px solid #eee; gap: 12px; transition: background 0.2s; }
.question-item:last-child { border-bottom: none; }
.question-item:hover { background: #f8f9fa; }
.question-item input[type=checkbox] { margin-top: 4px; width: 20px; height: 20px; cursor: pointer; }
.question-item .q-content { flex: 1; min-width: 0; }
.question-item .q-name { font-weight: 600; margin-bottom: 4px; color: #333; }
.question-item .q-preview { font-size: 13px; color: #666; line-height: 1.4; }
.question-item .q-meta { display: flex; flex-direction: column; gap: 5px; align-items: flex-end; }
.question-item .q-competency { font-size: 11px; padding: 4px 10px; border-radius: 20px; font-weight: 500; }
.question-item .q-competency.found { background: #28a745; color: #fff; }
.question-item .q-competency.no-code { background: #6c757d; color: #fff; }
.question-item .q-type { font-size: 10px; padding: 2px 8px; background: #e9ecef; border-radius: 4px; color: #666; }

/* Selection Counter */
.selection-counter { background: linear-gradient(135deg, #e3f2fd, #bbdefb); padding: 12px 18px; border-radius: 10px; margin: 15px 0; display: flex; justify-content: space-between; align-items: center; }
.selection-counter label { cursor: pointer; display: flex; align-items: center; gap: 8px; }

/* Buttons */
.btn { display: inline-block; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; font-size: 14px; transition: all 0.2s; }
.btn:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
.btn-success { background: linear-gradient(135deg, #28a745, #20c997); color: #fff; }
.btn-secondary { background: linear-gradient(135deg, #6c757d, #5a6268); color: #fff; }
.btn-info { background: linear-gradient(135deg, #17a2b8, #138496); color: #fff; }
.btn-group { display: flex; gap: 12px; margin-top: 25px; flex-wrap: wrap; }
.back-link { display: inline-flex; align-items: center; gap: 5px; margin-bottom: 20px; color: #28a745; text-decoration: none; font-weight: 500; }
.back-link:hover { color: #1e7e34; }

#xmlForm { display: none; }
.loading { text-align: center; padding: 50px; }
.loading .spinner { display: inline-block; width: 50px; height: 50px; border: 4px solid #f3f3f3; border-top: 4px solid #28a745; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

.template-buttons { margin-top: 20px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }

/* DEBUG BOX - Nuovo stile per mostrare info debug */
.debug-box { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px; margin-top: 15px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; }
.debug-box pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; }
</style>

<div class="cq-wizard">
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="back-link">← Torna alla Dashboard</a>
    
    <div class="cq-header">
        <h2>📝 Crea Nuovo Quiz</h2>
        <p>Importa domande da file XML e crea automaticamente il quiz con competenze</p>
    </div>
    
    <!-- SOURCE SELECTION -->
    <div class="cq-section">
        <div class="cq-section-header"><h3>📥 Fonte Domande</h3><span>▼</span></div>
        <div class="cq-section-content">
            <div class="source-tabs">
                <button type="button" class="source-tab <?php echo $source === 'xml' ? 'active' : ''; ?>" onclick="changeSource('xml')">📄 Da File XML</button>
                <button type="button" class="source-tab <?php echo $source === 'course' ? 'active' : ''; ?>" onclick="changeSource('course')">📚 Corso Corrente</button>
            </div>
            
            <?php if ($source === 'xml'): ?>
            <!-- XML UPLOAD MODE -->
            <div id="xmlUploadArea">
                <div class="upload-area" id="dropZone" onclick="document.getElementById('xmlFileInput').click()">
                    <div class="icon">📁</div>
                    <div class="title">Carica File XML</div>
                    <div class="subtitle">Trascina qui il file oppure clicca per selezionare</div>
                    <input type="file" id="xmlFileInput" accept=".xml">
                </div>
                
                <div class="template-buttons">
                    <a href="download_template.php?type=xml" class="btn btn-info" style="padding: 10px 20px;">⬇️ Scarica Template XML</a>
                    <a href="download_template.php?type=csv" class="btn btn-info" style="padding: 10px 20px;">⬇️ Template Livelli CSV</a>
                </div>
                
                <div id="xmlLoading" class="loading" style="display: none;">
                    <div class="spinner"></div>
                    <p style="margin-top: 15px; color: #666;">Analisi del file in corso...</p>
                </div>
                
                <div id="xmlResult" class="xml-result"></div>
                
                <!-- DEBUG BOX - Visibile solo in caso di errore -->
                <div id="debugBox" class="debug-box" style="display: none;">
                    <strong>🔧 Informazioni Debug:</strong>
                    <pre id="debugContent"></pre>
                </div>
            </div>
            
            <!-- FORM FOR XML QUIZ CREATION -->
            <form id="xmlForm" method="post" action="create_quiz.php?courseid=<?php echo $courseid; ?>&frameworkid=<?php echo $frameworkid; ?>&action=createfromxml">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="selectedindexes" id="selectedindexes" value="">
                
                <div class="cq-section" style="margin-top: 20px; border: 2px solid #28a745;">
                    <div class="cq-section-header" style="background: linear-gradient(135deg, #e8f5e9, #c8e6c9);"><h3>⚙️ Configurazione Quiz</h3></div>
                    <div class="cq-section-content">
                        
                        <div class="form-group">
                            <label>📚 Framework Competenze</label>
                            <select name="frameworkid" id="xmlFrameworkSelect">
                                <option value="0">-- Nessun framework (solo import) --</option>
                                <?php foreach ($frameworks as $fw): ?>
                                <option value="<?php echo $fw->id; ?>" <?php echo $frameworkid == $fw->id ? 'selected' : ''; ?>><?php echo format_string($fw->shortname); ?> (<?php echo $DB->count_records('competency', ['competencyframeworkid' => $fw->id]); ?> competenze)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" name="assigncompetencies" value="1" checked style="width: 20px; height: 20px;">
                                🔗 Assegna automaticamente competenze (legge codice dal nome domanda)
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>⭐ Livello Difficoltà</label>
                            <div class="level-options">
                                <label class="level-option selected" onclick="selectLevel(this)">
                                    <input type="radio" name="difficultylevel" value="1" checked>
                                    <div class="stars">⭐</div>
                                    <div class="level-name">Base</div>
                                </label>
                                <label class="level-option" onclick="selectLevel(this)">
                                    <input type="radio" name="difficultylevel" value="2">
                                    <div class="stars">⭐⭐</div>
                                    <div class="level-name">Intermedio</div>
                                </label>
                                <label class="level-option" onclick="selectLevel(this)">
                                    <input type="radio" name="difficultylevel" value="3">
                                    <div class="stars">⭐⭐⭐</div>
                                    <div class="level-name">Avanzato</div>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>📂 Categoria Banco Domande</label>
                            <select name="categoryid">
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat->id; ?>"><?php echo format_string($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>📝 Nome Quiz *</label>
                            <input type="text" name="quizname" id="xmlQuizName" required placeholder="Verrà suggerito automaticamente dal nome del file">
                        </div>
                        
                        <div class="form-group">
                            <label>📋 Descrizione (opzionale)</label>
                            <textarea name="quizdescription" rows="3" placeholder="Inserisci una descrizione per il quiz..."></textarea>
                        </div>
                        
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-success" id="xmlSubmitBtn" disabled>✅ Crea Quiz da XML</button>
                    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">Annulla</a>
                </div>
            </form>
            
            <?php else: ?>
            <!-- COURSE QUESTIONS MODE -->
            <?php if (empty($questions)): ?>
            <div style="text-align: center; padding: 50px; color: #666;">
                <div style="font-size: 64px; margin-bottom: 20px;">📭</div>
                <h3>Nessuna domanda nel corso</h3>
                <p>Il banco domande del corso è vuoto.</p>
                <a href="?courseid=<?php echo $courseid; ?>&source=xml" class="btn btn-success" style="margin-top: 15px;">📄 Importa da File XML</a>
            </div>
            <?php else: ?>
            
            <?php if ($frameworkid > 0): ?>
            <div class="stats-box">
                <div class="stat-item"><div class="number"><?php echo $stats['total']; ?></div><div class="label">Totali</div></div>
                <div class="stat-item success"><div class="number"><?php echo $stats['found']; ?></div><div class="label">✅ Con competenza</div></div>
                <div class="stat-item danger"><div class="number"><?php echo $stats['not_found']; ?></div><div class="label">❌ Non trovata</div></div>
                <div class="stat-item warning"><div class="number"><?php echo $stats['no_code']; ?></div><div class="label">⚪ Senza codice</div></div>
            </div>
            <?php endif; ?>
            
            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 15px;">
                <strong>💡 Suggerimento:</strong> Per creare quiz con tutte le funzionalità, usa "Da File XML" che importa e crea il quiz in un solo passaggio.
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<script>
var courseid = <?php echo $courseid; ?>;
var source = '<?php echo $source; ?>';

function changeSource(s) {
    window.location.href = 'create_quiz.php?courseid=' + courseid + '&source=' + s + '&frameworkid=<?php echo $frameworkid; ?>';
}

function selectLevel(el) {
    document.querySelectorAll('.level-option').forEach(function(e) { 
        e.classList.remove('selected'); 
    });
    el.classList.add('selected');
    el.querySelector('input').checked = true;
}

<?php if ($source === 'xml'): ?>
// XML Upload handling
var dropZone = document.getElementById('dropZone');
var fileInput = document.getElementById('xmlFileInput');
var xmlResult = document.getElementById('xmlResult');
var xmlLoading = document.getElementById('xmlLoading');
var xmlForm = document.getElementById('xmlForm');
var submitBtn = document.getElementById('xmlSubmitBtn');
var uploadArea = document.getElementById('xmlUploadArea');
var debugBox = document.getElementById('debugBox');
var debugContent = document.getElementById('debugContent');

dropZone.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.classList.add('dragover');
});

dropZone.addEventListener('dragleave', function() {
    this.classList.remove('dragover');
});

dropZone.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        handleXmlFile(e.dataTransfer.files[0]);
    }
});

fileInput.addEventListener('change', function() {
    if (this.files.length) {
        handleXmlFile(this.files[0]);
    }
});

function handleXmlFile(file) {
    if (!file.name.toLowerCase().endsWith('.xml')) {
        showError('Seleziona un file XML valido.');
        return;
    }
    
    // Show loading
    xmlLoading.style.display = 'block';
    xmlResult.style.display = 'none';
    dropZone.style.display = 'none';
    debugBox.style.display = 'none';
    
    var fd = new FormData();
    fd.append('xmlfile', file);
    
    fetch('create_quiz.php?courseid=' + courseid + '&action=parsexml', {
        method: 'POST',
        body: fd
    })
    .then(function(response) {
        // DEBUG: Log della risposta raw
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        return response.text().then(function(text) {
            console.log('Response text (primi 500 char):', text.substring(0, 500));
            
            // Prova a parsare come JSON
            try {
                return JSON.parse(text);
            } catch (e) {
                // Se il parsing fallisce, mostra l'errore con il testo ricevuto
                throw new Error('Risposta non JSON. Testo ricevuto: ' + text.substring(0, 200));
            }
        });
    })
    .then(function(data) {
        xmlLoading.style.display = 'none';
        
        // Se c'è info di debug, mostrala
        if (data.debug) {
            console.log('Debug info:', data.debug);
            debugContent.textContent = JSON.stringify(data.debug, null, 2);
            debugBox.style.display = 'block';
        }
        
        if (data.success) {
            showSuccess(data);
            debugBox.style.display = 'none'; // Nascondi debug se successo
        } else {
            showError(data.error || 'Errore nel parsing del file.');
        }
    })
    .catch(function(err) {
        xmlLoading.style.display = 'none';
        console.error('Fetch error:', err);
        showError('Errore di rete: ' + err.message);
        
        // Mostra debug box con info errore
        debugContent.textContent = 'Errore JavaScript:\n' + err.message + '\n\nStack:\n' + err.stack;
        debugBox.style.display = 'block';
    });
}

function showError(msg) {
    dropZone.style.display = 'block';
    xmlResult.innerHTML = '<div style="color:#721c24"><strong>❌ Errore:</strong> ' + msg + '</div>';
    xmlResult.classList.add('error');
    xmlResult.classList.remove('success');
    xmlResult.style.display = 'block';
}

function showSuccess(data) {
    // Suggest quiz name from filename
    document.getElementById('xmlQuizName').value = data.filename.replace('.xml', '').replace(/_/g, ' ');
    
    var html = '<h4>✅ File caricato: ' + escapeHtml(data.filename) + '</h4>';
    html += '<p style="margin-bottom:15px"><strong>' + data.count + '</strong> domande trovate nel file</p>';
    
    // Questions list
    html += '<div class="xml-questions-list">';
    data.questions.forEach(function(q, i) {
        var compClass = q.code ? 'found' : 'no-code';
        var compLabel = q.code || 'Senza codice';
        
        html += '<div class="question-item">';
        html += '<input type="checkbox" checked data-index="' + q.index + '" onchange="updateXmlSelection()">';
        html += '<div class="q-content">';
        html += '<div class="q-name">' + escapeHtml(q.name) + '</div>';
        html += '<div class="q-preview">' + escapeHtml(q.preview) + '</div>';
        html += '</div>';
        html += '<div class="q-meta">';
        html += '<span class="q-competency ' + compClass + '">' + compLabel + '</span>';
        html += '<span class="q-type">' + q.type + '</span>';
        html += '</div>';
        html += '</div>';
    });
    html += '</div>';
    
    // Selection counter
    html += '<div class="selection-counter">';
    html += '<label><input type="checkbox" id="xmlSelectAll" checked onchange="toggleXmlSelectAll()"> Seleziona/Deseleziona tutte</label>';
    html += '<span id="xmlSelectionCount"><strong>' + data.count + '</strong> domande selezionate</span>';
    html += '</div>';
    
    xmlResult.innerHTML = html;
    xmlResult.classList.remove('error');
    xmlResult.style.display = 'block';
    
    // Show form
    xmlForm.style.display = 'block';
    submitBtn.disabled = false;
    
    updateXmlSelection();
}

function toggleXmlSelectAll() {
    var checked = document.getElementById('xmlSelectAll').checked;
    document.querySelectorAll('#xmlResult .question-item input[type="checkbox"]').forEach(function(cb) {
        cb.checked = checked;
    });
    updateXmlSelection();
}

function updateXmlSelection() {
    var selected = [];
    document.querySelectorAll('#xmlResult .question-item input[type="checkbox"]:checked').forEach(function(cb) {
        selected.push(parseInt(cb.dataset.index));
    });
    
    document.getElementById('selectedindexes').value = JSON.stringify(selected);
    document.getElementById('xmlSelectionCount').innerHTML = '<strong>' + selected.length + '</strong> domande selezionate';
    document.getElementById('xmlSubmitBtn').disabled = selected.length === 0;
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
<?php endif; ?>
</script>

<?php
echo $OUTPUT->footer();
