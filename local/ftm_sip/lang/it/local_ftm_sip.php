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
 * Italian language strings for local_ftm_sip.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin metadata.
$string['pluginname'] = 'FTM Coaching Individualizzato';
$string['pluginname_desc'] = 'Coaching Individualizzato: programma di coaching di 10 settimane con piano d\'azione, diario e KPI.';

// Capabilities.
$string['ftm_sip:view'] = 'Visualizza dati Coaching Individualizzato';
$string['ftm_sip:manage'] = 'Gestisci iscrizioni Coaching Individualizzato';
$string['ftm_sip:edit'] = 'Modifica dati studente Coaching Individualizzato';
$string['ftm_sip:coach'] = 'Accedi alle funzionalita di coaching';
$string['ftm_sip:generatereport'] = 'Genera report Coaching Individualizzato';
$string['ftm_sip:viewown'] = 'Visualizza i propri dati Coaching Individualizzato (studente)';

// Navigation.
$string['sip_manager'] = 'Coaching Individualizzato';
$string['dashboard'] = 'Dashboard';
$string['enrollments'] = 'Iscrizioni';
$string['action_plan'] = 'Piano d\'Azione';
$string['coaching_diary'] = 'Diario Coaching';
$string['appointments'] = 'Appuntamenti';
$string['kpi_overview'] = 'Panoramica KPI';
$string['student_area'] = 'Area Studente';
$string['company_registry'] = 'Registro Aziende';

// Dashboard.
$string['total_enrollments'] = 'Iscrizioni Totali';
$string['active_enrollments'] = 'Iscrizioni Attive';
$string['completed_enrollments'] = 'Iscrizioni Completate';
$string['upcoming_appointments'] = 'Prossimi Appuntamenti';
$string['current_week'] = 'Settimana Corrente';
$string['week_of_10'] = 'Settimana {$a->current} di {$a->total}';
$string['dashboard_title'] = 'Dashboard Coaching Individualizzato';

// Enrollments.
$string['enrollment_title'] = 'Iscrizioni Coaching Individualizzato';
$string['new_enrollment'] = 'Nuova Iscrizione';
$string['edit_enrollment'] = 'Modifica Iscrizione';
$string['enrollment_start'] = 'Data Inizio';
$string['enrollment_end'] = 'Data Fine';
$string['enrollment_end_planned'] = 'Fine Prevista';
$string['enrollment_end_actual'] = 'Fine Effettiva';
$string['enrollment_status'] = 'Stato';
$string['enrollment_coach'] = 'Coach Assegnato';
$string['enrollment_notes'] = 'Note';
$string['enrollment_saved'] = 'Iscrizione salvata con successo';
$string['enrollment_deleted'] = 'Iscrizione eliminata';
$string['enrollment_not_found'] = 'Iscrizione non trovata';
$string['confirm_delete_enrollment'] = 'Sei sicuro di voler eliminare questa iscrizione?';

// Enrollment statuses.
$string['status_active'] = 'Attivo';
$string['status_completed'] = 'Completato';
$string['status_suspended'] = 'Sospeso';
$string['status_cancelled'] = 'Annullato';

// Action plan.
$string['action_plan_title'] = 'Piano d\'Azione';
$string['activation_areas'] = 'Aree di Attivazione';
$string['area_score'] = 'Punteggio (0-6)';
$string['area_notes'] = 'Note Area';
$string['area_objectives'] = 'Obiettivi';
$string['area_actions'] = 'Azioni Pianificate';
$string['area_deadline'] = 'Scadenza';
$string['action_plan_saved'] = 'Piano d\'azione salvato con successo';
$string['action_plan_not_found'] = 'Piano d\'azione non trovato';

// 7 Aree di attivazione (da documento Coaching Individualizzato).
$string['area_professional_strategy'] = 'Strategia Professionale';
$string['area_job_monitoring'] = 'Monitoraggio Annunci';
$string['area_targeted_applications'] = 'Candidature Mirate';
$string['area_unsolicited_applications'] = 'Autocandidature';
$string['area_direct_company_contact'] = 'Contatto Diretto Aziende';
$string['area_personal_network'] = 'Rete Personale e Professionale';
$string['area_intermediaries'] = 'Intermediari del Mercato del Lavoro';

