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
 * Post-installation script for FTM Scheduler.
 *
 * @package    local_ftm_scheduler
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Post-installation procedure.
 */
function xmldb_local_ftm_scheduler_install() {
    global $DB;
    
    // Insert default rooms
    $rooms = [
        (object)[
            'name' => 'AULA 1',
            'shortname' => 'A1',
            'capacity' => 8,
            'is_lab' => 1,
            'capabilities_json' => json_encode(['Elettricità', 'Automazione', 'Pneumatica', 'Idraulica', 'AutoCAD']),
            'color_hex' => '#DBEAFE',
            'sortorder' => 1,
            'active' => 1,
        ],
        (object)[
            'name' => 'AULA 2',
            'shortname' => 'A2',
            'capacity' => 20,
            'is_lab' => 0,
            'capabilities_json' => json_encode(['Lezioni', 'Quiz/Test', 'Atelier', 'Teoria']),
            'color_hex' => '#D1FAE5',
            'sortorder' => 2,
            'active' => 1,
        ],
        (object)[
            'name' => 'AULA 3',
            'shortname' => 'A3',
            'capacity' => 12,
            'is_lab' => 1,
            'capabilities_json' => json_encode(['CNC Fresa', 'CNC Tornio', 'SolidWorks']),
            'color_hex' => '#FEF3C7',
            'sortorder' => 3,
            'active' => 1,
        ],
    ];
    
    foreach ($rooms as $room) {
        $DB->insert_record('local_ftm_rooms', $room);
    }
    
    // Get AULA 2 ID for default room
    $aula2 = $DB->get_record('local_ftm_rooms', ['shortname' => 'A2']);
    $aula2_id = $aula2 ? $aula2->id : null;
    
    // Insert Week 1 template activities (from Excel)
    $week1_template = [
        // Lunedì
        (object)[
            'name' => 'Spiegazione percorso + Formulari iniziali',
            'day_of_week' => 1,
            'time_slot' => 'matt',
            'time_start' => '08:30',
            'time_end' => '11:45',
            'default_roomid' => $aula2_id,
            'is_mandatory' => 1,
            'is_remote' => 0,
            'description' => 'Prima attività di accoglienza',
            'sortorder' => 1,
            'active' => 1,
        ],
        (object)[
            'name' => 'Piano d\'azione + F3MAcademy + Stage',
            'day_of_week' => 1,
            'time_slot' => 'pom',
            'time_start' => '13:15',
            'time_end' => '16:30',
            'default_roomid' => $aula2_id,
            'is_mandatory' => 1,
            'is_remote' => 0,
            'description' => 'Presentazione piattaforma e stage',
            'sortorder' => 2,
            'active' => 1,
        ],
        // Martedì
        (object)[
            'name' => 'Test Entrata + Autovalutazione',
            'day_of_week' => 2,
            'time_slot' => 'matt',
            'time_start' => '08:30',
            'time_end' => '11:45',
            'default_roomid' => $aula2_id,
            'is_mandatory' => 1,
            'is_remote' => 0,
            'description' => 'Test FTM + Self-assessment',
            'sortorder' => 3,
            'active' => 1,
        ],
        (object)[
            'name' => 'Test Entrata + Autovalutazione (cont.)',
            'day_of_week' => 2,
            'time_slot' => 'pom',
            'time_start' => '13:15',
            'time_end' => '16:30',
            'default_roomid' => $aula2_id,
            'is_mandatory' => 1,
            'is_remote' => 0,
            'description' => 'Continuazione se necessario',
            'sortorder' => 4,
            'active' => 1,
        ],
        // Mercoledì - REMOTO (no activity created)
        // Giovedì
        (object)[
            'name' => 'Atelier CVBD redazione',
            'day_of_week' => 4,
            'time_slot' => 'matt',
            'time_start' => '08:30',
            'time_end' => '11:45',
            'default_roomid' => $aula2_id,
            'is_mandatory' => 1,
            'is_remote' => 0,
            'description' => 'CV Base Dati',
            'sortorder' => 5,
            'active' => 1,
        ],
        (object)[
            'name' => 'CVBD + Job room',
            'day_of_week' => 4,
            'time_slot' => 'pom',
            'time_start' => '13:15',
            'time_end' => '16:30',
            'default_roomid' => $aula2_id,
            'is_mandatory' => 1,
            'is_remote' => 0,
            'description' => 'Caricamento in Jobroom',
            'sortorder' => 6,
            'active' => 1,
        ],
        // Venerdì
        (object)[
            'name' => 'Newsletter + Redazione documento',
            'day_of_week' => 5,
            'time_slot' => 'matt',
            'time_start' => '08:30',
            'time_end' => '11:45',
            'default_roomid' => $aula2_id,
            'is_mandatory' => 1,
            'is_remote' => 0,
            'description' => 'Preparazione documenti',
            'sortorder' => 7,
            'active' => 1,
        ],
        // Venerdì pomeriggio - REMOTO (no activity created)
    ];
    
    foreach ($week1_template as $tpl) {
        $DB->insert_record('local_ftm_week1_template', $tpl);
    }
    
    // Insert Atelier catalog (from Excel)
    $atelier_catalog = [
        (object)[
            'name' => 'Canali - strumenti e mercato del lavoro',
            'shortname' => 'At. Canali',
            'typical_week_start' => 3,
            'typical_week_end' => 5,
            'typical_day' => 3, // Wednesday
            'typical_slot' => 'matt',
            'max_participants' => 10,
            'is_mandatory' => 0,
            'mandatory_week' => null,
            'description' => 'Iscrizione Coach sulla base PCI',
            'sortorder' => 1,
            'active' => 1,
        ],
        (object)[
            'name' => 'Colloquio di lavoro',
            'shortname' => 'At. Collo.',
            'typical_week_start' => 3,
            'typical_week_end' => 5,
            'typical_day' => 3,
            'typical_slot' => 'pom',
            'max_participants' => 10,
            'is_mandatory' => 0,
            'mandatory_week' => null,
            'description' => 'Simulazione colloqui',
            'sortorder' => 2,
            'active' => 1,
        ],
        (object)[
            'name' => 'Curriculum Vitae - redazione/revisione',
            'shortname' => 'At. CV',
            'typical_week_start' => 3,
            'typical_week_end' => 5,
            'typical_day' => 3,
            'typical_slot' => null,
            'max_participants' => 10,
            'is_mandatory' => 0,
            'mandatory_week' => null,
            'description' => 'Redazione/revisione CV',
            'sortorder' => 3,
            'active' => 1,
        ],
        (object)[
            'name' => 'Lettere AC + RA - redazione/revisione',
            'shortname' => 'At. AC/RA',
            'typical_week_start' => 4,
            'typical_week_end' => 6,
            'typical_day' => 3,
            'typical_slot' => null,
            'max_participants' => 10,
            'is_mandatory' => 0,
            'mandatory_week' => null,
            'description' => 'Lettere accompagnamento e risposta annunci',
            'sortorder' => 4,
            'active' => 1,
        ],
        (object)[
            'name' => 'Agenzie e guadagno intermedio',
            'shortname' => 'At. Ag. e GI',
            'typical_week_start' => 4,
            'typical_week_end' => 6,
            'typical_day' => 3,
            'typical_slot' => 'matt',
            'max_participants' => 10,
            'is_mandatory' => 0,
            'mandatory_week' => null,
            'description' => 'Iscrizione agenzie interinali',
            'sortorder' => 5,
            'active' => 1,
        ],
        (object)[
            'name' => 'Bilancio di fine misura',
            'shortname' => 'BILANCIO',
            'typical_week_start' => 6,
            'typical_week_end' => 6,
            'typical_day' => 3,
            'typical_slot' => 'pom',
            'max_participants' => 10,
            'is_mandatory' => 1,
            'mandatory_week' => 6,
            'description' => 'OBBLIGATORIO - Bilancio di fine rilevamento',
            'sortorder' => 6,
            'active' => 1,
        ],
    ];
    
    foreach ($atelier_catalog as $atelier) {
        $DB->insert_record('local_ftm_atelier_catalog', $atelier);
    }
    
    return true;
}
