<?php
/**
 * Fix Competenze da Nome Domanda (UNIVERSALE) - CON DEBUG
 *
 * Assegna le competenze alle domande estraendo il codice competenza
 * direttamente dal NOME della domanda.
 * 
 * Funziona per TUTTI i settori: CHIMFARM, AUTOMOBILE, MECCANICA, ecc.
 * 
 * Esempio: "CHIM_BASE_Q41 - CHIMFARM_1G_01" → estrae CHIMFARM_1G_01
 *
 * @package    local_competencyxmlimport
 */

// === DEBUG MODE ===
$DEBUG = true; // Imposta a false per disattivare
$debug_log = [];

function debug_log($message, $data = null) {
    global $DEBUG, $debug_log;
    if ($DEBUG) {
        $entry = date('H:i:s') . ' - ' . $message;
        if ($data !== null) {
            $entry .= ' | Data: ' . print_r($data, true);
        }
        $debug_log[] = $entry;
    }
}

define('CLI_SCRIPT', false);

debug_log("🚀 Script avviato");

try {
    require_once(__DIR__ . '/../../config.php');
    debug_log("✅ config.php caricato");
} catch (Exception $e) {
    die("❌ Errore caricamento config.php: " . $e->getMessage());
}

// Parametri
$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'preview', PARAM_ALPHA);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$sector = optional_param('sector', '', PARAM_TEXT);

debug_log("📋 Parametri", [
    'courseid' => $courseid,
    'action' => $action,
    'frameworkid' => $frameworkid,
    'sector' => $sector
]);

// Verifica accesso
try {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    debug_log("✅ Corso trovato", $course->fullname);
} catch (Exception $e) {
    die("❌ Corso non trovato: " . $e->getMessage());
}

try {
    require_login($course);
    debug_log("✅ Login OK");
} catch (Exception $e) {
    die("❌ Errore login: " . $e->getMessage());
}

try {
    $context = context_course::instance($courseid);
    debug_log("✅ Context", $context->id);
} catch (Exception $e) {
    die("❌ Errore context: " . $e->getMessage());
}

try {
    require_capability('moodle/course:manageactivities', $context);
    debug_log("✅ Capability OK");
} catch (Exception $e) {
    die("❌ Permessi insufficienti: " . $e->getMessage());
}

// Setup pagina
$PAGE->set_url('/local/competencyxmlimport/fix_competenze_da_nome.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Fix Competenze da Nome - DEBUG');
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

debug_log("✅ Pagina configurata");

// Settori supportati
$sectors = ['CHIMFARM', 'AUTOMOBILE', 'MECCANICA', 'ELETTRICITA', 'AUTOMAZIONE', 'LOGISTICA', 'METALCOSTRUZIONE'];

// Funzione per estrarre il codice competenza dal nome della domanda
function extract_competency_code($name, $sector = '') {
    global $DEBUG;
    
    // Pattern generico: SETTORE_PROFILO_NUMERO (es. CHIMFARM_1G_01, AUTOMOBILE_MR_A1)
    $patterns = [
        '/\b(CHIMFARM_[A-Z0-9]+_[A-Z0-9]+)\b/i',
        '/\b(AUTOMOBILE_[A-Z]+_[A-Z0-9]+)\b/i',
        '/\b(MECCANICA_[A-Z]+_[A-Z0-9]+)\b/i',
        '/\b(ELETTRICITA_[A-Z]+_[A-Z0-9]+)\b/i',
        '/\b(AUTOMAZIONE_[A-Z]+_[A-Z0-9]+)\b/i',
        '/\b(LOGISTICA_[A-Z]+_[A-Z0-9]+)\b/i',
        '/\b(METALCOSTRUZIONE_[A-Z]+_[A-Z0-9]+)\b/i',
    ];
    
    // Se specificato un settore, cerca prima quello
    if ($sector) {
        $pattern = '/\b(' . preg_quote($sector, '/') . '_[A-Z0-9]+_[A-Z0-9]+)\b/i';
        if (preg_match($pattern, $name, $matches)) {
            return strtoupper($matches[1]);
        }
    }
    
    // Altrimenti prova tutti i pattern
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $name, $matches)) {
            return strtoupper($matches[1]);
        }
    }
    
    return null;
}