// Descrizioni aree di attivazione.
$string['area_professional_strategy_desc'] = 'Chiarezza della PCI su ruolo, settore e aziende target';
$string['area_job_monitoring_desc'] = 'Capacita di individuare annunci pertinenti (portali, social media, stampa)';
$string['area_targeted_applications_desc'] = 'Risposta a posizioni aperte';
$string['area_unsolicited_applications_desc'] = 'Iniziativa verso aziende senza annunci pubblici';
$string['area_direct_company_contact_desc'] = 'Capacita di attivare contatti diretti';
$string['area_personal_network_desc'] = 'Utilizzo della propria rete di conoscenze';
$string['area_intermediaries_desc'] = 'Utilizzo di URC e agenzie di collocamento';

// Obiettivi default per area.
$string['area_professional_strategy_obj'] = 'Definire un profilo professionale realistico e aziende target';
$string['area_job_monitoring_obj'] = 'Migliorare la capacita di individuare opportunita';
$string['area_targeted_applications_obj'] = 'Aumentare numero e qualita candidature';
$string['area_unsolicited_applications_obj'] = 'Ampliare le opportunita tramite contatto diretto';
$string['area_direct_company_contact_obj'] = 'Favorire il contatto diretto con il mercato del lavoro';
$string['area_personal_network_obj'] = 'Attivare opportunita attraverso contatti';
$string['area_intermediaries_obj'] = 'Migliorare l\'uso degli intermediari';

// Indicatori di verifica per area.
$string['area_professional_strategy_verify'] = 'Elenco aziende target definito';
$string['area_job_monitoring_verify'] = 'Numero annunci analizzati';
$string['area_targeted_applications_verify'] = 'Numero candidature inviate';
$string['area_unsolicited_applications_verify'] = 'Numero aziende contattate';
$string['area_direct_company_contact_verify'] = 'Numero contatti diretti';
$string['area_personal_network_verify'] = 'Opportunita generate';
$string['area_intermediaries_verify'] = 'Numero contatti attivati';

// Scala di attivazione (0-6) - NON Bloom, misura livello attivazione.
$string['score_0'] = 'Non conosce / mai utilizzato';
$string['score_1'] = 'Conoscenza molto limitata';
$string['score_2'] = 'Utilizzo occasionale';
$string['score_3'] = 'Utilizzo minimo ma presente';
$string['score_4'] = 'Utilizzo regolare';
$string['score_5'] = 'Utilizzo attivo e strutturato';
$string['score_6'] = 'Utilizzo strategico e autonomo';

// Fasi roadmap.
$string['phase_1'] = 'Analisi e Orientamento';
$string['phase_1_desc'] = 'Analizzare le competenze e definire il target professionale';
$string['phase_2'] = 'Costruzione della Strategia';
$string['phase_2_desc'] = 'Valutazione livello di attivazione, identificare priorita di sviluppo, attuare misure concordate';
$string['phase_3'] = 'Attivazione della Ricerca';
$string['phase_3_desc'] = 'Ricerca di lavoro strutturata';
$string['phase_4'] = 'Rafforzamento della Strategia';
$string['phase_4_desc'] = 'Migliorare efficacia candidature e ampliare opportunita';
$string['phase_5'] = 'Contatto con il Mercato del Lavoro';
$string['phase_5_desc'] = 'Favorire l\'accesso a colloqui e opportunita concrete';
$string['phase_6'] = 'Consolidamento e Valutazione';
$string['phase_6_desc'] = 'Consolidare i risultati e adattare la strategia';

// Attivazione Coaching Individualizzato.
$string['activate_sip'] = 'Attiva Coaching Ind.';
$string['prepare_sip'] = 'Prepara Piano CI';
$string['sip_activation_motivation'] = 'Motivazione per l\'attivazione Coaching Individualizzato';
$string['sip_start_date'] = 'Data Inizio CI';
$string['sip_badge'] = 'CI';
$string['sip_color'] = '#0891B2';
$string['plan_status_draft'] = 'Bozza';
$string['plan_status_active'] = 'Attivo';
$string['plan_status_frozen'] = 'Baseline Congelata';
$string['student_visibility'] = 'Lo studente puo vedere il proprio piano';
$string['student_visibility_help'] = 'Se abilitato, lo studente puo vedere il piano d\'azione, gli appuntamenti e inserire i KPI';

