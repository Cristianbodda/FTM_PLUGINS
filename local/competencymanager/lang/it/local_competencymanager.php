<?php
/**
 * Italian strings - Competency Manager
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Competency Manager';
$string['dashboard'] = 'Dashboard';
$string['createquiz'] = 'Crea Quiz';
$string['reports'] = 'Report';
$string['studentreport'] = 'Report Studente';
$string['authorize'] = 'Gestisci Autorizzazioni';
$string['export'] = 'Esporta Dati';
$string['diagnostics'] = 'Diagnostica';

// Sector Admin
$string['sector_admin'] = 'Gestione Settori Studenti';
$string['sector'] = 'Settore';
$string['all_sectors'] = 'Tutti i settori';
$string['all_courses'] = 'Tutti i corsi';
$string['all_cohorts'] = 'Tutte le coorti';
$string['cohort'] = 'Coorte/Gruppo';
$string['search_student'] = 'Cerca studente';
$string['search_placeholder'] = 'Nome, cognome o email...';
$string['date_from'] = 'Da';
$string['date_to'] = 'A';
$string['date_start'] = 'Data Ingresso';
$string['student'] = 'Studente';
$string['primary_sector'] = 'Settore Primario';
$string['detected_sectors'] = 'Settori Rilevati';
$string['no_primary_sector'] = 'Nessun settore primario';
$string['students_found'] = 'Trovati {$a} studenti';
$string['no_students_found'] = 'Nessuno studente trovato';
$string['edit_sector'] = 'Modifica Settore';
$string['sector_saved'] = 'Settore salvato con successo';
$string['sector_removed'] = 'Settore primario rimosso';
$string['invalid_user'] = 'Utente non valido';
$string['invalid_sector'] = 'Settore non valido';
$string['quiz_count'] = '{$a} quiz completati';

// Legenda colori
$string['legend'] = 'Legenda';
$string['color_green'] = '< 2 settimane (nuovo ingresso)';
$string['color_yellow'] = '2-4 settimane (in corso)';
$string['color_orange'] = '4-6 settimane (fine percorso vicina)';
$string['color_red'] = '> 6 settimane (prolungo/ritardo)';
$string['color_gray'] = 'Data non impostata';

// Capabilities
$string['competencymanager:view'] = 'Visualizzare i report competenze';
$string['competencymanager:manage'] = 'Gestire le competenze';
$string['competencymanager:managecoaching'] = 'Gestire il coaching studenti';
$string['competencymanager:assigncoach'] = 'Assegnare studenti ai coach';
$string['competencymanager:managesectors'] = 'Gestire i settori studenti';
$string['competencymanager:evaluate'] = 'Valutare studenti (valutazione formatore)';
$string['competencymanager:viewallevaluations'] = 'Visualizzare tutte le valutazioni formatore';
$string['competencymanager:editallevaluations'] = 'Modificare tutte le valutazioni formatore';
$string['competencymanager:authorizestudentview'] = 'Autorizzare lo studente a vedere la valutazione';

// ============================================================================
// VALUTAZIONE FORMATORE
// ============================================================================

// Titoli pagina e intestazioni
$string['coach_evaluation'] = 'Valutazione Formatore';
$string['coach_evaluation_title'] = 'Valutazione Competenze del Formatore';
$string['evaluation_for'] = 'Valutazione per {$a}';
$string['new_evaluation'] = 'Nuova Valutazione';
$string['edit_evaluation'] = 'Modifica Valutazione';
$string['view_evaluation'] = 'Visualizza Valutazione';
$string['evaluation_history'] = 'Storico Valutazione';
$string['my_evaluations'] = 'Le Mie Valutazioni';

// Stato
$string['status_draft'] = 'Bozza';
$string['status_completed'] = 'Completata';
$string['status_signed'] = 'Firmata (Bloccata)';
$string['evaluation_draft'] = 'Questa valutazione e ancora una bozza e puo essere modificata.';
$string['evaluation_completed'] = 'Questa valutazione e stata completata.';
$string['evaluation_signed'] = 'Questa valutazione e stata firmata e non puo essere modificata.';

// Scala Bloom
$string['bloom_scale'] = 'Scala di Bloom';
$string['bloom_not_observed'] = 'Non Osservato - Nessuna opportunita di valutare questa competenza';
$string['bloom_1_remember'] = 'Ricordare - Richiama fatti, termini, concetti di base';
$string['bloom_2_understand'] = 'Capire - Spiega idee o concetti con parole proprie';
$string['bloom_3_apply'] = 'Applicare - Usa le informazioni in nuove situazioni';
$string['bloom_4_analyze'] = 'Analizzare - Distingue le diverse parti, identifica schemi';
$string['bloom_5_evaluate'] = 'Valutare - Giustifica decisioni, formula giudizi';
$string['bloom_6_create'] = 'Creare - Produce lavoro nuovo, sviluppa soluzioni originali';

// Etichette rating
$string['rating_no'] = 'N/O';
$string['rating_1'] = '1 - Ricordare';
$string['rating_2'] = '2 - Capire';
$string['rating_3'] = '3 - Applicare';
$string['rating_4'] = '4 - Analizzare';
$string['rating_5'] = '5 - Valutare';
$string['rating_6'] = '6 - Creare';
$string['select_rating'] = 'Seleziona voto';

// Form e interfaccia
$string['competency_area'] = 'Area {$a}';
$string['expand_area'] = 'Espandi area {$a}';
$string['collapse_area'] = 'Comprimi area {$a}';
$string['competency'] = 'Competenza';
$string['coach_rating'] = 'Valutazione Coach';
$string['notes'] = 'Note';
$string['notes_placeholder'] = 'Note opzionali per questa competenza...';
$string['general_notes'] = 'Note Generali';
$string['general_notes_help'] = 'Aggiungi osservazioni generali sulla performance complessiva dello studente.';
$string['is_final_week'] = 'Valutazione Finale';
$string['is_final_week_help'] = 'Segna questa come valutazione obbligatoria di fine percorso.';

// Azioni
$string['save_draft'] = 'Salva come Bozza';
$string['save_and_complete'] = 'Salva e Completa';
$string['sign_evaluation'] = 'Firma Valutazione';
$string['sign_confirm'] = 'Sei sicuro? Una valutazione firmata non puo piu essere modificata.';
$string['delete_evaluation'] = 'Elimina Valutazione';
$string['delete_confirm'] = 'Sei sicuro di voler eliminare questa valutazione? Questa azione non puo essere annullata.';
$string['authorize_student'] = 'Autorizza Visualizzazione Studente';
$string['revoke_student'] = 'Revoca Visualizzazione Studente';

// Messaggi
$string['evaluation_saved'] = 'Valutazione salvata con successo.';
$string['evaluation_completed_msg'] = 'Valutazione marcata come completata.';
$string['evaluation_signed_msg'] = 'Valutazione firmata e bloccata.';
$string['evaluation_deleted'] = 'Valutazione eliminata.';
$string['student_authorized'] = 'Lo studente e stato autorizzato a vedere questa valutazione.';
$string['student_revoked'] = 'L\'autorizzazione alla visualizzazione dello studente e stata revocata.';
$string['cannot_edit_signed'] = 'Questa valutazione e firmata e non puo essere modificata.';
$string['no_permission'] = 'Non hai i permessi per eseguire questa azione.';

// Statistiche
$string['competencies_rated'] = '{$a->rated} di {$a->total} competenze valutate';
$string['not_observed_count'] = '{$a} marcate come N/O (Non Osservate)';
$string['average_rating'] = 'Media valutazioni: {$a}';
$string['no_ratings'] = 'Nessuna valutazione ancora';

// Storico
$string['history_created'] = 'Valutazione creata';
$string['history_updated'] = 'Campo "{$a->field}" modificato';
$string['history_deleted'] = 'Valutazione eliminata';
$string['changed_by'] = 'Modificato da {$a}';
$string['on_date'] = 'il {$a}';

// Integrazione report
$string['coach_evaluation_data'] = 'Valutazione Formatore';
$string['show_coach_evaluation'] = 'Mostra Valutazione Formatore';
$string['hide_coach_evaluation'] = 'Nascondi Valutazione Formatore';
$string['no_coach_evaluation'] = 'Nessuna valutazione formatore disponibile per questo studente/settore.';
$string['evaluation_date'] = 'Data valutazione: {$a}';
$string['evaluated_by'] = 'Valutato da: {$a}';

// Validazione
$string['select_sector_first'] = 'Seleziona prima un settore.';
$string['no_competencies_found'] = 'Nessuna competenza trovata per questo settore.';
$string['missing_required_ratings'] = 'Valuta tutte le competenze prima di completare la valutazione.';
