<?php
/**
 * Italian language strings for FTM Test Suite
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin info
$string['pluginname'] = 'FTM Test Suite';
$string['plugindescription'] = 'Suite di test completa per verifica pre-produzione sistema FTM';

// Capabilities
$string['ftm_testsuite:manage'] = 'Gestire FTM Test Suite';
$string['ftm_testsuite:execute'] = 'Eseguire test FTM';
$string['ftm_testsuite:viewresults'] = 'Visualizzare risultati test';

// Navigation
$string['dashboard'] = 'Dashboard';
$string['generatedata'] = 'Genera Dati Test';
$string['runtests'] = 'Esegui Test';
$string['results'] = 'Risultati';
$string['history'] = 'Storico';
$string['settings'] = 'Impostazioni';

// Dashboard
$string['welcome'] = 'Benvenuto in FTM Test Suite';
$string['welcomedesc'] = 'Sistema completo per verificare il funzionamento di tutti i plugin FTM prima della produzione.';
$string['quickactions'] = 'Azioni Rapide';
$string['recentruns'] = 'Esecuzioni Recenti';
$string['systemstatus'] = 'Stato Sistema';

// Test Users
$string['testusers'] = 'Utenti Test';
$string['createtestusers'] = 'Crea Utenti Test';
$string['testuser_low'] = '[TEST] Studente Critico (30%)';
$string['testuser_medium'] = '[TEST] Studente Sufficiente (65%)';
$string['testuser_high'] = '[TEST] Studente Eccellente (95%)';
$string['testusercreated'] = 'Utente test creato: {$a}';
$string['testusersexist'] = 'Utenti test già esistenti';

// Data Generation
$string['generatingdata'] = 'Generazione Dati Test';
$string['selectcourse'] = 'Seleziona Corso';
$string['allcourses'] = 'Tutti i corsi';
$string['generatequizdata'] = 'Genera Tentativi Quiz';
$string['generateselfassessment'] = 'Genera Autovalutazioni';
$string['generatelabeval'] = 'Genera Valutazioni Lab';
$string['datagenerated'] = 'Dati test generati con successo';
$string['generateall'] = 'Genera Tutti i Dati';
$string['cleanupdata'] = 'Pulisci Dati Test';
$string['cleanupdataconfirm'] = 'Sei sicuro di voler eliminare tutti i dati test? Questa azione non può essere annullata.';
$string['datacleaned'] = 'Dati test eliminati con successo';

// Test Execution
$string['executingtests'] = 'Esecuzione Test';
$string['selectmodules'] = 'Seleziona Moduli da Testare';
$string['module_quiz'] = 'Modulo 1: Quiz e Competenze';
$string['module_selfassessment'] = 'Modulo 2: Autovalutazioni';
$string['module_labeval'] = 'Modulo 3: LabEval';
$string['module_radar'] = 'Modulo 4: Aggregazione e Radar';
$string['module_report'] = 'Modulo 5: Report';
$string['runalltests'] = 'Esegui Tutti i Test';
$string['runselectedtests'] = 'Esegui Test Selezionati';

// Test Status
$string['status_passed'] = 'Passato';
$string['status_failed'] = 'Fallito';
$string['status_warning'] = 'Warning';
$string['status_skipped'] = 'Saltato';
$string['status_running'] = 'In esecuzione';
$string['status_completed'] = 'Completato';

// Results
$string['testresults'] = 'Risultati Test';
$string['summary'] = 'Riepilogo';
$string['totaltests'] = 'Test Totali';
$string['passedtests'] = 'Test Passati';
$string['failedtests'] = 'Test Falliti';
$string['warningtests'] = 'Warning';
$string['successrate'] = 'Tasso Successo';
$string['executiontime'] = 'Tempo Esecuzione';
$string['viewdetails'] = 'Vedi Dettagli';
$string['viewtrace'] = 'Vedi Trace Calcolo';
$string['viewsql'] = 'Vedi Query SQL';

// Trace
$string['tracecalculation'] = 'Trace Calcolo';
$string['step'] = 'Step';
$string['query'] = 'Query';
$string['result'] = 'Risultato';
$string['formula'] = 'Formula';
$string['expected'] = 'Atteso';
$string['actual'] = 'Ottenuto';
$string['match'] = 'Match';
$string['mismatch'] = 'Mismatch';

// PDF Report
$string['generatepdf'] = 'Genera PDF Certificazione';
$string['pdfgenerated'] = 'PDF generato con successo';
$string['pdftitle'] = 'Certificazione Sistema FTM';
$string['pdfsubtitle'] = 'Report Verifica Pre-Produzione';
$string['systeminfo'] = 'Informazioni Sistema';
$string['testcoverage'] = 'Copertura Test';
$string['calculationverification'] = 'Verifica Calcoli';
$string['datatraceability'] = 'Tracciabilità Dati';
$string['integritycheck'] = 'Verifica Integrità';

// Hash Integrity
$string['hashintegrity'] = 'Hash Integrità';
$string['hashvalid'] = 'Hash valido - Dati non modificati';
$string['hashinvalid'] = 'Hash non valido - Possibile alterazione dati';
$string['verifyhash'] = 'Verifica Hash';

// Test Names - Module 1: Quiz
$string['test_1_1'] = 'Domande con competenze';
$string['test_1_1_desc'] = 'Verifica che ogni domanda del quiz abbia almeno una competenza assegnata';
$string['test_1_2'] = 'Competenze esistenti';
$string['test_1_2_desc'] = 'Verifica che le competenze assegnate esistano nella tabella competency';
$string['test_1_3'] = 'Framework corretto';
$string['test_1_3_desc'] = 'Verifica che le competenze appartengano al framework corretto per il settore';
$string['test_1_4'] = 'Domande orfane';
$string['test_1_4_desc'] = 'Conta le domande del quiz senza competenze assegnate';
$string['test_1_5'] = 'Risposte registrate';
$string['test_1_5_desc'] = 'Verifica che esistano risposte per ogni tentativo quiz';
$string['test_1_6'] = 'Fraction valida';
$string['test_1_6_desc'] = 'Verifica che i valori fraction siano nel range 0-1';
$string['test_1_7'] = 'Calcolo manuale';
$string['test_1_7_desc'] = 'Confronta il calcolo manuale delle percentuali con i valori mostrati';
$string['test_1_8'] = 'Parsing idnumber';
$string['test_1_8_desc'] = 'Verifica che gli idnumber delle competenze rispettino il formato atteso';

// Test Names - Module 2: Self Assessment
$string['test_2_1'] = 'Competenze valide';
$string['test_2_1_desc'] = 'Verifica che le autovalutazioni puntino a competenze esistenti';
$string['test_2_2'] = 'Livelli Bloom';
$string['test_2_2_desc'] = 'Verifica che i livelli Bloom siano nel range 1-6';
$string['test_2_3'] = 'Utente valido';
$string['test_2_3_desc'] = 'Verifica che le autovalutazioni appartengano a utenti esistenti';
$string['test_2_4'] = 'Match con quiz';
$string['test_2_4_desc'] = 'Verifica che le competenze autovalutate corrispondano a quelle testate';
$string['test_2_5'] = 'Calcolo media Bloom';
$string['test_2_5_desc'] = 'Confronta il calcolo della media Bloom con il valore mostrato';

// Test Names - Module 3: LabEval
$string['test_3_1'] = 'Template attivi';
$string['test_3_1_desc'] = 'Verifica che esistano template attivi per le prove lab';
$string['test_3_2'] = 'Comportamenti definiti';
$string['test_3_2_desc'] = 'Verifica che ogni template abbia comportamenti osservabili';
$string['test_3_3'] = 'Mapping competenze';
$string['test_3_3_desc'] = 'Verifica che i comportamenti siano collegati a competenze valide';
$string['test_3_4'] = 'Sessioni complete';
$string['test_3_4_desc'] = 'Verifica che le sessioni complete abbiano tutti i ratings';
$string['test_3_5'] = 'Rating validi';
$string['test_3_5_desc'] = 'Verifica che i rating siano 0, 1 o 3';
$string['test_3_6'] = 'Calcolo punteggio';
$string['test_3_6_desc'] = 'Confronta il calcolo del punteggio totale con i ratings';
$string['test_3_7'] = 'Cache aggiornata';
$string['test_3_7_desc'] = 'Verifica che la cache comp_scores sia aggiornata';

// Test Names - Module 4: Radar
$string['test_4_1'] = 'Aree estratte';
$string['test_4_1_desc'] = 'Verifica che tutte le aree siano correttamente estratte dagli idnumber';
$string['test_4_2'] = 'Somma per area';
$string['test_4_2_desc'] = 'Verifica che la somma delle domande per area sia corretta';
$string['test_4_3'] = 'Percentuali area';
$string['test_4_3_desc'] = 'Verifica che le percentuali per area siano calcolate correttamente';
$string['test_4_4'] = 'Range radar';
$string['test_4_4_desc'] = 'Verifica che tutti i valori del radar siano nel range 0-100';
$string['test_4_5'] = 'Gap Analysis';
$string['test_4_5_desc'] = 'Verifica che i gap tra autovalutazione e quiz siano corretti';

// Test Names - Module 5: Report
$string['test_5_1'] = 'Totale competenze';
$string['test_5_1_desc'] = 'Verifica che il totale competenze nel report sia corretto';
$string['test_5_2'] = 'Piano azione';
$string['test_5_2_desc'] = 'Verifica che la somma delle categorie del piano sia uguale al totale';
$string['test_5_3'] = 'Classificazione';
$string['test_5_3_desc'] = 'Verifica che ogni competenza sia in una sola categoria';
$string['test_5_4'] = 'Progressi';
$string['test_5_4_desc'] = 'Verifica che il grafico progressi mostri i tentativi corretti';

// Errors
$string['error_notestusers'] = 'Nessun utente test trovato. Crea prima gli utenti test.';
$string['error_nodata'] = 'Nessun dato test trovato. Genera prima i dati test.';
$string['error_nocourse'] = 'Corso non trovato';
$string['error_noquiz'] = 'Nessun quiz trovato nel corso';
$string['error_nocompetencies'] = 'Nessuna competenza trovata';
$string['error_runfailed'] = 'Esecuzione test fallita';

// Glossary
$string['glossary'] = 'Glossario';
$string['glossary_percentage'] = 'Percentuale competenza: Rapporto risposte corrette su totale per quella competenza. Formula: (Σ fraction) / n_domande * 100';
$string['glossary_bloom'] = 'Livello Bloom: Scala 1-6 della tassonomia di Bloom. 1=Ricordare, 2=Comprendere, 3=Applicare, 4=Analizzare, 5=Valutare, 6=Creare';
$string['glossary_gap'] = 'Gap Analysis: Differenza tra autovalutazione e performance reale. Formula: (Bloom/6*100) - %quiz';
$string['glossary_radar'] = 'Radar Aree: Aggregazione competenze per area tematica. Formula: media(% competenze area)';
$string['glossary_rating'] = 'Rating LabEval: Punteggio comportamento osservabile. 0=Non osservato, 1=Parziale, 3=Adeguato';

// Misc
$string['notavailable'] = 'Non disponibile';
$string['nodata'] = 'Nessun dato';
$string['loading'] = 'Caricamento...';
$string['confirm'] = 'Conferma';
$string['cancel'] = 'Annulla';
$string['save'] = 'Salva';
$string['delete'] = 'Elimina';
$string['export'] = 'Esporta';
$string['print'] = 'Stampa';
$string['refresh'] = 'Aggiorna';
$string['back'] = 'Indietro';
$string['next'] = 'Avanti';
$string['finish'] = 'Termina';
