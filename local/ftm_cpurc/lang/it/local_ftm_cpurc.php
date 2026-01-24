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
 * Italian language strings for local_ftm_cpurc.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin metadata.
$string['pluginname'] = 'FTM CPURC Manager';
$string['pluginname_desc'] = 'Importa e gestisce studenti CPURC con dati estesi e report finali.';

// Capabilities.
$string['ftm_cpurc:view'] = 'Visualizza dati CPURC';
$string['ftm_cpurc:import'] = 'Importa CSV CPURC';
$string['ftm_cpurc:edit'] = 'Modifica dati studente';
$string['ftm_cpurc:generatereport'] = 'Genera report';
$string['ftm_cpurc:finalizereport'] = 'Finalizza e invia report';

// Navigation.
$string['cpurc_manager'] = 'CPURC Manager';
$string['import_csv'] = 'Importa CSV';
$string['student_card'] = 'Scheda Studente';
$string['generate_report'] = 'Genera Report';

// Dashboard.
$string['dashboard'] = 'Dashboard';
$string['total_students'] = 'Studenti Totali';
$string['active_students'] = 'Studenti Attivi';
$string['reports_draft'] = 'Report in Bozza';
$string['reports_final'] = 'Report Finali';

// Import.
$string['import_title'] = 'Importa CSV CPURC';
$string['import_instructions'] = 'Carica un file CSV in formato CPURC (69 colonne, separatore punto e virgola).';
$string['import_file'] = 'File CSV';
$string['import_options'] = 'Opzioni Importazione';
$string['update_existing'] = 'Aggiorna utenti esistenti';
$string['enrol_course'] = 'Iscrivi al corso';
$string['assign_cohort'] = 'Assegna a coorte URC';
$string['assign_group'] = 'Assegna a gruppo colore';
$string['import_preview'] = 'Anteprima';
$string['import_start'] = 'Avvia Importazione';
$string['import_success'] = 'Importazione completata con successo';
$string['import_errors'] = 'Importazione completata con errori';
$string['rows_imported'] = '{$a} righe importate';
$string['rows_updated'] = '{$a} righe aggiornate';
$string['rows_errors'] = '{$a} errori';

// Student card.
$string['tab_anagrafica'] = 'Anagrafica';
$string['tab_percorso'] = 'Percorso';
$string['tab_assenze'] = 'Assenze';
$string['tab_stage'] = 'Stage';
$string['tab_report'] = 'Report';

// Fields - Personal data.
$string['firstname'] = 'Nome';
$string['lastname'] = 'Cognome';
$string['gender'] = 'Sesso';
$string['birthdate'] = 'Data di Nascita';
$string['address'] = 'Indirizzo';
$string['cap'] = 'CAP';
$string['city'] = 'Citta';
$string['phone'] = 'Telefono';
$string['mobile'] = 'Cellulare';
$string['email'] = 'Email';
$string['avs_number'] = 'Numero AVS';
$string['nationality'] = 'Nazionalita';
$string['permit'] = 'Permesso';
$string['iban'] = 'IBAN';
$string['civil_status'] = 'Stato Civile';

// Fields - Path.
$string['personal_number'] = 'Numero Personale';
$string['measure'] = 'Misura';
$string['trainer'] = 'Formatore';
$string['signal_date'] = 'Data Segnalazione';
$string['date_start'] = 'Data Inizio';
$string['date_end_planned'] = 'Fine Prevista';
$string['date_end_actual'] = 'Fine Effettiva';
$string['occupation_grade'] = 'Grado Occupazione';
$string['urc_office'] = 'Ufficio URC';
$string['urc_consultant'] = 'Consulente URC';
$string['status'] = 'Stato';
$string['exit_reason'] = 'Motivo Uscita';
$string['last_profession'] = 'Ultima Professione';
$string['sector'] = 'Settore';
$string['priority'] = 'Priorita';
$string['financier'] = 'Finanziatore';
$string['unemployment_fund'] = 'Cassa Disoccupazione';