// Coaching diary.
$string['diary_title'] = 'Diario Coaching';
$string['new_entry'] = 'Nuova Voce';
$string['edit_entry'] = 'Modifica Voce';
$string['entry_date'] = 'Data';
$string['entry_type'] = 'Tipo';
$string['entry_duration'] = 'Durata (minuti)';
$string['entry_summary'] = 'Riepilogo';
$string['entry_details'] = 'Dettagli';
$string['entry_next_steps'] = 'Prossimi Passi';
$string['entry_saved'] = 'Voce del diario salvata con successo';
$string['entry_deleted'] = 'Voce eliminata';
$string['entry_not_found'] = 'Voce non trovata';
$string['confirm_delete_entry'] = 'Sei sicuro di voler eliminare questa voce del diario?';
$string['no_diary_entries'] = 'Nessuna voce nel diario';

// Diary entry types.
$string['type_meeting'] = 'Incontro';
$string['type_phone'] = 'Telefonata';
$string['type_email'] = 'Email';
$string['type_workshop'] = 'Workshop';
$string['type_observation'] = 'Osservazione';
$string['type_other'] = 'Altro';

// Appointments.
$string['appointments_title'] = 'Calendario Appuntamenti';
$string['new_appointment'] = 'Nuovo Appuntamento';
$string['edit_appointment'] = 'Modifica Appuntamento';
$string['appointment_date'] = 'Data';
$string['appointment_time'] = 'Ora';
$string['appointment_duration'] = 'Durata';
$string['appointment_location'] = 'Luogo';
$string['appointment_topic'] = 'Argomento';
$string['appointment_status'] = 'Stato';
$string['appointment_saved'] = 'Appuntamento salvato con successo';
$string['appointment_deleted'] = 'Appuntamento eliminato';
$string['appointment_not_found'] = 'Appuntamento non trovato';
$string['confirm_delete_appointment'] = 'Sei sicuro di voler eliminare questo appuntamento?';
$string['no_appointments'] = 'Nessun appuntamento programmato';

// Appointment statuses.
$string['appt_scheduled'] = 'Programmato';
$string['appt_confirmed'] = 'Confermato';
$string['appt_completed'] = 'Completato';
$string['appt_cancelled'] = 'Annullato';
$string['appt_no_show'] = 'Assente';

// KPIs.
$string['kpi_title'] = 'Indicatori Chiave di Prestazione';
$string['kpi_applications'] = 'Candidature Inviate';
$string['kpi_company_contacts'] = 'Contatti Aziendali';
$string['kpi_opportunities'] = 'Opportunita Identificate';
$string['kpi_interviews'] = 'Colloqui Ottenuti';
$string['kpi_trials'] = 'Giornate di Prova';
$string['kpi_offers'] = 'Offerte di Lavoro';
$string['kpi_period'] = 'Periodo';
$string['kpi_weekly'] = 'Questa Settimana';
$string['kpi_total'] = 'Totale';
$string['kpi_target'] = 'Obiettivo';
$string['kpi_actual'] = 'Effettivo';
$string['kpi_saved'] = 'KPI salvati con successo';
$string['kpi_trend_up'] = 'In crescita';
$string['kpi_trend_down'] = 'In calo';
$string['kpi_trend_stable'] = 'Stabile';
$string['add_kpi_entry'] = 'Aggiungi Voce KPI';
$string['kpi_week_label'] = 'Settimana {$a}';

// Student self-service area.
$string['student_area_title'] = 'La Mia Area Coaching';
$string['my_action_plan'] = 'Il Mio Piano d\'Azione';
$string['my_appointments'] = 'I Miei Appuntamenti';
$string['my_kpis'] = 'I Miei KPI';
$string['my_progress'] = 'I Miei Progressi';
$string['next_appointment'] = 'Prossimo Appuntamento';
$string['no_next_appointment'] = 'Nessun appuntamento in programma';
$string['progress_week'] = 'Settimana {$a->current} di {$a->total}';
$string['progress_percentage'] = '{$a}% completato';
$string['student_notes'] = 'Le Mie Note';
$string['student_notes_placeholder'] = 'Scrivi qui le tue note e riflessioni...';
$string['student_notes_saved'] = 'Note salvate con successo';
$string['submit_kpi'] = 'Invia KPI Settimanali';
$string['kpi_submitted'] = 'KPI settimanali inviati con successo';

