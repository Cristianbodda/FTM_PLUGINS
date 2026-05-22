<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Italian language strings.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'JobMatch Agent';

// Capabilities.
$string['jobmatchagent:configure'] = 'Configurare JobMatch Agent';
$string['jobmatchagent:manage'] = 'Gestire filtri studenti e revisione match';
$string['jobmatchagent:addoffer'] = 'Aggiungere annunci manuali';
$string['jobmatchagent:viewown'] = 'Visualizzare i propri match pubblicati';

// Settings.
$string['score_threshold'] = 'Soglia minima score (%)';
$string['score_threshold_desc'] = 'I match con score deterministico sotto questa soglia vengono scartati senza chiamare l\'AI. Default: 10%';
$string['openai_model'] = 'Modello OpenAI';
$string['openai_model_desc'] = 'Modello AI per il matching. La API key viene riusata da JobAIDA. GPT-4o Mini e raccomandato per restare nel budget.';
$string['budget_eur_month'] = 'Budget mensile (EUR)';
$string['budget_eur_month_desc'] = 'Limite indicativo di spesa AI mensile (solo per logging, non bloccante).';
$string['weights_heading'] = 'Pesi scoring (deve sommare a 100)';
$string['weights_heading_desc'] = 'Pesi di default per il calcolo dello score globale.';
$string['weight_sector'] = 'Peso settore (%)';
$string['weight_experience'] = 'Peso esperienza CV (%)';
$string['weight_distance'] = 'Peso distanza casa (%)';
$string['weight_schedule'] = 'Peso orario (%)';
$string['weight_size'] = 'Peso dimensione azienda (%)';

// Navigation.
$string['coachdashboard'] = 'JobMatch (Coach)';
$string['mymatches'] = 'Le mie opportunita';

// Coach dashboard.
$string['cd_title'] = 'JobMatch Agent — Dashboard Coach';
$string['cd_intro'] = 'Studenti gestiti e match in attesa di revisione.';
$string['cd_student'] = 'Studente';
$string['cd_pending'] = 'In attesa';
$string['cd_published'] = 'Pubblicati';
$string['cd_discarded'] = 'Scartati';
$string['cd_filters'] = 'Filtri';
$string['cd_actions'] = 'Azioni';
$string['cd_review'] = 'Rivedi match';
$string['cd_setfilters'] = 'Imposta filtri';
$string['cd_addoffer'] = 'Aggiungi annuncio manuale';
$string['cd_nostudents'] = 'Nessuno studente assegnato.';
$string['cd_agent_off'] = 'Agente OFF';
$string['cd_agent_on'] = 'Agente ON';
$string['cd_runsearch'] = 'Cerca opportunita';
$string['cd_runsearch_all'] = 'Cerca per tutti gli attivi';
$string['cd_fetch_now'] = 'Aggiorna catalogo (RSS + AI Scraper)';
$string['cd_fetch_confirm'] = 'Avviare l\'aggiornamento catalogo? Il sistema scarichera annunci da tutte le fonti RSS configurate (ti.ch, admin.ch, Indeed Ticino) E lancera l\'AI Scraper su jobs.ch, job-room.ch e carriera.ch per i settori e attivita degli studenti attivi. Puo richiedere fino a 5 minuti.';
$string['cd_manage_sources'] = 'Gestisci fonti RSS';
$string['cd_search_settings'] = 'Impostazioni ricerca';
$string['cd_catalog'] = 'Catalogo';
$string['cd_catalog_offers'] = 'annunci attivi';
$string['cd_catalog_sources'] = 'fonti attive';

