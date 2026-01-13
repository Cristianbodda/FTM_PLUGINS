<?php
namespace qbank_competenciesbyquestion\columns;

defined('MOODLE_INTERNAL') || die();

use core_question\local\bank\column_base;
use moodle_url;
use pix_icon;
use qbank_competenciesbyquestion\local\manager;

class competency_column extends column_base {
    
    public function get_name(): string {
        return 'competencies';
    }
    
    public function get_required_fields(): array {
        return ['q.id'];
    }
    
    public function get_title(): string {
        return get_string('columncompetencies', 'qbank_competenciesbyquestion');
    }
    
    public function is_sortable() {
        return false;
    }
    
    protected function display_content($question, $rowclasses): void {
        global $OUTPUT;
        
        $mapping = manager::get_mapping($question->id);
        
        if ($mapping) {
            // Recupera la competenza
            $competency = manager::get_competency_for_question($question->id);
            
            if ($competency) {
                // Nome della competenza
                echo format_string($competency->shortname);
                echo '<br>';
                
                // Livello di difficoltà con stelline
                $level = $mapping->difficultylevel;
                $levelname = manager::get_difficulty_name($level);
                
                // Stelline in base al livello
                $stars = str_repeat('⭐', $level);
                
                // Mostra le stelline e il nome del livello
                echo '<span style="color: #666; font-size: 0.9em;">';
                echo $stars . ' ' . $levelname;
                echo '</span>';
            } else {
                echo get_string('competency_none', 'qbank_competenciesbyquestion');
            }
        } else {
            echo get_string('competency_none', 'qbank_competenciesbyquestion');
        }
        
        echo ' ';
        
        // Link to edit page.
        $url = new moodle_url('/question/bank/competenciesbyquestion/edit.php', ['id' => $question->id]);
        $icon = new pix_icon('t/edit', get_string('editcompetency', 'qbank_competenciesbyquestion'));
        echo $OUTPUT->action_icon($url, $icon);
    }
}
