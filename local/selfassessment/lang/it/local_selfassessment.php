<?php
// ============================================
// Self Assessment - Stringhe Italiane
// ============================================

defined('MOODLE_INTERNAL') || die();

// Plugin info
$string['pluginname'] = 'Autovalutazione Competenze';
$string['selfassessment'] = 'Autovalutazione';
$string['selfassessment:complete'] = 'Compilare autovalutazione';
$string['selfassessment:view'] = 'Visualizzare autovalutazioni studenti';
$string['selfassessment:manage'] = 'Gestire abilitazioni studenti';
$string['selfassessment:sendreminder'] = 'Inviare reminder agli studenti';

// Navigation
$string['myassessment'] = 'La mia Autovalutazione';
$string['manageassessments'] = 'Gestione Autovalutazioni';
$string['dashboard'] = 'Dashboard Autovalutazioni';

// Page titles
$string['compile_title'] = 'Autovalutazione Competenze';
$string['manage_title'] = 'Gestione Autovalutazioni';
$string['index_title'] = 'Dashboard Autovalutazioni';

// Instructions
$string['instructions'] = 'Valuta il tuo livello di competenza per ogni area usando la scala di Bloom (1-6).';
$string['instructions_detail'] = 'Sii onesto nella tua autovalutazione. Questo aiuterà il tuo coach a capire dove hai bisogno di supporto.';

// Bloom levels
$string['level1'] = 'RICORDO';
$string['level1_desc'] = 'Conosco i termini base e le informazioni fondamentali';
$string['level2'] = 'COMPRENDO';
$string['level2_desc'] = 'Posso spiegare i concetti con parole mie';
$string['level3'] = 'APPLICO';
$string['level3_desc'] = 'So usare le conoscenze in situazioni standard';
$string['level4'] = 'ANALIZZO';
$string['level4_desc'] = 'So scomporre problemi e identificare le cause';
$string['level5'] = 'VALUTO';
$string['level5_desc'] = 'So giudicare la qualità e scegliere la soluzione migliore';
$string['level6'] = 'CREO';
$string['level6_desc'] = 'So progettare soluzioni nuove o migliorare quelle esistenti';

// Status
$string['status_enabled'] = 'Attiva';
$string['status_disabled'] = 'Disabilitata';
$string['status_completed'] = 'Compilata';
$string['status_pending'] = 'In attesa';
$string['status_never'] = 'Mai compilata';

// Actions
$string['save'] = 'Salva Autovalutazione';
$string['saving'] = 'Salvataggio...';
$string['saved'] = 'Autovalutazione salvata!';
$string['enable'] = 'Abilita';
$string['disable'] = 'Disabilita';
$string['send_reminder'] = 'Invia Reminder';
$string['view_detail'] = 'Vedi Dettaglio';

// Messages
$string['save_success'] = 'La tua autovalutazione è stata salvata con successo.';
$string['save_error'] = 'Errore nel salvataggio. Riprova.';
$string['disabled_message'] = 'L\'autovalutazione è stata disabilitata per il tuo account. Contatta il tuo coach per maggiori informazioni.';
$string['already_completed'] = 'Hai già compilato la tua autovalutazione. Puoi aggiornarla in qualsiasi momento.';
$string['reminder_sent'] = 'Reminder inviato con successo a {$a} studente/i.';
$string['status_changed'] = 'Stato modificato con successo.';

// Dashboard
$string['total_students'] = 'Studenti Totali';
$string['completed_count'] = 'Compilate';
$string['pending_count'] = 'In Attesa';
$string['disabled_count'] = 'Disabilitate';
$string['last_update'] = 'Ultimo Aggiornamento';
$string['no_students'] = 'Nessuno studente trovato.';

// Filters
$string['filter_all'] = 'Tutti';
$string['filter_completed'] = 'Compilate';
$string['filter_pending'] = 'In attesa';
$string['filter_disabled'] = 'Disabilitate';

// Table headers
$string['student'] = 'Studente';
$string['status'] = 'Stato';
$string['completed_date'] = 'Compilata il';
$string['actions'] = 'Azioni';

// Confirmation
$string['confirm_disable'] = 'Sei sicuro di voler disabilitare l\'autovalutazione per questo studente?';
$string['confirm_enable'] = 'Sei sicuro di voler riabilitare l\'autovalutazione per questo studente?';

// Errors
$string['error_notfound'] = 'Studente non trovato.';
$string['error_permission'] = 'Non hai i permessi per eseguire questa azione.';
$string['error_disabled'] = 'L\'autovalutazione è disabilitata per il tuo account.';

// Progress
$string['progress'] = 'Progresso';
$string['areas_completed'] = '{$a->completed} di {$a->total} aree valutate';
$string['completion_percent'] = '{$a}% completato';

// Areas (for display)
$string['area_manutenzione_auto'] = 'Manutenzione Auto';
$string['area_assemblaggio'] = 'Assemblaggio';
$string['area_automazione'] = 'Automazione';
$string['area_cnc'] = 'Controllo Numerico CNC';
$string['area_disegno'] = 'Disegno Tecnico';
$string['area_misurazione'] = 'Misurazione';
$string['area_pianificazione'] = 'Pianificazione';
$string['area_sicurezza'] = 'Sicurezza e Qualità';

// Message providers
$string['messageprovider:reminder'] = 'Reminder autovalutazione dal coach';
$string['messageprovider:assignment'] = 'Nuove competenze assegnate per autovalutazione';

// Notification messages
$string['notification_assignment_subject'] = 'Nuove competenze da autovalutare';
$string['notification_assignment_body'] = 'Ciao {$a->fullname},

dopo aver completato il quiz "{$a->quizname}", ti sono state assegnate {$a->count} nuove competenze da autovalutare.

Accedi alla tua area autovalutazione per compilarle:
{$a->url}

Questa autovalutazione aiuterà il tuo coach a capire dove hai bisogno di supporto.';
$string['notification_assignment_small'] = '{$a->count} nuove competenze da autovalutare';

$string['notification_reminder_subject'] = 'Reminder: Completa la tua autovalutazione';
$string['notification_reminder_body'] = 'Ciao {$a->fullname},

il tuo coach {$a->coachname} ti ricorda di completare la tua autovalutazione delle competenze.

{$a->message}

Accedi qui per compilarla:
{$a->url}';
$string['notification_reminder_small'] = 'Reminder autovalutazione dal coach';

// No competencies
$string['no_competencies'] = 'Nessuna competenza da autovalutare';
$string['no_competencies_desc'] = 'Al momento non hai competenze assegnate per l\'autovalutazione. Completa un quiz con domande associate a competenze per sbloccare questa funzione.';