// Sources admin.
$string['src_title'] = 'Fonti RSS / feed annunci';
$string['src_intro'] = 'Configura le fonti RSS o Atom da cui l\'agente scarica annunci di lavoro. Verranno interrogate dal cron quotidiano e dal bottone "Aggiorna catalogo".';
$string['src_name'] = 'Nome';
$string['src_url'] = 'URL feed';
$string['src_url_desc'] = 'URL del feed RSS 2.0, RSS 1.0 o Atom. Esempio: https://www4.ti.ch/can/rss/posti-di-lavoro/';
$string['src_enabled'] = 'Attivo';
$string['src_on'] = 'ON';
$string['src_off'] = 'OFF';
$string['src_last_fetch'] = 'Ultimo fetch';
$string['src_last_error'] = 'Ultimo errore';
$string['src_add'] = 'Aggiungi fonte';
$string['src_save'] = 'Salva fonte';
$string['src_saved'] = 'Fonte salvata.';
$string['src_deleted'] = 'Fonte eliminata.';
$string['src_toggled'] = 'Stato fonte aggiornato.';
$string['src_confirm_delete'] = 'Eliminare definitivamente questa fonte?';
$string['src_disable'] = 'Disabilita';
$string['src_enable'] = 'Abilita';
$string['src_none'] = 'Nessuna fonte configurata. Aggiungine una per iniziare a popolare il catalogo automaticamente.';
$string['src_examples'] = 'Esempi di feed RSS svizzeri:';

// Fetch now.
$string['fn_title'] = 'Aggiornamento catalogo annunci';
$string['fn_intro'] = 'L\'agente sta scaricando annunci da tutte le fonti RSS configurate e calcolando i match per gli studenti attivi.';
$string['fn_no_sources'] = 'Nessuna fonte RSS configurata. Aggiungine almeno una in "Gestisci fonti".';
$string['fn_configure_sources'] = 'Configura fonti RSS';
$string['fn_success'] = 'Aggiunti {$a->offers} nuovi annunci al catalogo. Generati {$a->matches} nuovi match per gli studenti attivi.';
$string['fn_no_new'] = 'Nessun nuovo annuncio trovato. Tutti gli annunci dei feed sono gia nel catalogo.';
$string['fn_with_errors'] = 'Fetch completato con errori. Vedi dettaglio sotto per ogni fonte.';
$string['fn_sources_run'] = 'Fonti interrogate';
$string['fn_offers_added'] = 'nuovi annunci';
$string['fn_matches_created'] = 'Match generati per studenti attivi';
$string['fn_per_source'] = 'Dettaglio per fonte';
$string['fn_status'] = 'Stato';
$string['fn_rss_sources'] = 'Fonti RSS';
$string['fn_ai_scraper'] = 'AI Scraper (jobs.ch / job-room.ch / carriera)';
$string['fn_ai_not_available'] = 'Plugin local_ftm_jobsearch non installato';
$string['fn_sectors_scraped'] = 'settori scrappati';
$string['fn_offers_imported'] = 'annunci importati';
$string['fn_total_offers'] = 'Totale nuovi annunci nel catalogo';
$string['fn_rss_detail'] = 'Dettaglio fonti RSS';
$string['fn_ai_detail'] = 'Dettaglio AI Scraper';
$string['fn_ai_explanation'] = 'L\'AI Scraper interroga jobs.ch, job-room.ch e carriera.ch per ogni combinazione settore + attivita desiderata degli studenti attivi.';
$string['fn_sector_mansione'] = 'Combo settore | mansione';
$string['fn_errors'] = 'Errori';

// Run search results page.
$string['rs_title'] = 'Risultato ricerca per {$a}';
$string['rs_intro'] = 'L\'agente ha confrontato i filtri di {$a} con tutti gli annunci nel catalogo. Ecco cosa ha trovato:';
$string['rs_offers_checked'] = 'Annunci controllati nel catalogo';
$string['rs_new_matches'] = 'Nuovi match sopra soglia (visibili in revisione)';
$string['rs_below_threshold'] = 'Scartati automaticamente (sotto soglia {$a}%)';
$string['rs_already_done'] = 'Gia presenti dalla precedente ricerca (saltati)';
$string['rs_no_offers'] = 'Nessun annuncio nel catalogo. Aggiungine uno con "Aggiungi annuncio manuale" o attendi il cron quotidiano (in F2).';
$string['rs_no_new'] = 'Nessun nuovo match trovato in questa ricerca. Tutti gli annunci nel catalogo erano gia stati valutati per questo studente, oppure nessuno supera la soglia configurata ({$a}%).';
$string['rs_success'] = 'Trovati {$a} nuovi match. Clicca "Rivedi match" per esaminarli.';
$string['rs_view_matches'] = 'Rivedi i match trovati';
$string['rs_back_dashboard'] = 'Torna alla dashboard';
$string['rs_no_filters'] = 'Devi prima impostare i filtri di ricerca per questo studente.';
$string['rs_agent_off'] = 'L\'agente e OFF per questo studente. Attivalo nei filtri prima di cercare.';
$string['rs_confirm'] = 'Avviare ora la ricerca di opportunita di lavoro per {$a}? L\'agente confrontera il CV e i filtri dello studente con tutti gli annunci nel catalogo.';

