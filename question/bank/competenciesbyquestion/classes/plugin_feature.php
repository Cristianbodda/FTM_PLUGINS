<?php
namespace qbank_competenciesbyquestion;

// Entry point for the qbank_competenciesbyquestion plugin.
defined('MOODLE_INTERNAL') || die();

use core_question\local\bank\plugin_features_base;
use core_question\local\bank\view;
use qbank_competenciesbyquestion\columns\competency_column;

/**
 * Plugin feature class for the question bank plugin.
 *
 * Registers extra columns in the question bank view.
 */
class plugin_feature extends plugin_features_base {
    /**
     * Extra columns provided by this plugin.
     *
     * @param view $qbank
     * @return array
     */
    public function get_question_columns(view $qbank): array {
        return [
            new competency_column($qbank),
        ];
    }
}