// Company registry.
$string['company_registry_title'] = 'Registro Aziende';
$string['new_company'] = 'Nuova Azienda';
$string['edit_company'] = 'Modifica Azienda';
$string['company_name'] = 'Nome Azienda';
$string['company_sector'] = 'Settore';
$string['company_address'] = 'Indirizzo';
$string['company_city'] = 'Citta';
$string['company_phone'] = 'Telefono';
$string['company_email'] = 'Email';
$string['company_website'] = 'Sito Web';
$string['company_contact_person'] = 'Persona di Riferimento';
$string['company_contact_role'] = 'Ruolo Contatto';
$string['company_notes'] = 'Note';
$string['company_last_contact'] = 'Ultimo Contatto';
$string['company_status'] = 'Stato';
$string['company_saved'] = 'Azienda salvata con successo';
$string['company_deleted'] = 'Azienda eliminata';
$string['company_not_found'] = 'Azienda non trovata';
$string['confirm_delete_company'] = 'Sei sicuro di voler eliminare questa azienda?';
$string['no_companies'] = 'Nessuna azienda registrata';
$string['search_companies'] = 'Cerca aziende...';
$string['error_already_enrolled'] = 'Lo studente e gia iscritto al programma Coaching Individualizzato';

// Company statuses.
$string['company_status_prospect'] = 'Potenziale';
$string['company_status_contacted'] = 'Contattata';
$string['company_status_interested'] = 'Interessata';
$string['company_status_collaborating'] = 'In Collaborazione';
$string['company_status_inactive'] = 'Inattiva';

// Fields - Personal data.
$string['firstname'] = 'Nome';
$string['lastname'] = 'Cognome';
$string['email'] = 'Email';
$string['phone'] = 'Telefono';
$string['coach'] = 'Coach';

// Common actions.
$string['save'] = 'Salva';
$string['cancel'] = 'Annulla';
$string['delete'] = 'Elimina';
$string['edit'] = 'Modifica';
$string['view'] = 'Visualizza';
$string['back'] = 'Indietro';
$string['search'] = 'Cerca';
$string['filter'] = 'Filtra';
$string['export'] = 'Esporta';
$string['print'] = 'Stampa';
$string['close'] = 'Chiudi';
$string['confirm'] = 'Conferma';
$string['reset_filters'] = 'Reimposta Filtri';

// Export.
$string['export_word'] = 'Esporta in Word';
$string['export_pdf'] = 'Esporta in PDF';
$string['export_excel'] = 'Esporta in Excel';
$string['export_success'] = 'Esportazione completata con successo';
$string['export_error'] = 'Errore durante l\'esportazione';

// Messages.
$string['error_save_failed'] = 'Salvataggio fallito';
$string['error_permission'] = 'Non hai i permessi per eseguire questa azione';
$string['error_invalid_data'] = 'Dati non validi';
$string['error_missing_required'] = 'Campi obbligatori mancanti: {$a}';
$string['error_student_not_found'] = 'Studente non trovato';
$string['error_date_invalid'] = 'Formato data non valido';
$string['error_score_range'] = 'Il punteggio deve essere compreso tra 0 e 6';
$string['success_saved'] = 'Dati salvati con successo';
$string['confirm_action'] = 'Sei sicuro di voler procedere?';
$string['loading'] = 'Caricamento in corso...';
$string['no_data'] = 'Nessun dato disponibile';
$string['click_to_edit'] = 'Clicca per modificare';

// Inline editing.
$string['field_saved'] = 'Campo salvato con successo';
$string['field_save_error'] = 'Errore nel salvataggio';

// Notifications.
$string['notification_appointment_reminder'] = 'Promemoria: hai un appuntamento il {$a->date} alle {$a->time}';
$string['notification_kpi_reminder'] = 'Promemoria: inserisci i tuoi KPI settimanali';
$string['notification_plan_update'] = 'Il tuo piano d\'azione e stato aggiornato dal coach';

// Message providers.
$string['messageprovider:appointment_reminder'] = 'Promemoria appuntamenti Coaching Ind.';
$string['messageprovider:appointment_created'] = 'Notifiche nuovo appuntamento CI';
$string['messageprovider:plan_updated'] = 'Notifiche aggiornamento piano CI';
$string['messageprovider:action_reminder'] = 'Promemoria azioni CI';
$string['messageprovider:student_inactivity'] = 'Avvisi inattivita studente CI';
$string['messageprovider:meeting_not_logged'] = 'Promemoria incontri non registrati CI';

// Reports.
$string['report_title'] = 'Report Coaching Individualizzato';
$string['report_summary'] = 'Report di Sintesi';
$string['report_progress'] = 'Report Progressi';
$string['report_kpi_chart'] = 'Grafico KPI';
$string['report_area_radar'] = 'Radar Aree di Attivazione';
$string['report_generated'] = 'Report generato il {$a}';

