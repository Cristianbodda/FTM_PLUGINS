<?php
// ============================================
// Self Assessment - Library Functions
// ============================================
// Integrazione con navigazione Moodle
// ============================================

defined('MOODLE_INTERNAL') || die();

/**
 * Aggiunge link nel menu di navigazione
 * Per STUDENTI: link alla compilazione autovalutazione
 * Per COACH/DOCENTI: link alla dashboard gestione
 */
function local_selfassessment_extend_navigation(global_navigation $navigation) {
    global $USER, $DB;
    
    if (!isloggedin() || isguestuser()) {
        return;
    }
    
    $context = context_system::instance();
    
    // Per gli studenti: mostra "La mia Autovalutazione"
    if (has_capability('local/selfassessment:complete', $context)) {
        // Verifica se l'autovalutazione è abilitata per questo utente
        $status = $DB->get_record('local_selfassessment_status', ['userid' => $USER->id]);
        $is_enabled = !$status || $status->enabled == 1;
        
        if ($is_enabled) {
            $node = $navigation->add(
                get_string('myassessment', 'local_selfassessment'),
                new moodle_url('/local/selfassessment/compile.php'),
                navigation_node::TYPE_CUSTOM,
                null,
                'selfassessment_compile',
                new pix_icon('i/grades', '')
            );
        }
    }
    
    // Per coach/docenti: mostra "Gestione Autovalutazioni"
    if (has_capability('local/selfassessment:view', $context)) {
        $node = $navigation->add(
            get_string('manageassessments', 'local_selfassessment'),
            new moodle_url('/local/selfassessment/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'selfassessment_manage',
            new pix_icon('i/report', '')
        );
    }
}

/**
 * Aggiunge link nelle impostazioni del sito
 */
function local_selfassessment_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    global $PAGE;
    
    if (!has_capability('local/selfassessment:manage', context_system::instance())) {
        return;
    }
    
    if ($settingnode = $settingsnav->find('root', navigation_node::TYPE_SITE_ADMIN)) {
        $node = $settingnode->add(
            get_string('pluginname', 'local_selfassessment'),
            new moodle_url('/local/selfassessment/index.php'),
            navigation_node::TYPE_SETTING
        );
    }
}

/**
 * Ritorna i livelli Bloom con descrizioni
 */
function local_selfassessment_get_bloom_levels() {
    return [
        1 => [
            'nome' => get_string('level1', 'local_selfassessment'),
            'descrizione' => get_string('level1_desc', 'local_selfassessment'),
            'colore' => '#e74c3c',
            'icona' => '🔴'
        ],
        2 => [
            'nome' => get_string('level2', 'local_selfassessment'),
            'descrizione' => get_string('level2_desc', 'local_selfassessment'),
            'colore' => '#e67e22',
            'icona' => '🟠'
        ],
        3 => [
            'nome' => get_string('level3', 'local_selfassessment'),
            'descrizione' => get_string('level3_desc', 'local_selfassessment'),
            'colore' => '#f1c40f',
            'icona' => '🟡'
        ],
        4 => [
            'nome' => get_string('level4', 'local_selfassessment'),
            'descrizione' => get_string('level4_desc', 'local_selfassessment'),
            'colore' => '#27ae60',
            'icona' => '🟢'
        ],
        5 => [
            'nome' => get_string('level5', 'local_selfassessment'),
            'descrizione' => get_string('level5_desc', 'local_selfassessment'),
            'colore' => '#3498db',
            'icona' => '🔵'
        ],
        6 => [
            'nome' => get_string('level6', 'local_selfassessment'),
            'descrizione' => get_string('level6_desc', 'local_selfassessment'),
            'colore' => '#9b59b6',
            'icona' => '🟣'
        ],
    ];
}

/**
 * Verifica se l'autovalutazione è abilitata per un utente
 */
function local_selfassessment_is_enabled($userid) {
    global $DB;
    
    $status = $DB->get_record('local_selfassessment_status', ['userid' => $userid]);
    
    // Default: abilitata (se non c'è record, è abilitata)
    if (!$status) {
        return true;
    }
    
    return (bool) $status->enabled;
}

