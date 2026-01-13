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
 * English language strings for FTM Scheduler.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'FTM Scheduler';
$string['ftm_scheduler'] = 'FTM Scheduler';

// Navigation
$string['dashboard'] = 'Dashboard';
$string['calendario'] = 'Calendar';
$string['gruppi'] = 'Groups';
$string['attivita'] = 'Activities';
$string['aule'] = 'Rooms';
$string['atelier'] = 'Workshops';

// Page titles
$string['scheduler_title'] = '📅 FTM Scheduler';
$string['dashboard_segreteria'] = 'Secretary Dashboard';

// Groups
$string['gruppo'] = 'Group';
$string['gruppi_attivi'] = 'Active Groups';
$string['nuovo_gruppo'] = 'New Group';
$string['crea_gruppo'] = 'Create Group and Generate Activities';
$string['modifica_gruppo'] = 'Edit Group';
$string['elimina_gruppo'] = 'Delete Group';
$string['gruppo_giallo'] = 'Yellow Group';
$string['gruppo_grigio'] = 'Gray Group';
$string['gruppo_rosso'] = 'Red Group';
$string['gruppo_marrone'] = 'Brown Group';
$string['gruppo_viola'] = 'Purple Group';

// Colors
$string['colore'] = 'Color';
$string['giallo'] = 'Yellow';
$string['grigio'] = 'Gray';
$string['rosso'] = 'Red';
$string['marrone'] = 'Brown';
$string['viola'] = 'Purple';
$string['seleziona_colore'] = 'Select Color';

// Status
$string['status'] = 'Status';
$string['status_planning'] = 'Planning';
$string['status_active'] = 'Active';
$string['status_completed'] = 'Completed';
$string['attivo'] = 'Active';
$string['in_arrivo'] = 'Upcoming';
$string['completato'] = 'Completed';

// Dates and times
$string['data_inizio'] = 'Start Date';
$string['data_fine'] = 'End Date';
$string['data_fine_prevista'] = 'Expected End';
$string['orario'] = 'Time';
$string['mattina'] = 'Morning';
$string['pomeriggio'] = 'Afternoon';
$string['settimana'] = 'Week';
$string['settimana_corrente'] = 'Current week';
$string['settimana_num'] = 'Week {$a}';
$string['kw'] = 'CW';

// Students
$string['studenti'] = 'Students';
$string['studenti_assegnati'] = 'Assigned students';
$string['studenti_iscritti'] = 'Enrolled Students';
$string['studenti_da_assegnare'] = 'Students to Assign';
$string['studenti_selezionati'] = '{$a} students selected';
$string['iscritti'] = 'Enrolled';
$string['altri_studenti'] = '... {$a} more students';

// Activities
$string['attivita'] = 'Activities';
$string['nuova_attivita'] = 'New Activity';
$string['tipo_attivita'] = 'Type';
$string['attivita_settimana'] = 'Activities this week';
$string['week1'] = 'Week 1';
$string['week2'] = 'Week 2';
$string['week1_auto'] = 'Week 1 (Automatic)';

// Rooms
$string['aula'] = 'Room';
$string['aule'] = 'Rooms';
$string['aule_utilizzate'] = 'Rooms used';
$string['postazioni'] = 'workstations';
$string['laboratorio'] = 'Laboratory';
$string['teoria'] = 'Theory';
$string['aula_libera'] = 'Available';
$string['aula_occupata'] = 'Occupied';

// Coach/Teacher
$string['docente'] = 'Teacher';
$string['coach'] = 'Coach';
$string['responsabile'] = 'Responsible';

// External bookings
$string['prenota_aula_esterno'] = 'Book Room (External)';
$string['progetto_esterno'] = 'External Project';
$string['progetti_esterni'] = 'External Projects';
$string['nome_progetto'] = 'Project Name';
$string['fascia_oraria'] = 'Time Slot';
$string['tutto_giorno'] = 'All day';
$string['solo_mattina'] = 'Morning only';
$string['solo_pomeriggio'] = 'Afternoon only';

// Actions
$string['visualizza'] = 'View';
$string['modifica'] = 'Edit';
$string['elimina'] = 'Delete';
$string['salva'] = 'Save';
$string['annulla'] = 'Cancel';
$string['chiudi'] = 'Close';
$string['dettagli'] = 'Details';
$string['export_excel'] = 'Export Excel';

// Filters
$string['tutti'] = 'All';
$string['tutti_gruppi'] = 'All groups';
$string['tutte_aule'] = 'All rooms';
$string['tutti_tipi'] = 'All types';

// Stats
$string['stats_gruppi_attivi'] = 'Active Groups';
$string['stats_studenti'] = 'Students in Program';
$string['stats_attivita'] = 'Weekly Activities';
$string['stats_aule'] = 'Rooms Used';
$string['stats_conflitti'] = 'Conflicts';

// Notifications
$string['notifica'] = 'Notification';
$string['notifica_inviata'] = 'Sent';
$string['notifica_non_inviata'] = 'Not sent';

// Atelier
$string['catalogo_atelier'] = 'Workshop Catalog';
$string['atelier_disponibili'] = 'Workshops available from Week 3';
$string['settimana_tipica'] = 'Typical Week';
$string['max_partecipanti'] = 'Max Participants';
$string['obbligatorio'] = 'Mandatory';
$string['bilancio_fine_misura'] = 'End of program assessment';

// Week 2 choices
$string['scelte_settimana2'] = 'Week 2 Choices';
$string['da_completare'] = 'to complete';
$string['test_teorico'] = 'Theory Test';
$string['lab_pratico'] = 'Practical Lab';
$string['seleziona_test'] = '-- Select Test --';
$string['seleziona_lab'] = '-- Select Lab --';

// Messages
$string['gruppo_creato'] = 'Group created successfully. Week 1 activities have been automatically generated.';
$string['attivita_create'] = 'Week 1 activities have been automatically generated.';
$string['info_creazione_gruppo'] = 'What happens when you create the group:';
$string['info_creazione_1'] = 'Week 1 activities are created automatically';
$string['info_creazione_2'] = 'All students are enrolled in activities';
$string['info_creazione_3'] = 'Email + calendar notifications sent to students';

// Legend
$string['legenda'] = 'Legend';

// Progress
$string['progresso'] = 'Progress';

// Remote
$string['remoto'] = 'REMOTE';

// Errors
$string['error_no_permission'] = 'You do not have permission to access this page.';
$string['error_group_not_found'] = 'Group not found.';
$string['error_activity_not_found'] = 'Activity not found.';

// Capabilities
$string['ftm_scheduler:view'] = 'View FTM Scheduler';
$string['ftm_scheduler:manage'] = 'Manage FTM Scheduler';
$string['ftm_scheduler:managegroups'] = 'Manage groups';
$string['ftm_scheduler:manageactivities'] = 'Manage activities';
$string['ftm_scheduler:managerooms'] = 'Manage rooms';
$string['ftm_scheduler:enrollstudents'] = 'Enroll students';