// Student filters.
$string['sf_title'] = 'Filtri ricerca per {$a}';
$string['sf_intro'] = 'Configura i filtri di ricerca dell\'agente. Tutti i filtri sono in AND (devono essere soddisfatti).';
$string['sf_active'] = 'Agente attivo per questo studente';
$string['sf_active_desc'] = 'Se disattivato, nessuna nuova ricerca verra fatta per questo studente.';
$string['sf_home_address'] = 'Indirizzo di casa';
$string['sf_home_address_desc'] = 'Via, citta. Geocoding inserito a mano (lat/lng opzionali).';
$string['sf_home_lat'] = 'Latitudine';
$string['sf_home_lng'] = 'Longitudine';
$string['sf_max_distance'] = 'Distanza massima (km)';
$string['sf_max_distance_desc'] = 'Limite massimo accettabile dalla casa al lavoro.';
$string['sf_company_sizes'] = 'Dimensione azienda';
$string['sf_company_size_s'] = 'Piccola (1-49)';
$string['sf_company_size_m'] = 'Media (50-249)';
$string['sf_company_size_l'] = 'Grande (250+)';
$string['sf_work_schedules'] = 'Orario di lavoro';
$string['sf_schedule_fulltime'] = 'Tempo pieno';
$string['sf_schedule_parttime'] = 'Tempo parziale';
$string['sf_schedule_shifts'] = 'Turni';
$string['sf_schedule_flex'] = 'Flessibile';
$string['sf_desired_activities'] = 'Attivita desiderate';
$string['sf_desired_activities_desc'] = 'Una attivita per riga. Esempio: manutentore stabili, elettricista, magazziniere.';
$string['sf_extra_notes'] = 'Note aggiuntive (per AI)';
$string['sf_extra_notes_desc'] = 'Contesto libero che verra passato all\'AI per migliorare il matching.';
$string['sf_save'] = 'Salva filtri';
$string['sf_saved'] = 'Filtri salvati con successo.';
$string['sf_cv_section'] = 'CV usato per il matching';
$string['sf_cv_using_manual'] = 'L\'agente sta usando il CV incollato qui sotto (override).';
$string['sf_cv_using_jobaida'] = 'L\'agente sta usando l\'ultimo CV salvato in JobAIDA. Se vuoi usare un CV specifico, incollalo qui sotto.';
$string['sf_cv_none'] = 'Nessun CV disponibile per questo studente. Incollane uno qui sotto, oppure lo studente deve generare almeno una lettera AIDA in JobAIDA.';
$string['sf_manual_cv'] = 'CV personalizzato (incolla testo)';
$string['sf_manual_cv_desc'] = 'Se compilato, questo CV avra la priorita sul CV salvato in JobAIDA. Lascia vuoto per usare automaticamente il CV JobAIDA piu recente.';
$string['sf_manual_cv_placeholder'] = "Incolla qui il CV completo dello studente. Esempio:\n\nMARIO ROSSI\nVia Roma 12, 6900 Lugano\nmario.rossi@email.ch\n\nESPERIENZE LAVORATIVE\n2023-2025 - Operaio metalmeccanico, ABC SA, Lugano\n  - Saldatura TIG/MIG\n  - Lettura disegno tecnico\n\nFORMAZIONE\n2020-2023 - SPAI Lugano, AFC Polimeccanico\n\nLINGUE\nItaliano (madrelingua), Tedesco (B1), Inglese (A2)";
$string['sf_clear_cv'] = 'Cancella il CV personalizzato e torna ad usare il CV JobAIDA';