/**
 * Verifica se l'utente ha già compilato l'autovalutazione
 */
function local_selfassessment_is_completed($userid) {
    global $DB;
    
    $count = $DB->count_records('local_selfassessment', ['userid' => $userid]);
    return $count > 0;
}

/**
 * Ottiene la data dell'ultima autovalutazione
 */
function local_selfassessment_get_last_update($userid) {
    global $DB;
    
    $last = $DB->get_field_sql(
        "SELECT MAX(timemodified) FROM {local_selfassessment} WHERE userid = ?",
        [$userid]
    );
    
    return $last ? $last : null;
}

/**
 * Calcola la percentuale di completamento
 */
function local_selfassessment_get_completion_percent($userid, $total_competencies) {
    global $DB;

    if ($total_competencies == 0) {
        return 0;
    }

    $completed = $DB->count_records('local_selfassessment', ['userid' => $userid]);
    return round(($completed / $total_competencies) * 100);
}

// ============================================
// SISTEMA REMINDER INVASIVI
// ============================================

/**
 * Codici di skip per l'autovalutazione
 */
if (!defined('SELFASSESSMENT_SKIP_TEMP')) {
    define('SELFASSESSMENT_SKIP_TEMP', '6807');      // Skip temporaneo (sessione)
}
if (!defined('SELFASSESSMENT_SKIP_PERMANENT')) {
    define('SELFASSESSMENT_SKIP_PERMANENT', 'FTM');  // Skip definitivo (database)
}

/**
 * Verifica centralizzata: lo studente deve vedere i reminder?
 * Ritorna array con info sullo stato
 *
 * @param int $userid User ID
 * @return array ['should_show' => bool, 'pending_count' => int, 'total_count' => int, 'skip_type' => string|null]
 */
function local_selfassessment_get_reminder_status($userid) {
    global $DB;

    $result = [
        'should_show' => false,
        'pending_count' => 0,
        'total_count' => 0,
        'completed_count' => 0,
        'skip_type' => null,
        'has_permanent_skip' => false
    ];

    // Verifica se ha capability (è uno studente)
    $context = context_system::instance();
    if (!has_capability('local/selfassessment:complete', $context, $userid)) {
        return $result;
    }

    // Verifica se coach/admin (non mostrare reminder)
    if (has_capability('local/selfassessment:view', $context, $userid)) {
        return $result;
    }

    // Verifica se autovalutazione disabilitata per questo utente
    if (!local_selfassessment_is_enabled($userid)) {
        return $result;
    }

    // Verifica skip permanente nel database
    $status = $DB->get_record('local_selfassessment_status', ['userid' => $userid]);
    if ($status && $status->skip_accepted) {
        $result['has_permanent_skip'] = true;
        $result['skip_type'] = 'permanent';
        return $result;
    }

    // Conta competenze assegnate
    $assigned = $DB->get_records('local_selfassessment_assign', ['userid' => $userid]);
    $total = count($assigned);

    if ($total == 0) {
        // Nessuna competenza assegnata
        return $result;
    }

    $result['total_count'] = $total;

    // Conta competenze già valutate
    $assessed = $DB->get_records('local_selfassessment', ['userid' => $userid]);
    $assessed_by_comp = [];
    foreach ($assessed as $a) {
        if ($a->level > 0) {
            $assessed_by_comp[$a->competencyid] = true;
        }
    }

    // Conta pending
    $completed = 0;
    foreach ($assigned as $a) {
        if (isset($assessed_by_comp[$a->competencyid])) {
            $completed++;
        }
    }

    $result['completed_count'] = $completed;
    $result['pending_count'] = $total - $completed;

    // Se ci sono competenze da valutare, mostra reminder
    if ($result['pending_count'] > 0) {
        $result['should_show'] = true;
    }

    return $result;
}

// ============================================
// NOTA: Il callback per l'iniezione HTML è ora in:
// classes/hook_callbacks.php (nuovo sistema hook Moodle 4.3+)
// Registrato in: db/hooks.php
// ============================================