// Stringhe pagina studente Coaching Individualizzato.
$string['sip_student_initial_level'] = 'Livello Iniziale';
$string['sip_student_current_level'] = 'Livello Attuale';
$string['sip_student_in_development'] = 'In fase di sviluppo';
$string['sip_student_objectives_met'] = 'Obiettivi raggiunti';
$string['sip_student_phase'] = 'Fase {$a}';
$string['sip_student_phase_notes_placeholder'] = 'Note per questa fase...';
$string['sip_student_verification'] = 'Indicatore di Verifica';
$string['sip_student_weeks_label'] = 'Settimane {$a}';

// Diario e incontri.
$string['meeting_date'] = 'Data Incontro';
$string['meeting_duration'] = 'Durata (min)';
$string['meeting_modality'] = 'Modalita';
$string['meeting_modality_presence'] = 'In Presenza';
$string['meeting_modality_remote'] = 'Da Remoto';
$string['meeting_modality_phone'] = 'Telefonata';
$string['meeting_modality_email'] = 'Email';
$string['meeting_summary'] = 'Riepilogo';
$string['meeting_notes'] = 'Note Coach';
$string['meeting_sip_week'] = 'Settimana CI';
$string['meeting_saved'] = 'Incontro salvato con successo';
$string['meeting_deleted'] = 'Incontro eliminato';
$string['new_meeting'] = 'Registra Nuovo Incontro';
$string['no_meetings'] = 'Nessun incontro registrato';
$string['meeting_actions_title'] = 'Azioni Assegnate';
$string['meeting_previous_actions'] = 'Stato Azioni Precedenti';

// Azioni.
$string['action_description'] = 'Descrizione Azione';
$string['action_deadline'] = 'Scadenza';
$string['action_status'] = 'Stato';
$string['action_status_pending'] = 'In Attesa';
$string['action_status_in_progress'] = 'In Corso';
$string['action_status_completed'] = 'Completata';
$string['action_status_not_done'] = 'Non Svolta';
$string['action_saved'] = 'Azione salvata';
$string['add_action'] = 'Aggiungi Azione';
$string['no_pending_actions'] = 'Nessuna azione in sospeso';

// Calendario appuntamenti.
$string['appointment_modality_presence'] = 'In Presenza';
$string['appointment_modality_remote'] = 'Da Remoto';
$string['appointment_modality_phone'] = 'Telefonata';
$string['appointment_saved_notification'] = 'Appuntamento creato. Lo studente sara notificato.';
$string['appointment_confirm_delete'] = 'Eliminare questo appuntamento?';
$string['today'] = 'Oggi';
$string['this_week'] = 'Questa Settimana';
$string['no_upcoming'] = 'Nessun appuntamento in programma';
$string['past_appointments'] = 'Appuntamenti Passati';

// Idoneita - Griglia Valutazione PCI (6 criteri, scala 1-5).
$string['eligibility_title'] = 'Griglia Valutazione PCI';
$string['eligibility_section'] = 'Valutazione PCI';
$string['eligibility_criterion_motivazione'] = 'Motivazione';
$string['eligibility_criterion_chiarezza'] = 'Chiarezza Obiettivo';
$string['eligibility_criterion_occupabilita'] = 'Occupabilita';
$string['eligibility_criterion_autonomia'] = 'Autonomia';
$string['eligibility_criterion_bisogno_coaching'] = 'Bisogno Coaching';
$string['eligibility_criterion_comportamento'] = 'Comportamento';
$string['eligibility_desc_motivazione_1'] = 'passivo / poco coinvolto';
$string['eligibility_desc_motivazione_3'] = 'partecipa ma senza iniziativa';
$string['eligibility_desc_motivazione_5'] = 'proattivo, coinvolto, orientato al risultato';
$string['eligibility_desc_chiarezza_1'] = 'nessun obiettivo';
$string['eligibility_desc_chiarezza_3'] = 'obiettivo generico';
$string['eligibility_desc_chiarezza_5'] = 'obiettivo chiaro e realistico';
$string['eligibility_desc_occupabilita_1'] = 'molto bassa';
$string['eligibility_desc_occupabilita_3'] = 'media';
$string['eligibility_desc_occupabilita_5'] = 'alta (profilo spendibile rapidamente)';
$string['eligibility_desc_autonomia_1'] = 'totalmente dipendente';
$string['eligibility_desc_autonomia_3'] = 'parzialmente autonomo';
$string['eligibility_desc_autonomia_5'] = 'autonomo e organizzato';
$string['eligibility_desc_bisogno_coaching_1'] = 'non necessario';
$string['eligibility_desc_bisogno_coaching_3'] = 'utile ma non essenziale';
$string['eligibility_desc_bisogno_coaching_5'] = 'altamente necessario per sblocco';
$string['eligibility_desc_comportamento_1'] = 'assenze / scarso impegno';
$string['eligibility_desc_comportamento_3'] = 'adeguato';
$string['eligibility_desc_comportamento_5'] = 'eccellente (puntuale, attivo, collaborativo)';
$string['eligibility_totale'] = 'Totale';
$string['eligibility_decisione'] = 'Decisione';
$string['eligibility_decisione_idoneo'] = 'Idoneo';
$string['eligibility_decisione_non_idoneo'] = 'Non Idoneo';
$string['eligibility_decisione_pending'] = 'In attesa';
$string['eligibility_note'] = 'Note';
$string['eligibility_recommendation'] = 'Raccomandazione coach (consultiva)';
$string['eligibility_recommend_activate'] = 'Attivare Coaching Ind.';
$string['eligibility_recommend_not_activate'] = 'Non attivare';
$string['eligibility_recommend_refer'] = 'Rinvio ad altra misura';
$string['eligibility_referral_detail'] = 'Rinvio a';
$string['eligibility_scale_hint'] = 'Valutare ogni criterio da 1 (minimo) a 5 (massimo)';
$string['eligibility_activation_section'] = 'Attivazione Coaching Individualizzato';
$string['eligibility_save_only'] = 'Salva solo valutazione';
$string['eligibility_save_and_activate'] = 'Salva e Attiva CI';
$string['eligibility_saved'] = 'Valutazione PCI salvata';
$string['eligibility_summary'] = 'Valutazione PCI';
$string['eligibility_approved'] = 'Approvato dalla segreteria';
$string['eligibility_approved_msg'] = 'Idoneita approvata';