// Add offer manual.
$string['ao_title'] = 'Aggiungi annuncio manuale';
$string['ao_intro'] = 'Incolla URL e/o testo dell\'annuncio. Verra calcolato il match per tutti gli studenti attivi.';
$string['ao_url'] = 'URL annuncio';
$string['ao_jobtitle'] = 'Titolo';
$string['ao_company'] = 'Azienda';
$string['ao_location'] = 'Localita';
$string['ao_company_size'] = 'Dimensione azienda';
$string['ao_company_size_unknown'] = 'Sconosciuta';
$string['ao_work_schedule'] = 'Orario';
$string['ao_text'] = 'Testo annuncio';
$string['ao_text_desc'] = 'Incolla qui il testo completo dell\'annuncio. Verra usato per il matching AI.';
$string['ao_save'] = 'Salva e calcola match';
$string['ao_saved'] = 'Annuncio salvato. {$a} match calcolati.';
$string['ao_duplicate'] = 'Annuncio gia presente nel catalogo (fingerprint duplicato).';

// Coach review.
$string['cr_title'] = 'Match per {$a}';
$string['cr_intro'] = 'Match in attesa di revisione. Decidi se pubblicare allo studente, scartare o tenere in sospeso.';
$string['cr_offer'] = 'Annuncio';
$string['cr_score'] = 'Idoneita';
$string['cr_breakdown'] = 'Dettaglio score';
$string['cr_explanation'] = 'Spiegazione AI';
$string['cr_no_ai_yet'] = 'Spiegazione AI non ancora generata (in coda).';
$string['cr_publish'] = 'Pubblica allo studente';
$string['cr_discard'] = 'Scarta';
$string['cr_onhold'] = 'Sospendi';
$string['cr_published'] = 'Pubblicato il {$a}';
$string['cr_discarded'] = 'Scartato il {$a}';
$string['cr_onhold_at'] = 'Sospeso il {$a}';
$string['cr_note'] = 'Nota coach';
$string['cr_no_pending'] = 'Nessun match in attesa per questo studente.';
$string['cr_show_published'] = 'Mostra pubblicati';
$string['cr_show_discarded'] = 'Mostra scartati';

// Score criteria labels.
$string['score_global'] = 'Score globale';
$string['score_sector'] = 'Settore';
$string['score_experience'] = 'Esperienza CV';
$string['score_distance'] = 'Distanza casa';
$string['score_schedule'] = 'Orario';
$string['score_size'] = 'Dimensione azienda';
$string['score_activity'] = 'Attivita desiderata';

// Student view.
$string['sv_title'] = 'Le mie opportunita';
$string['sv_intro'] = 'Opportunita selezionate dal tuo coach in base al tuo CV e alle tue preferenze.';
$string['sv_no_matches'] = 'Nessuna opportunita disponibile al momento.';
$string['sv_published_at'] = 'Pubblicato il {$a}';
$string['sv_view_offer'] = 'Vedi annuncio originale';
$string['sv_cv_used'] = 'CV usato per la valutazione';
$string['sv_show_cv'] = 'Mostra CV';
$string['sv_hide_cv'] = 'Nascondi CV';
$string['sv_why_match'] = 'Perche e adatto a te';
$string['sv_action_interested'] = 'Mi interessa, voglio candidarmi';
$string['sv_action_not_interested'] = 'Non mi interessa';
$string['sv_action_already_applied'] = 'Mi sono gia candidato altrove';
$string['sv_reason'] = 'Motivo (opzionale)';
$string['sv_action_saved'] = 'Risposta salvata. Grazie per il feedback.';
$string['sv_generate_letter'] = 'Genera lettera AIDA per questo annuncio';

// Errors.
$string['err_no_capability'] = 'Non hai i permessi per accedere a questa pagina.';
$string['err_invalid_student'] = 'Studente non valido o non gestito da te.';
$string['err_invalid_match'] = 'Match non trovato.';
$string['err_no_text'] = 'Devi incollare il testo dell\'annuncio.';
$string['err_no_title'] = 'Devi inserire un titolo.';

// Capabilities — aziende e target.
$string['jobmatchagent:managecompanies'] = 'Gestisci database aziende Ticino';
$string['jobmatchagent:managetargets'] = 'Gestisci aziende target per studenti';

