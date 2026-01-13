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
 * Upgrade script for local_labeval
 *
 * @package    local_labeval
 * @copyright  2024 FTM - Formazione Tecnica Meccanica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_labeval_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Upgrade per supportare valutazione per competenza (non più per comportamento)
    if ($oldversion < 2025010901) {
        
        // 1. Aggiungi il campo competencycode alla tabella ratings
        $table = new xmldb_table('local_labeval_ratings');
        $field = new xmldb_field('competencycode', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'behaviorid');
        
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // 2. Rimuovi l'indice unico esistente (sessionid, behaviorid)
        $index = new xmldb_index('sesbeh_uix', XMLDB_INDEX_UNIQUE, ['sessionid', 'behaviorid']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        
        // 3. Crea il nuovo indice unico (sessionid, behaviorid, competencycode)
        $newindex = new xmldb_index('sesbehcomp_uix', XMLDB_INDEX_UNIQUE, ['sessionid', 'behaviorid', 'competencycode']);
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }
        
        // 4. Migra i dati esistenti: estrai competencycode dal campo notes
        $records = $DB->get_records('local_labeval_ratings');
        foreach ($records as $record) {
            if (strpos($record->notes, 'COMP:') === 0) {
                // Formato: COMP:MECCANICA_MIS_01:note reali
                $parts = explode(':', $record->notes, 3);
                $compcode = $parts[1] ?? '';
                $realnotes = $parts[2] ?? '';
                
                $record->competencycode = $compcode;
                $record->notes = $realnotes;
                $DB->update_record('local_labeval_ratings', $record);
            } else if (empty($record->competencycode)) {
                // Vecchi record senza competencycode: assegna un valore di default
                // basato sul primo competencycode del behavior
                $compmap = $DB->get_record('local_labeval_behavior_comp', 
                    ['behaviorid' => $record->behaviorid], 'competencycode', IGNORE_MULTIPLE);
                if ($compmap) {
                    $record->competencycode = $compmap->competencycode;
                    $DB->update_record('local_labeval_ratings', $record);
                }
            }
        }
        
        upgrade_plugin_savepoint(true, 2025010901, 'local', 'labeval');
    }

    return true;
}