// Fields - Absences.
$string['absence_x'] = 'Assenza X';
$string['absence_o'] = 'Assenza O';
$string['absence_a'] = 'Assenza A';
$string['absence_b'] = 'Assenza B';
$string['absence_c'] = 'Assenza C';
$string['absence_d'] = 'Assenza D';
$string['absence_e'] = 'Assenza E';
$string['absence_f'] = 'Assenza F';
$string['absence_g'] = 'Assenza G';
$string['absence_h'] = 'Assenza H';
$string['absence_i'] = 'Assenza I';
$string['absence_total'] = 'Assenze Totali';

// Fields - Stage.
$string['stage_start'] = 'Inizio Stage';
$string['stage_end'] = 'Fine Stage';
$string['stage_responsible'] = 'Responsabile';
$string['stage_company'] = 'Azienda';
$string['stage_contact'] = 'Persona di Riferimento';
$string['stage_percentage'] = 'Percentuale';
$string['stage_function'] = 'Funzione';

// Report.
$string['report_title'] = 'Report Finale';
$string['section_initial_situation'] = 'Situazione Iniziale';
$string['section_sector_competencies'] = 'Competenze Settore';
$string['section_transversal_competencies'] = 'Competenze Trasversali';
$string['section_job_search'] = 'Ricerca Impiego';
$string['section_interviews'] = 'Colloqui';
$string['section_outcome'] = 'Esito';
$string['save_draft'] = 'Salva Bozza';
$string['export_data'] = 'Esporta Dati';
$string['hired'] = 'Assunto';
$string['not_hired'] = 'Non Assunto';
$string['possible_sectors'] = 'Possibili settori e ambiti';
$string['final_summary'] = 'Sintesi conclusiva';
$string['obs_personal'] = 'Osservazioni competenze personali';
$string['obs_social'] = 'Osservazioni competenze sociali';
$string['obs_methodological'] = 'Osservazioni competenze metodologiche';
$string['obs_search_channels'] = 'Osservazioni canali ricerca';
$string['obs_search_evaluation'] = 'Valutazione capacita ricerca impiego';

// URC Offices.
$string['urc_bellinzona'] = 'URC Bellinzona';
$string['urc_chiasso'] = 'URC Chiasso';
$string['urc_lugano'] = 'URC Lugano';
$string['urc_biasca'] = 'URC Biasca';
$string['urc_locarno'] = 'URC Locarno';

// Sectors.
$string['sector_automobile'] = 'Automobile';
$string['sector_meccanica'] = 'Meccanica';
$string['sector_logistica'] = 'Logistica';
$string['sector_elettricita'] = 'Elettricita';
$string['sector_automazione'] = 'Automazione';
$string['sector_metalcostruzione'] = 'Metalcostruzione';
$string['sector_chimfarm'] = 'Chimico-Farmaceutico';

// Messages.
$string['student_saved'] = 'Dati studente salvati con successo';
$string['report_saved'] = 'Report salvato con successo';
$string['studentnotfound'] = 'Studente non trovato';
$string['error_invalid_csv'] = 'Formato file CSV non valido';
$string['error_missing_required'] = 'Campi obbligatori mancanti: {$a}';
$string['error_user_exists'] = 'Utente gia esistente: {$a}';
$string['error_save_failed'] = 'Salvataggio fallito';

// Export.
$string['export_word'] = 'Esporta in Word';
$string['export_pdf'] = 'Esporta in PDF';
$string['templatenotfound'] = 'File template non trovato: {$a}';
$string['cannotcreatetempdir'] = 'Impossibile creare cartella temporanea';
$string['cannotopentempate'] = 'Impossibile aprire il file template';
$string['invaliddocument'] = 'Struttura documento non valida';
$string['cannotcreateoutput'] = 'Impossibile creare file di output';