// Student targets page.
$string['st_title']                    = 'Autocandidature — {$a}';
$string['st_info_sector']              = 'Settore';
$string['st_info_coach']               = 'Coach';
$string['st_ci_active']                = 'CI attivo';
$string['st_ci_inactive']              = 'CI non attivo';
$string['st_student_view']             = 'Vista studente';
$string['st_sv_on']                    = 'ON';
$string['st_sv_off']                   = 'OFF';
$string['st_sv_toggle_help']           = 'Attiva/disattiva la visualizzazione delle autocandidature per lo studente';
$string['st_sv_activated']             = 'Vista studente attivata';
$string['st_sv_deactivated']           = 'Vista studente disattivata';
$string['st_targets_list']             = 'Aziende target';
$string['st_add_target']               = 'Aggiungi azienda target';
$string['st_no_targets']               = 'Nessuna azienda target. Clicca "Aggiungi azienda target" per iniziare.';
$string['st_note_per_ai']              = 'Note per lettera AI';
$string['st_note_per_ai_placeholder']  = 'Es: Candidatura spontanea per ruolo di elettricista. Enfatizza esperienza con impianti industriali.';
$string['st_note_per_ai_help']         = 'Queste note vengono passate a JobAIDA per personalizzare la lettera di presentazione.';
$string['st_registered_ci']            = 'Registrato in CI';
$string['st_btn_generate_letter']      = 'Genera lettera';
$string['st_btn_confirm_sent']         = 'Conferma invio';
$string['st_btn_delete']               = 'Elimina';
$string['st_btn_add']                  = 'Aggiungi';
$string['st_confirm_delete']           = 'Eliminare questa azienda target? L\'operazione non puo essere annullata.';
$string['st_confirm_sent_help']        = 'Confermare che la candidatura e stata inviata a questa azienda?';
$string['st_note_saved']               = 'Nota salvata.';
$string['st_target_created']           = 'Azienda target aggiunta.';
$string['st_target_deleted']           = 'Azienda target eliminata.';
$string['st_status_updated']           = 'Stato aggiornato.';

// Status labels for student targets.
$string['st_status_pending']           = 'Da inviare';
$string['st_status_lettera_generata']  = 'Lettera generata';
$string['st_status_inviata']           = 'Inviata';
$string['st_status_risposta']          = 'Risposta ricevuta';
$string['st_status_colloquio']         = 'Colloquio';
$string['st_status_assunto']           = 'Assunto';
$string['st_status_rifiutato']         = 'Rifiutato';

// Modal: add target.
$string['st_modal_title']              = 'Aggiungi azienda target';
$string['st_modal_search']             = 'Cerca azienda';
$string['st_modal_search_placeholder'] = 'Nome azienda...';
$string['st_modal_filter_sector']      = 'Settore';
$string['st_modal_all_sectors']        = 'Tutti i settori';
$string['st_modal_type_to_search']     = 'Digita almeno 2 caratteri per cercare';
$string['st_modal_no_results']         = 'Nessuna azienda trovata';
$string['st_modal_selected']           = 'Selezionata';

// AI suggest targets.
$string['st_ai_suggest']               = 'Suggerisci aziende AI';
$string['st_ai_suggest_title']         = 'Suggerimenti AI — Aziende target';
$string['st_ai_suggest_loading']       = 'Analisi profilo in corso...';
$string['st_ai_suggest_none']          = 'Nessun suggerimento trovato. Aggiungi più aziende al database.';
$string['st_ai_suggest_already']       = '✓ Già nel piano';
$string['st_ai_add_target']            = '+ Aggiungi target';
$string['st_ai_added']                 = '✓ Aggiunto';
$string['st_btn_detail']               = 'Dettaglio';
$string['st_detail_title']             = 'Scheda azienda';
$string['st_detail_loading']           = 'Analisi sito in corso...';
$string['st_detail_no_website']        = 'Nessun sito web disponibile per questa azienda.';

// Modal: note esito.
$string['st_modal_note_esito']         = 'Note sull\'esito';
$string['st_note_esito_placeholder']   = 'Es: Rifiutato per mancanza esperienza specifica. / Colloquio il 05/06.';
$string['st_btn_confirm_status']       = 'Conferma';

// Errors.
$string['st_err_target_not_found']     = 'Azienda target non trovata.';
$string['st_err_company_not_found']    = 'Azienda non trovata nel database.';
$string['st_err_invalid_action']       = 'Azione non valida.';
$string['st_err_invalid_status']       = 'Stato non valido.';

// Navigazione — aziende e target.
$string['companies'] = 'Aziende';
$string['companiesdb'] = 'Database Aziende Ticino';
$string['studenttargets'] = 'Aziende Target Studente';