// Closure.
$string['closure_title'] = 'Chiusura Coaching Individualizzato';
$string['closure_warning'] = 'Chiusura Coaching Individualizzato';
$string['closure_outcome'] = 'Esito finale';
$string['closure_outcome_hired'] = 'Assunto';
$string['closure_outcome_stage'] = 'Stage/Tirocinio';
$string['closure_outcome_training'] = 'Formazione Ulteriore';
$string['closure_outcome_interrupted'] = 'Interrotto';
$string['closure_outcome_not_suitable'] = 'Non idoneo';
$string['closure_outcome_none'] = 'Nessun esito';
$string['closure_company'] = 'Azienda (se applicabile)';
$string['closure_company_placeholder'] = 'Nome azienda...';
$string['closure_date'] = 'Data esito';
$string['closure_percentage'] = '% impiego (se assunto)';
$string['closure_interruption_reason'] = 'Motivo interruzione';
$string['closure_interruption_placeholder'] = 'Descrivere il motivo dell\'interruzione...';
$string['closure_referral'] = 'Rinvio ad altra misura';
$string['closure_referral_placeholder'] = 'Specificare la misura di rinvio...';
$string['closure_coach_evaluation'] = 'Valutazione finale coach';
$string['closure_coach_evaluation_placeholder'] = 'Valutazione complessiva del percorso Coaching Individualizzato dello studente (min. 100 caratteri)...';
$string['closure_next_steps'] = 'Prossimi passi';
$string['closure_next_steps_placeholder'] = 'Raccomandazioni per il proseguimento...';
$string['closure_validation'] = 'Validazione';
$string['closure_confirm_btn'] = 'Completa Percorso';
$string['closure_cancel'] = 'Annulla';
$string['closure_complete_sip'] = 'Completa Percorso';
$string['closure_saved'] = 'Chiusura Coaching Individualizzato completata';
$string['closure_select_outcome'] = 'Seleziona esito...';

// Quality indicators.
$string['quality_baseline_levels'] = 'Livelli baseline';
$string['quality_baseline_complete'] = '{$a}/7 livelli impostati';
$string['quality_meetings_count'] = 'Incontri registrati';
$string['quality_meetings_info'] = '{$a} incontri (min. 3)';
$string['quality_kpi_entries'] = 'Voci KPI';
$string['quality_kpi_info'] = '{$a} voci KPI registrate';
$string['quality_meeting_frequency'] = 'Frequenza incontri';
$string['quality_frequency_info'] = '{$a->ratio} incontri/settimana';
$string['quality_check_ok'] = 'Completato';
$string['quality_check_partial'] = 'Parziale';
$string['quality_check_missing'] = 'Mancante';

