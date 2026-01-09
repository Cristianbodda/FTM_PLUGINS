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
 * Italian language strings for local_ftm_suite
 *
 * @package    local_ftm_suite
 * @copyright  2026 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name
$string['pluginname'] = 'FTM Suite Installer';

// Privacy
$string['privacy:metadata'] = 'Il plugin FTM Suite Installer non memorizza alcun dato personale.';

// Capabilities
$string['ftm_suite:viewstatus'] = 'Visualizza stato installazione FTM Suite';

// Status page
$string['pagetitle'] = 'Stato Installazione FTM Suite';
$string['pageheading'] = 'Stato Plugin FTM Suite';
$string['description'] = 'Questa pagina mostra lo stato di installazione di tutti i plugin FTM che compongono la suite completa.';

// Table headers
$string['plugin'] = 'Plugin';
$string['component'] = 'Componente';
$string['requiredversion'] = 'Versione Richiesta';
$string['installedversion'] = 'Versione Installata';
$string['status'] = 'Stato';

// Status labels
$string['status_installed'] = 'Installato';
$string['status_missing'] = 'Mancante';
$string['status_outdated'] = 'Non aggiornato';
$string['status_unknown'] = 'Sconosciuto';

// Plugin descriptions
$string['plugin_qbank_competenciesbyquestion'] = 'Question Bank - Competenze';
$string['plugin_local_competencymanager'] = 'Competency Manager (Core)';
$string['plugin_local_coachmanager'] = 'Coach Manager';
$string['plugin_local_labeval'] = 'Valutazione Laboratorio';
$string['plugin_local_selfassessment'] = 'Autovalutazione';
$string['plugin_local_competencyreport'] = 'Report Competenze';
$string['plugin_local_competencyxmlimport'] = 'Import XML/Word';
$string['plugin_local_ftm_hub'] = 'FTM Hub';
$string['plugin_block_ftm_tools'] = 'Blocco FTM Tools';

// Summary
$string['summary'] = 'Riepilogo';
$string['total_plugins'] = 'Plugin totali';
$string['installed_plugins'] = 'Installati';
$string['missing_plugins'] = 'Mancanti';
$string['outdated_plugins'] = 'Non aggiornati';

// Messages
$string['all_installed'] = 'Tutti i plugin FTM sono installati e aggiornati.';
$string['some_missing'] = 'Alcuni plugin FTM sono mancanti o non aggiornati. Installali per utilizzare la suite completa.';
$string['install_instructions'] = 'Per installare i plugin mancanti, copiali nella directory Moodle appropriata e visita la pagina delle notifiche di amministrazione.';

// Navigation
$string['navigation_label'] = 'Stato FTM Suite';
