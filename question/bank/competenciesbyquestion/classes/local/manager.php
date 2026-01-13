<?php
namespace qbank_competenciesbyquestion\local;

// Helper methods to work with question/competency mapping.
defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * Manager class for qbank_competenciesbyquestion.
 */
class manager {
    
    // Costanti per i livelli di difficoltà
    const LEVEL_BASE = 1;
    const LEVEL_INTERMEDIATE = 2;
    const LEVEL_ADVANCED = 3;
    
    /**
     * Returns the record from qbank_competenciesbyquestion for a given question.
     *
     * @param int $questionid
     * @return stdClass|null
     */
    public static function get_mapping(int $questionid): ?stdClass {
        global $DB;
        return $DB->get_record('qbank_competenciesbyquestion', ['questionid' => $questionid]) ?: null;
    }
    
    /**
     * Returns the competency record for a given question, or null if none.
     *
     * @param int $questionid
     * @return stdClass|null
     */
    public static function get_competency_for_question(int $questionid): ?stdClass {
        global $DB;
        $mapping = self::get_mapping($questionid);
        if (!$mapping) {
            return null;
        }
        return $DB->get_record('competency', ['id' => $mapping->competencyid]) ?: null;
    }
    
    /**
     * Returns the difficulty level for a given question.
     *
     * @param int $questionid
     * @return int Level (1, 2, or 3) or 1 if not set
     */
    public static function get_difficulty_level(int $questionid): int {
        $mapping = self::get_mapping($questionid);
        return $mapping ? (int)$mapping->difficultylevel : self::LEVEL_BASE;
    }
    
    /**
     * Creates/updates/deletes the mapping for a question.
     *
     * @param int $questionid
     * @param int|null $competencyid  Competency id or null/0 to remove mapping.
     * @param int $difficultylevel    Difficulty level (1=Base, 2=Intermediate, 3=Advanced)
     */
    public static function set_competency_for_question(int $questionid, ?int $competencyid, int $difficultylevel = self::LEVEL_BASE): void {
        global $DB;
        $existing = self::get_mapping($questionid);
        
        // Valida il livello di difficoltà
        if (!in_array($difficultylevel, [self::LEVEL_BASE, self::LEVEL_INTERMEDIATE, self::LEVEL_ADVANCED])) {
            $difficultylevel = self::LEVEL_BASE;
        }
        
        if (empty($competencyid)) {
            if ($existing) {
                $DB->delete_records('qbank_competenciesbyquestion', ['questionid' => $questionid]);
            }
            return;
        }
        
        if ($existing) {
            $existing->competencyid = $competencyid;
            $existing->difficultylevel = $difficultylevel;
            $DB->update_record('qbank_competenciesbyquestion', $existing);
        } else {
            $record = new stdClass();
            $record->questionid = $questionid;
            $record->competencyid = $competencyid;
            $record->difficultylevel = $difficultylevel;
            $DB->insert_record('qbank_competenciesbyquestion', $record);
        }
    }
    
    /**
     * Options for the competency selector (id => label).
     *
     * @return array
     */
    public static function get_competency_options(): array {
        global $DB;
        $records = $DB->get_records('competency', null, 'shortname ASC');
        $options = [0 => get_string('competency_none', 'qbank_competenciesbyquestion')];
        
        foreach ($records as $c) {
            $label = $c->shortname ?: ('ID ' . $c->id);
            if (!empty($c->idnumber)) {
                $label .= ' (' . $c->idnumber . ')';
            }
            $options[$c->id] = $label;
        }
        
        return $options;
    }
    
    /**
     * Returns array of difficulty level options for dropdown.
     *
     * @return array
     */
    public static function get_difficulty_options(): array {
        return [
            self::LEVEL_BASE => get_string('level_base', 'qbank_competenciesbyquestion'),
            self::LEVEL_INTERMEDIATE => get_string('level_intermediate', 'qbank_competenciesbyquestion'),
            self::LEVEL_ADVANCED => get_string('level_advanced', 'qbank_competenciesbyquestion'),
        ];
    }
    
    /**
     * Returns human-readable name for a difficulty level.
     *
     * @param int $level
     * @return string
     */
    public static function get_difficulty_name(int $level): string {
        $options = self::get_difficulty_options();
        return $options[$level] ?? $options[self::LEVEL_BASE];
    }
}