// Valutazione idoneita - stringhe legacy/compatibilita (mantenute per report).
$string['eligibility_referral'] = 'Rinvio a';
$string['eligibility_recommend_not'] = 'Non attivare';
$string['level_high'] = 'Alto';
$string['level_medium'] = 'Medio';
$string['level_low'] = 'Basso';
$string['sector_yes'] = 'Si';
$string['sector_partial'] = 'Parziale';
$string['sector_no'] = 'No';

// Chiusura / Esito - stringhe aggiuntive.
$string['closure_outcome_tryout'] = 'Giorno di prova / Tryout';
$string['closure_outcome_intermediate'] = 'Guadagno intermedio';
$string['closure_outcome_not_placed'] = 'Non collocato ma maggiore attivazione';
$string['closure_complete'] = 'Completa Percorso';
$string['closure_validation_error'] = 'Impossibile chiudere il percorso. Mancano: {$a}';
$string['closure_missing_levels'] = 'livelli di attivazione finali per tutte le 7 aree';
$string['closure_missing_meetings'] = 'minimo 3 incontri registrati';
$string['closure_missing_outcome'] = 'selezione esito finale';
$string['closure_missing_evaluation'] = 'valutazione finale del coach';

// Indicatori qualita - stringhe aggiuntive.
$string['quality_complete'] = 'Completo';
$string['quality_partial'] = 'Parziale';
$string['quality_missing'] = 'Mancante';
$string['quality_baseline_incomplete'] = 'Livelli baseline incompleti';
$string['quality_meetings_low'] = 'Pochi incontri registrati';
$string['quality_no_kpi'] = 'Nessuna voce KPI';
$string['quality_meetings_per_week'] = '{$a} incontri/settimana';
$string['quality_alert_no_meeting'] = 'Nessun incontro registrato questa settimana per {$a}';

// Statistiche aggregate.
$string['stats_title'] = 'Statistiche Coaching Individualizzato';
$string['stats_activated'] = 'CI Attivati';
$string['stats_completed'] = 'Completati';
$string['stats_interrupted'] = 'Interrotti';
$string['stats_completion_rate'] = 'Tasso Completamento';
$string['stats_insertion_rate'] = 'Tasso Inserimento';
$string['stats_outcome_distribution'] = 'Distribuzione Esiti';
$string['stats_level_evolution'] = 'Evoluzione Media Livelli di Attivazione';
$string['stats_coach_performance'] = 'Performance Coach';
$string['stats_no_completed'] = 'Nessun percorso completato nel periodo selezionato';
$string['stats_no_coach_data'] = 'Nessun dato coach nel periodo selezionato';

// Qualita dati - quality_checker.
$string['quality_missing_enrollment'] = 'Iscrizione non trovata';
$string['quality_missing_final_levels'] = 'Livelli finali incompleti (tutte le 7 aree richieste)';
$string['quality_missing_meetings'] = 'Incontri insufficienti (minimo 3 richiesti)';
$string['quality_missing_outcome'] = 'Esito non selezionato';
$string['quality_missing_evaluation'] = 'Valutazione finale del coach mancante';

// Message provider: meeting frequency alert.
$string['messageprovider:meeting_frequency_alert'] = 'Avvisi frequenza incontri CI';

// Risultati rilevamento competenze (integrazione Gap G).
$string['assessment_results'] = 'Risultati Rilevamento Competenze (6 settimane)';
$string['assessment_sector'] = 'Settore Rilevato';
$string['assessment_quiz_avg'] = 'Media Quiz';
$string['assessment_autoval_avg'] = 'Media Autovalutazione';
$string['assessment_coach_avg'] = 'Media Valutazione Coach';
$string['assessment_quiz_count'] = 'Quiz Completati';
$string['assessment_comp_count'] = 'Competenze Valutate';
$string['assessment_baseline_note'] = 'Dati dal sistema di Rilevamento Competenze FTM. Questi valori rappresentano la baseline di partenza per il percorso Coaching Individualizzato.';

