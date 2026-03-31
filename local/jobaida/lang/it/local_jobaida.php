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
 * Italian language strings for local_jobaida.
 *
 * @package    local_jobaida
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin.
$string['pluginname'] = 'JobAIDA - Generatore Lettere';
$string['pluginname_desc'] = 'Genera lettere di candidatura basate sul modello AIDA utilizzando l\'intelligenza artificiale.';

// Capabilities.
$string['jobaida:use'] = 'Utilizzare il generatore di lettere';
$string['jobaida:authorize'] = 'Autorizzare studenti a usare il generatore';
$string['jobaida:viewall'] = 'Visualizzare tutte le lettere generate';

// Settings.
$string['openai_apikey'] = 'Chiave API OpenAI';
$string['openai_apikey_desc'] = 'Inserisci la chiave API OpenAI per la generazione delle lettere.';
$string['openai_model'] = 'Modello OpenAI';
$string['openai_model_desc'] = 'Seleziona il modello AI da utilizzare per la generazione.';
$string['letter_language'] = 'Lingua lettere';
$string['letter_language_desc'] = 'Lingua in cui verranno generate le lettere di candidatura.';
$string['max_tokens'] = 'Token massimi';
$string['max_tokens_desc'] = 'Numero massimo di token per la generazione (default: 2000).';

// Navigation.
$string['generator'] = 'Generatore Lettere';
$string['history'] = 'Storico Lettere';
$string['manage_auth'] = 'Gestisci Autorizzazioni';

// Form.
$string['job_ad'] = 'Annuncio di Lavoro';
$string['job_ad_help'] = 'Incolla qui il testo completo dell\'annuncio di lavoro a cui vuoi candidarti.';
$string['job_ad_placeholder'] = 'Incolla qui l\'annuncio di lavoro...';
$string['cv_text'] = 'Il tuo CV';
$string['cv_text_help'] = 'Incolla qui il contenuto del tuo curriculum vitae o le tue esperienze principali.';
$string['cv_text_placeholder'] = 'Incolla qui il tuo CV o le tue esperienze...';
$string['objectives'] = 'I tuoi Obiettivi';
$string['objectives_help'] = 'Descrivi cosa cerchi in questo lavoro, i tuoi valori e le tue motivazioni personali.';
$string['objectives_placeholder'] = 'Cosa ti motiva? Quali sono i tuoi obiettivi professionali?';
$string['generate'] = 'Genera Lettera';
$string['generating'] = 'Generazione in corso...';

// AIDA sections.
$string['aida_model'] = 'Modello AIDA';
$string['aida_explanation'] = 'Il modello AIDA e una tecnica di comunicazione persuasiva utilizzata nel marketing e nella redazione di lettere di candidatura efficaci.';
$string['attention'] = 'ATTENTION - Cattura l\'Attenzione';
$string['attention_desc'] = 'Un gancio iniziale che cattura immediatamente l\'attenzione del selezionatore, basato sull\'annuncio di lavoro.';
$string['interest'] = 'INTEREST - Suscita Interesse';
$string['interest_desc'] = 'Collegamento tra i requisiti dell\'annuncio e le tue competenze/esperienze dal CV.';
$string['desire'] = 'DESIRE - Crea il Desiderio';
$string['desire_desc'] = 'Connessione tra i tuoi valori/obiettivi personali e la cultura/missione dell\'azienda.';
$string['action'] = 'ACTION - Invito all\'Azione';
$string['action_desc'] = 'Chiusura con una chiamata all\'azione chiara e professionale.';
$string['rationale'] = 'Perche questa scelta';
$string['full_letter'] = 'Lettera Completa';
$string['copy_letter'] = 'Copia Lettera';
$string['copied'] = 'Copiata!';

// History.
$string['history_title'] = 'Le tue Lettere';
$string['no_letters'] = 'Non hai ancora generato nessuna lettera.';
$string['generated_on'] = 'Generata il';
$string['view_letter'] = 'Visualizza';
$string['delete_letter'] = 'Elimina';
$string['delete_confirm'] = 'Sei sicuro di voler eliminare questa lettera?';
$string['letter_deleted'] = 'Lettera eliminata';

// Authorization.
$string['not_authorized'] = 'Non sei autorizzato a utilizzare il generatore di lettere. Chiedi al tuo coach di abilitarti.';
$string['authorize_student'] = 'Autorizza Studente';
$string['revoke_student'] = 'Revoca Autorizzazione';
$string['student_authorized'] = 'Studente autorizzato con successo';
$string['student_revoked'] = 'Autorizzazione revocata';
$string['authorized_students'] = 'Studenti Autorizzati';
$string['search_student'] = 'Cerca studente da autorizzare...';

// Errors.
$string['error_no_apikey'] = 'Chiave API OpenAI non configurata. Contattare l\'amministratore.';
$string['error_api_failed'] = 'Errore nella generazione: {$a}';
$string['error_empty_fields'] = 'Compila tutti i campi obbligatori.';
$string['error_too_short'] = 'Il testo inserito e troppo breve. Inserisci almeno 50 caratteri per campo.';

// Stats.
$string['letters_generated'] = 'Lettere generate';
$string['tokens_consumed'] = 'Token consumati';
$string['last_generated'] = 'Ultima generazione';