// Pagina Aziende (companies.php).
$string['companies_title'] = 'Database Aziende Ticino';
$string['companies_tab_all'] = 'Tutte';
$string['companies_tab_toclassify'] = 'Da Classificare';
$string['companies_tab_discover'] = 'Scopri Azienda';
$string['companies_tab_import'] = 'Importa CSV';
$string['companies_filter_sector'] = 'Settore';
$string['companies_filter_status'] = 'Stato';
$string['companies_filter_search'] = 'Cerca azienda...';
$string['companies_col_nome'] = 'Azienda';
$string['companies_col_settore'] = 'Settore';
$string['companies_col_localita'] = 'Localita\'';
$string['companies_col_status'] = 'Stato';
$string['companies_col_anno'] = 'Anno';
$string['companies_col_azioni'] = 'Azioni';
$string['companies_add'] = 'Aggiungi Azienda';
$string['companies_edit'] = 'Modifica';
$string['companies_activate'] = 'Attiva';
$string['companies_deactivate'] = 'Disattiva';
$string['companies_classify_batch'] = 'Classifica con AI (batch)';
$string['companies_classifying'] = 'Classificazione in corso...';
$string['companies_classified'] = '{$a->n} aziende classificate automaticamente';
$string['companies_import_success'] = 'Importazione completata: {$a->inserted} inserite, {$a->skipped} gia\' presenti, {$a->errors} errori';
$string['companies_discover_url'] = 'URL sito web aziendale';
$string['companies_discover_analyze'] = 'Analizza';
$string['companies_discover_found'] = 'Cosa ho capito';
$string['companies_discover_questions'] = 'Domande';
$string['companies_discover_add'] = 'Aggiungi al Database';
$string['companies_discover_discard'] = 'Scarta';
$string['companies_status_active'] = 'Attiva';
$string['companies_status_inactive'] = 'Inattiva';
$string['companies_status_unverified'] = 'Non verificata';
$string['companies_saved'] = 'Azienda salvata con successo';
$string['companies_notfound'] = 'Nessuna azienda trovata';

// Settori FTM.
$string['sector_AUTOMOBILE'] = 'Automotive';
$string['sector_AUTOMAZIONE'] = 'Automazione';
$string['sector_CHIMFARM'] = 'Chimica/Farmaceutica';
$string['sector_ELETTRICITA'] = 'Elettricita\'';
$string['sector_LOGISTICA'] = 'Logistica';
$string['sector_MECCANICA'] = 'Meccanica';
$string['sector_METALCOSTRUZIONE'] = 'Metalcostruzione';
$string['sector_ALTRO'] = 'Altro/Non classificato';

// Pagina Target Studente (student_targets.php).
$string['targets_title'] = 'Autocandidature — {$a}';
$string['targets_add_company'] = '+ Aggiungi Azienda Target';
$string['targets_note_ai'] = 'Note per la lettera AI';
$string['targets_note_ai_help'] = 'Es: Marco e\' forte su CNC, punta su questo';
$string['targets_generate_letter'] = '✉ Genera Lettera';
$string['targets_confirm_sent'] = '✓ Conferma Invio';
$string['targets_delete'] = 'Elimina';
$string['targets_ci_registered'] = '✓ Registrato in CI';
$string['targets_ci_active'] = 'CI Attivo';
$string['targets_ci_inactive'] = 'CI Non Attivo';
$string['targets_view_enabled'] = 'Vista studente attivata';
$string['targets_view_disabled'] = 'Vista studente disattivata';
$string['targets_view_toggle'] = 'Vista studente';
$string['targets_status_pending'] = 'In attesa';
$string['targets_status_lettera_generata'] = 'Lettera generata';
$string['targets_status_inviata'] = 'Inviata';
$string['targets_status_risposta'] = 'Risposta ricevuta';
$string['targets_status_colloquio'] = 'Colloquio';
$string['targets_status_assunto'] = 'Assunto';
$string['targets_status_rifiutato'] = 'Rifiutato';
$string['targets_search_company'] = 'Cerca azienda...';
$string['targets_no_targets'] = 'Nessuna azienda target assegnata. Clicca "+ Aggiungi" per iniziare.';
$string['targets_added'] = 'Azienda aggiunta ai target';
$string['targets_deleted'] = 'Target eliminato';
$string['targets_status_updated'] = 'Stato aggiornato';
