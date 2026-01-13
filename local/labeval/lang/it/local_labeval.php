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
 * Italian language strings for local_labeval
 *
 * @package    local_labeval
 * @copyright  2024 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin info
$string['pluginname'] = 'Valutazione Laboratorio';
$string['labeval:managetemplates'] = 'Gestire i template di valutazione';
$string['labeval:importtemplates'] = 'Importare template da Excel';
$string['labeval:assignevaluations'] = 'Assegnare valutazioni agli studenti';
$string['labeval:evaluate'] = 'Valutare gli studenti';
$string['labeval:viewownreport'] = 'Visualizzare il proprio report';
$string['labeval:viewallreports'] = 'Visualizzare tutti i report';
$string['labeval:authorizestudents'] = 'Autorizzare gli studenti';
$string['labeval:view'] = 'Accedere a Valutazione Laboratorio';

// Navigation
$string['dashboard'] = 'Dashboard';
$string['templates'] = 'Template';
$string['assignments'] = 'Assegnazioni';
$string['evaluations'] = 'Valutazioni';
$string['reports'] = 'Report';

// Templates
$string['templatelist'] = 'Template Valutazioni';
$string['newtemplate'] = 'Nuovo Template';
$string['edittemplate'] = 'Modifica Template';
$string['importtemplate'] = 'Importa da Excel';
$string['downloadtemplate'] = 'Scarica Template Excel';
$string['templatename'] = 'Nome Template';
$string['templatedesc'] = 'Descrizione';
$string['sectorcode'] = 'Codice Settore';
$string['behaviors'] = 'Comportamenti Osservabili';
$string['behaviorscount'] = '{$a} comportamenti';
$string['competenciescount'] = '{$a} competenze';
$string['notemplates'] = 'Nessun template trovato';
$string['templatecreated'] = 'Template creato con successo';
$string['templateupdated'] = 'Template aggiornato con successo';
$string['templatedeleted'] = 'Template eliminato';
$string['confirmdeletetemplate'] = 'Sei sicuro di voler eliminare questo template?';

// Import
$string['importexcel'] = 'Importa File Excel';
$string['selectfile'] = 'Seleziona file Excel';
$string['importpreview'] = 'Anteprima Importazione';
$string['importconfirm'] = 'Conferma Importazione';
$string['importsuccess'] = 'Importazione completata: {$a->behaviors} comportamenti, {$a->competencies} mappature competenze';
$string['importerror'] = 'Errore importazione: {$a}';
$string['downloadexample'] = 'Scarica Excel di Esempio';
$string['excelformat'] = 'L\'Excel deve avere le colonne: Comportamento, Codice Competenza, Descrizione, Peso (1-3)';

// Behaviors
$string['behavior'] = 'Comportamento Osservabile';
$string['addbehavior'] = 'Aggiungi Comportamento';
$string['editbehavior'] = 'Modifica Comportamento';
$string['deletebehavior'] = 'Elimina Comportamento';
$string['competency'] = 'Competenza';
$string['competencies'] = 'Competenze';
$string['weight'] = 'Peso';
$string['weightprimary'] = 'Principale (3)';
$string['weightsecondary'] = 'Secondario (1)';

// Assignments
$string['assignevaluation'] = 'Assegna Valutazione';
$string['assignto'] = 'Assegna a';
$string['selectstudents'] = 'Seleziona Studenti';
$string['selecttemplate'] = 'Seleziona Template';
$string['duedate'] = 'Data Scadenza';
$string['assignmentcreated'] = 'Valutazione assegnata a {$a} studenti';
$string['noassignments'] = 'Nessuna assegnazione trovata';
$string['assignmentstatus'] = 'Stato';
$string['pending'] = 'In attesa';
$string['completed'] = 'Completata';
$string['expired'] = 'Scaduta';

// Evaluation
$string['evaluate'] = 'Valuta';
$string['evaluatestudent'] = 'Valuta Studente';
$string['evaluationform'] = 'Scheda di Valutazione';
$string['ratingscale'] = 'Scala di Valutazione';
$string['rating0'] = 'Non osservato / N/A';
$string['rating1'] = 'Da migliorare';
$string['rating3'] = 'Adeguato / Competente';
$string['savenotes'] = 'Salva Note';
$string['savedraft'] = 'Salva Bozza';
$string['completeevaluation'] = 'Completa Valutazione';
$string['evaluationcompleted'] = 'Valutazione completata con successo';
$string['evaluationsaved'] = 'Valutazione salvata come bozza';
$string['confirmevaluation'] = 'Confermare e completare la valutazione?';
$string['totalscore'] = 'Punteggio Totale';
$string['maxscore'] = 'Punteggio Massimo';
$string['percentage'] = 'Percentuale';
$string['generalnotes'] = 'Note Generali';

// Reports
$string['studentreport'] = 'Report Studente';
$string['integratedreport'] = 'Report Integrato';
$string['selectsources'] = 'Seleziona Fonti Dati';
$string['source_quiz'] = 'Quiz (conoscenze teoriche)';
$string['source_selfassess'] = 'Autovalutazione (Bloom)';
$string['source_labeval'] = 'Prove Pratiche (laboratorio)';
$string['selectvisualization'] = 'Seleziona Visualizzazione';
$string['viz_radar_table'] = 'Radar + Tabella Gap (consigliato)';
$string['viz_radar_only'] = 'Solo Radar';
$string['viz_table_only'] = 'Solo Tabella';
$string['options'] = 'Opzioni';
$string['showallcompetencies'] = 'Mostra tutte le competenze dell\'area';
$string['highlightgaps'] = 'Evidenzia gap significativi (>15%)';
$string['includesuggestions'] = 'Includi suggerimenti per colloquio';
$string['generatereport'] = 'Genera Report';
$string['printpdf'] = 'Stampa PDF';
$string['sendtostudent'] = 'Invia a Studente';

// Gap analysis
$string['gapanalysis'] = 'Analisi Gap';
$string['quiz'] = 'Quiz';
$string['selfassessment'] = 'Autovalutazione';
$string['practical'] = 'Pratica';
$string['gap'] = 'Gap';
$string['aligned'] = 'Allineato';
$string['activate'] = 'Attiva';
$string['nottested'] = 'Non testato';

// Authorization
$string['authorizestudent'] = 'Autorizza Studente';
$string['authorizedstudents'] = 'Studenti Autorizzati';
$string['studentauthorized'] = 'Studente autorizzato a visualizzare il report';
$string['studentunauthorized'] = 'Autorizzazione rimossa';

// PDF
$string['reporttitle'] = 'Report Competenze Integrato';
$string['datasources'] = 'Fonti Dati Incluse';
$string['competencydetail'] = 'Dettaglio Competenze';
$string['coachnotes'] = 'Note e Osservazioni del Coach';
$string['interviewdate'] = 'Data Colloquio';
$string['coachsignature'] = 'Firma Coach';
$string['studentsignature'] = 'Firma Studente';

// Misc
$string['nodata'] = 'Nessun dato disponibile';
$string['student'] = 'Studente';
$string['coach'] = 'Coach';
$string['date'] = 'Data';
$string['actions'] = 'Azioni';
$string['view'] = 'Visualizza';
$string['edit'] = 'Modifica';
$string['delete'] = 'Elimina';
$string['save'] = 'Salva';
$string['cancel'] = 'Annulla';
$string['confirm'] = 'Conferma';
$string['success'] = 'Operazione completata con successo';
$string['error'] = 'Si è verificato un errore';
$string['back'] = 'Indietro';
$string['next'] = 'Avanti';