// CSS
$css = '
<style>
.fix-page { max-width: 1200px; margin: 0 auto; padding: 20px; }
.fix-header { background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
.fix-header h2 { margin: 0 0 8px 0; }
.panel { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; margin-bottom: 20px; }
.panel h3 { margin: 0 0 15px 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
.stat-card .number { font-size: 28px; font-weight: bold; color: #27ae60; }
.stat-card .label { color: #666; margin-top: 5px; font-size: 13px; }
.question-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.question-table th, .question-table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
.question-table th { background: #f8f9fa; font-weight: 600; }
.question-table tr:hover { background: #f8f9fa; }
.code-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; font-family: monospace; }
.code-new { background: #27ae60; color: white; }
.code-exists { background: #3498db; color: white; }
.code-error { background: #e74c3c; color: white; }
.code-none { background: #95a5a6; color: white; }
.btn { display: inline-block; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500; border: none; cursor: pointer; margin-right: 10px; }
.btn-primary { background: #27ae60; color: white; }
.btn-primary:hover { background: #219a52; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.back-link { display: inline-block; margin-bottom: 20px; color: #27ae60; }
.log-item { padding: 8px 12px; border-left: 4px solid #ddd; margin-bottom: 5px; background: #f8f9fa; font-size: 13px; }
.log-item.success { border-color: #27ae60; }
.log-item.error { border-color: #e74c3c; background: #fdeaea; }
.log-item.skip { border-color: #f39c12; }
select.form-control { padding: 10px; border-radius: 6px; border: 1px solid #ddd; min-width: 250px; }
.form-group { margin-bottom: 15px; display: inline-block; margin-right: 20px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
.info-box { background: #e8f5e9; border: 1px solid #27ae60; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
.warning-box { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
.scroll-table { max-height: 500px; overflow-y: auto; }
.debug-panel { background: #1e1e1e; color: #0f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; }
.debug-panel pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; }
</style>';

echo $OUTPUT->header();
echo $css;

// Carica frameworks disponibili
debug_log("📂 Caricamento frameworks...");
try {
    $frameworks = $DB->get_records('competency_framework', [], 'shortname ASC');
    debug_log("✅ Frameworks trovati", count($frameworks));
} catch (Exception $e) {
    debug_log("❌ Errore caricamento frameworks", $e->getMessage());
    $frameworks = [];
}

// Default framework FTM
if (!$frameworkid) {
    foreach ($frameworks as $fw) {
        if (stripos($fw->shortname, 'FTM') !== false || stripos($fw->idnumber, 'FTM') !== false) {
            $frameworkid = $fw->id;
            debug_log("🎯 Framework FTM auto-selezionato", $fw->shortname);
            break;
        }
    }
}

// Carica TUTTE le competenze dal framework selezionato
$comp_lookup = [];
$sector_counts = [];
if ($frameworkid) {
    debug_log("📂 Caricamento competenze per framework ID: $frameworkid");
    try {
        $competencies = $DB->get_records_sql("
            SELECT id, idnumber, shortname
            FROM {competency}
            WHERE competencyframeworkid = ?
            ORDER BY idnumber
        ", [$frameworkid]);
        debug_log("✅ Competenze trovate", count($competencies));
        
        foreach ($competencies as $c) {
            $comp_lookup[strtoupper($c->idnumber)] = $c->id;
            
            // Conta per settore
            foreach ($sectors as $s) {
                if (stripos($c->idnumber, $s . '_') === 0) {
                    if (!isset($sector_counts[$s])) $sector_counts[$s] = 0;
                    $sector_counts[$s]++;
                    break;
                }
            }
        }
        debug_log("📊 Competenze per settore", $sector_counts);
        
        // Mostra alcune competenze CHIMFARM come esempio
        $chimfarm_sample = [];
        foreach ($comp_lookup as $code => $id) {
            if (strpos($code, 'CHIMFARM_') === 0) {
                $chimfarm_sample[] = $code;
                if (count($chimfarm_sample) >= 10) break;
            }
        }
        debug_log("🔍 Esempio competenze CHIMFARM", $chimfarm_sample);
        
    } catch (Exception $e) {
        debug_log("❌ Errore caricamento competenze", $e->getMessage());
    }
} else {
    debug_log("⚠️ Nessun framework selezionato");
}

// Rileva settore dalle domande se non specificato
if (!$sector) {
    debug_log("🔍 Rilevamento automatico settore...");
    try {
        $sample = $DB->get_records_sql("
            SELECT q.name
            FROM {question} q
            JOIN {question_versions} qv ON qv.questionid = q.id
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
            WHERE qc.contextid = ?
            LIMIT 100
        ", [$context->id]);
        
        debug_log("📋 Domande campione trovate", count($sample));
        
        $detected_sectors = [];
        foreach ($sample as $q) {
            foreach ($sectors as $s) {
                if (stripos($q->name, $s . '_') !== false) {
                    if (!isset($detected_sectors[$s])) $detected_sectors[$s] = 0;
                    $detected_sectors[$s]++;
                }
            }
        }
        
        debug_log("📊 Settori rilevati", $detected_sectors);
        
        if (!empty($detected_sectors)) {
            arsort($detected_sectors);
            $sector = array_key_first($detected_sectors);
            debug_log("🎯 Settore auto-selezionato", $sector);
        }
    } catch (Exception $e) {
        debug_log("❌ Errore rilevamento settore", $e->getMessage());
    }
}

// Carica domande del corso
debug_log("📂 Caricamento domande del corso...");
try {
    $questions = $DB->get_records_sql("
        SELECT q.id, q.name, qc.name as category_name,
               (SELECT GROUP_CONCAT(c.idnumber) FROM {qbank_competenciesbyquestion} qbc
                JOIN {competency} c ON c.id = qbc.competencyid
                WHERE qbc.questionid = q.id) as existing_competencies
        FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        WHERE qc.contextid = ?
        ORDER BY q.name
    ", [$context->id]);
    
    debug_log("✅ Domande trovate", count($questions));
    
    // Mostra prime 5 domande come esempio
    $sample_questions = [];
    $i = 0;
    foreach ($questions as $q) {
        $sample_questions[] = $q->name;
        if (++$i >= 5) break;
    }
    debug_log("🔍 Esempio domande", $sample_questions);
    
} catch (Exception $e) {
    debug_log("❌ Errore caricamento domande", $e->getMessage());
    $questions = [];
}

// Analizza domande
debug_log("🔄 Analisi domande in corso...");
$analysis = [];
$stats = [
    'total' => 0,
    'with_code' => 0,
    'to_assign' => 0,
    'already_assigned' => 0,
    'code_not_found' => 0,
    'no_code' => 0
];

$extraction_examples = [];

foreach ($questions as $q) {
    $stats['total']++;
    
    $code = extract_competency_code($q->name, $sector);
    $q->extracted_code = $code;
    $q->target_competency_id = null;
    $q->status = 'no_code';
    
    // Salva esempi di estrazione (prime 10)
    if (count($extraction_examples) < 10) {
        $extraction_examples[] = [
            'name' => substr($q->name, 0, 60),
            'extracted' => $code ?: '(nessuno)',
            'in_framework' => $code && isset($comp_lookup[$code]) ? 'SÌ' : 'NO'
        ];
    }
    
    if ($code) {
        $stats['with_code']++;
        
        if (isset($comp_lookup[$code])) {
            $q->target_competency_id = $comp_lookup[$code];
            
            // Verifica se già assegnata
            $already = $DB->record_exists('qbank_competenciesbyquestion', [
                'questionid' => $q->id,
                'competencyid' => $q->target_competency_id
            ]);
            
            if ($already) {
                $q->status = 'already_assigned';
                $stats['already_assigned']++;
            } else {
                $q->status = 'to_assign';
                $stats['to_assign']++;
            }
        } else {
            $q->status = 'code_not_found';
            $stats['code_not_found']++;
        }
    } else {
        $stats['no_code']++;
    }
    
    $analysis[] = $q;
}

debug_log("📊 Statistiche analisi", $stats);
debug_log("🔍 Esempi estrazione codici", $extraction_examples);

// === AZIONE: ESEGUI FIX ===
if ($action === 'execute' && $frameworkid) {
    require_sesskey();
    
    debug_log("🚀 ESECUZIONE ASSEGNAZIONE");

    echo '<div class="fix-page">';
    echo '<a href="dashboard.php?courseid='.$courseid.'" class="back-link">← Dashboard</a>';
    echo '<div class="fix-header"><h2>🔧 Assegnazione Competenze in Corso...</h2></div>';

    // Mostra Debug Panel
    if ($DEBUG) {
        echo '<div class="debug-panel"><pre>';
        echo "=== DEBUG LOG ===\n";
        foreach ($debug_log as $log) {
            echo htmlspecialchars($log) . "\n";
        }
        echo '</pre></div>';
    }

    $assigned = 0;
    $errors = 0;
    $skipped = 0;

    echo '<div class="panel"><h3>📋 Log Operazioni</h3><div class="scroll-table">';

    foreach ($analysis as $q) {
        if ($q->status === 'to_assign' && $q->target_competency_id) {
            try {
                $rec = new stdClass();
                $rec->questionid = $q->id;
                $rec->competencyid = $q->target_competency_id;
                $rec->difficultylevel = 1; // Livello base
                $DB->insert_record('qbank_competenciesbyquestion', $rec);

                echo '<div class="log-item success">✅ ' . htmlspecialchars(substr($q->name, 0, 40)) . ' → <strong>' . $q->extracted_code . '</strong></div>';
                $assigned++;
            } catch (Exception $e) {
                echo '<div class="log-item error">❌ ' . htmlspecialchars(substr($q->name, 0, 40)) . ' - Errore: ' . $e->getMessage() . '</div>';
                $errors++;
            }
        } elseif ($q->status === 'already_assigned') {
            $skipped++;
        }
    }

    echo '</div></div>';

    // Riepilogo
    echo '<div class="panel">';
    echo '<h3>🎉 Operazione Completata!</h3>';
    echo '<div class="stats-grid">';
    echo '<div class="stat-card"><div class="number" style="color: #27ae60;">' . $assigned . '</div><div class="label">Competenze Assegnate</div></div>';
    echo '<div class="stat-card"><div class="number" style="color: #3498db;">' . $skipped . '</div><div class="label">Già Presenti (skip)</div></div>';
    echo '<div class="stat-card"><div class="number" style="color: #e74c3c;">' . $errors . '</div><div class="label">Errori</div></div>';
    echo '</div>';
    echo '<a href="fix_competenze_da_nome.php?courseid='.$courseid.'&frameworkid='.$frameworkid.'&sector='.$sector.'" class="btn btn-secondary">🔄 Verifica Risultato</a>';
    echo '<a href="audit_competenze.php?courseid='.$courseid.'" class="btn btn-primary">📊 Vai all\'Audit</a>';
    echo '</div>';

    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

// === PAGINA PREVIEW ===
?>
<div class="fix-page">
    <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="back-link">← Dashboard</a>

    <div class="fix-header">
        <h2>🔍 Fix Competenze da Nome Domanda - DEBUG MODE</h2>
        <p>Estrae automaticamente il codice competenza dal nome della domanda e lo associa</p>
    </div>

    <!-- DEBUG PANEL -->
    <?php if ($DEBUG): ?>
    <div class="debug-panel">
        <pre><strong>=== 🐛 DEBUG LOG ===</strong>
<?php foreach ($debug_log as $log): ?>
<?php echo htmlspecialchars($log); ?>

<?php endforeach; ?>
</pre>
    </div>
    <?php endif; ?>

    <div class="info-box">
        <strong>💡 Come funziona:</strong> Lo script legge il nome di ogni domanda (es. <code>CHIM_BASE_Q41 - CHIMFARM_1G_01</code>), 
        estrae il codice competenza (<code>CHIMFARM_1G_01</code>) e lo associa automaticamente alla domanda.
    </div>

    <!-- Selezione Framework e Settore -->
    <div class="panel">
        <h3>⚙️ Configurazione</h3>
        <form method="get" action="">
            <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
            
            <div class="form-group">
                <label>Framework Competenze:</label>
                <select name="frameworkid" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Seleziona Framework --</option>
                    <?php foreach ($frameworks as $fw): ?>
                    <option value="<?php echo $fw->id; ?>" <?php echo ($frameworkid == $fw->id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($fw->shortname); ?> (ID: <?php echo $fw->id; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Settore (filtro opzionale):</label>
                <select name="sector" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Tutti i settori --</option>
                    <?php foreach ($sectors as $s): 
                        $count = $sector_counts[$s] ?? 0;
                    ?>
                    <option value="<?php echo $s; ?>" <?php echo ($sector == $s) ? 'selected' : ''; ?>>
                        <?php echo $s; ?> (<?php echo $count; ?> competenze)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($frameworkid && count($comp_lookup) > 0): ?>
        <p style="color: #27ae60; margin-top: 15px;">✅ Caricate <strong><?php echo count($comp_lookup); ?></strong> competenze dal framework</p>
        <?php elseif ($frameworkid): ?>
        <p style="color: #e74c3c; margin-top: 15px;">⚠️ Framework selezionato ma <strong>nessuna competenza</strong> trovata!</p>
        <?php endif; ?>
    </div>

    <!-- Statistiche -->
    <div class="panel">
        <h3>📊 Riepilogo Analisi</h3>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $stats['total']; ?></div>
                <div class="label">Domande Totali</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['with_code']; ?></div>
                <div class="label">Con Codice Trovato</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #27ae60;"><?php echo $stats['to_assign']; ?></div>
                <div class="label">Da Assegnare</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #3498db;"><?php echo $stats['already_assigned']; ?></div>
                <div class="label">Già Assegnate</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #e74c3c;"><?php echo $stats['code_not_found']; ?></div>
                <div class="label">Codice Non in Framework</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #95a5a6;"><?php echo $stats['no_code']; ?></div>
                <div class="label">Senza Codice</div>
            </div>
        </div>
    </div>

    <!-- Tabella domande -->
    <div class="panel">
        <h3>📋 Dettaglio Domande (prime 100)</h3>
        <div class="scroll-table">
            <table class="question-table">
                <thead>
                    <tr>
                        <th style="width: 50%;">Nome Domanda</th>
                        <th>Codice Estratto</th>
                        <th>Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $shown = 0;
                    foreach ($analysis as $q): 
                        if ($shown >= 100) break;
                        $shown++;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars(substr($q->name, 0, 60)); ?><?php if (strlen($q->name) > 60) echo '...'; ?></td>
                        <td>
                            <?php if ($q->extracted_code): ?>
                                <code><?php echo $q->extracted_code; ?></code>
                            <?php else: ?>
                                <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($q->status === 'to_assign'): ?>
                                <span class="code-badge code-new">Da Assegnare</span>
                            <?php elseif ($q->status === 'already_assigned'): ?>
                                <span class="code-badge code-exists">Già OK ✓</span>
                            <?php elseif ($q->status === 'code_not_found'): ?>
                                <span class="code-badge code-error">Non trovato</span>
                            <?php else: ?>
                                <span class="code-badge code-none">Nessun codice</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Azioni -->
    <?php if ($stats['to_assign'] > 0 && $frameworkid): ?>
    <div class="panel" style="background: #e8f5e9; border-color: #27ae60;">
        <h3 style="color: #27ae60;">🚀 Pronto per l'Assegnazione!</h3>
        <p>Verranno assegnate <strong><?php echo $stats['to_assign']; ?></strong> competenze alle domande.</p>
        <form method="post" action="fix_competenze_da_nome.php?courseid=<?php echo $courseid; ?>&frameworkid=<?php echo $frameworkid; ?>&sector=<?php echo $sector; ?>&action=execute">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <button type="submit" class="btn btn-success">✅ Esegui Assegnazione (<?php echo $stats['to_assign']; ?> domande)</button>
            <a href="dashboard.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary">Annulla</a>
        </form>
    </div>
    <?php elseif ($stats['to_assign'] == 0 && $stats['already_assigned'] > 0): ?>
    <div class="panel" style="background: #e3f2fd; border-color: #3498db;">
        <h3 style="color: #3498db;">✅ Tutto OK!</h3>
        <p>Tutte le domande con codice valido hanno già le competenze assegnate.</p>
        <a href="audit_competenze.php?courseid=<?php echo $courseid; ?>" class="btn btn-primary">📊 Vai all'Audit</a>
    </div>
    <?php elseif ($stats['code_not_found'] > 0): ?>
    <div class="warning-box">
        <strong>⚠️ Attenzione:</strong> <?php echo $stats['code_not_found']; ?> domande hanno un codice competenza che non esiste nel framework selezionato.
        Verifica che il framework sia corretto o che i codici nelle domande corrispondano.
    </div>
    <?php elseif ($stats['total'] == 0): ?>
    <div class="warning-box">
        <strong>⚠️ Nessuna domanda trovata!</strong> Verifica che il corso abbia domande nella banca domande.
    </div>
    <?php endif; ?>
</div>
<?php

echo $OUTPUT->footer();