// Export Report Finale Coaching Individualizzato.
$string['report_sip_title'] = 'Report Coaching Individualizzato';
$string['report_section_pci'] = 'Dati PCI';
$string['report_section_eligibility'] = 'Valutazione Idoneita';
$string['report_section_baseline'] = 'Baseline - Livelli Iniziali';
$string['report_section_final'] = 'Livelli Finali e Progressi';
$string['report_section_meetings'] = 'Riepilogo Incontri';
$string['report_section_kpi'] = 'Indicatori Chiave';
$string['report_section_outcome'] = 'Esito Finale';
$string['report_section_evaluation'] = 'Valutazione Finale Coach';
$string['report_total_meetings'] = 'Totale incontri';
$string['report_total_hours'] = 'Ore totali';
$string['report_avg_frequency'] = 'Frequenza media';
$string['report_generated_by'] = 'Report generato il {$a->date} da {$a->user}';
$string['report_download'] = 'Scarica Report';
$string['report_duration_weeks'] = 'Durata effettiva (settimane)';
$string['report_level_description'] = 'Descrizione Livello';
$string['report_initial'] = 'Iniziale';
$string['report_final'] = 'Finale';
$string['report_meetings_per_week'] = 'incontri/settimana';
$string['report_most_frequent_modality'] = 'Modalita piu frequente';
$string['report_with_interview'] = 'con colloquio';
$string['report_positive_outcome'] = 'esito positivo';
$string['report_completed'] = 'completate';
$string['report_position'] = 'Posizione';
$string['report_type'] = 'Tipo';
$string['report_contact_type'] = 'Tipo Contatto';
$string['report_contact_person'] = 'Persona Contattata';
$string['report_outcome_col'] = 'Esito';

// Griglia Valutazione PCI - 6 criteri numerici (1-5).
$string['eligibility_grid_title'] = 'Griglia Valutazione PCI';
$string['eligibility_grid_instruction'] = 'Valutare ogni criterio da 1 (minimo) a 5 (massimo)';
$string['eligibility_criterion_motivazione'] = 'Motivazione';
$string['eligibility_criterion_chiarezza'] = 'Chiarezza Obiettivo';
$string['eligibility_criterion_occupabilita'] = 'Occupabilita';
$string['eligibility_criterion_autonomia'] = 'Autonomia';
$string['eligibility_criterion_bisogno_coaching'] = 'Bisogno Coaching';
$string['eligibility_criterion_comportamento'] = 'Comportamento';
$string['eligibility_desc_motivazione_1'] = 'Passivo / poco coinvolto';
$string['eligibility_desc_motivazione_3'] = 'Partecipa ma senza iniziativa';
$string['eligibility_desc_motivazione_5'] = 'Proattivo, coinvolto, orientato al risultato';
$string['eligibility_desc_chiarezza_1'] = 'Nessun obiettivo';
$string['eligibility_desc_chiarezza_3'] = 'Obiettivo generico';
$string['eligibility_desc_chiarezza_5'] = 'Obiettivo chiaro e realistico';
$string['eligibility_desc_occupabilita_1'] = 'Molto bassa';
$string['eligibility_desc_occupabilita_3'] = 'Media';
$string['eligibility_desc_occupabilita_5'] = 'Alta (profilo spendibile rapidamente)';
$string['eligibility_desc_autonomia_1'] = 'Totalmente dipendente';
$string['eligibility_desc_autonomia_3'] = 'Parzialmente autonomo';
$string['eligibility_desc_autonomia_5'] = 'Autonomo e organizzato';
$string['eligibility_desc_bisogno_coaching_1'] = 'Non necessario';
$string['eligibility_desc_bisogno_coaching_3'] = 'Utile ma non essenziale';
$string['eligibility_desc_bisogno_coaching_5'] = 'Altamente necessario per sblocco';
$string['eligibility_desc_comportamento_1'] = 'Assenze / scarso impegno';
$string['eligibility_desc_comportamento_3'] = 'Adeguato';
$string['eligibility_desc_comportamento_5'] = 'Eccellente (puntuale, attivo, collaborativo)';
$string['eligibility_total'] = 'Totale';
$string['eligibility_decisione'] = 'Decisione';
$string['eligibility_decisione_idoneo'] = 'Idoneo';
$string['eligibility_decisione_non_idoneo'] = 'Non Idoneo';
$string['eligibility_decisione_pending'] = 'In attesa';
$string['eligibility_note'] = 'Note';
$string['eligibility_note_placeholder'] = 'Note aggiuntive sulla valutazione...';
$string['eligibility_activation_title'] = 'Attivazione Coaching Individualizzato (se idoneo)';
$string['eligibility_motivation_detail'] = 'Motivazione dettagliata';
$string['eligibility_motivation_placeholder'] = 'Perche questa persona puo beneficiare del Coaching Individualizzato...';
$string['eligibility_save_assessment'] = 'Salva valutazione';
$string['eligibility_save_and_activate'] = 'Salva e Attiva CI';
$string['eligibility_report_criterion'] = 'Criterio';
$string['eligibility_report_score'] = 'Punteggio';
