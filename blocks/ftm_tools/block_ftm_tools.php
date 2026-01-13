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
 * FTM Tools Block - Main block class.
 *
 * @package    block_ftm_tools
 * @copyright  2026 Cristian Bodda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_ftm_tools extends block_base {

    public function init() {
        $this->title = 'FTM Tools';
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function applicable_formats() {
        return [
            'all' => true,
            'mod' => false,
            'tag' => false,
            'my' => true,
            'course-view' => true,
            'site' => true,
        ];
    }

    public function has_config() {
        return false;
    }

    public function get_content() {
        global $USER, $COURSE, $OUTPUT, $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        $courseid = $COURSE->id;
        if ($courseid == SITEID) {
            $courseid = optional_param('courseid', 0, PARAM_INT);
        }

        $context = context_system::instance();
        if ($courseid > 1) {
            $context = context_course::instance($courseid);
        }

        $is_admin = has_capability('moodle/site:config', context_system::instance());
        $is_teacher = has_capability('moodle/course:manageactivities', $context);
        $is_student = has_capability('mod/quiz:attempt', $context) && !$is_teacher;

        $html = '<div class="ftm-tools-block">';
        $html .= $this->get_block_styles();

        // ===== AREA COACH =====
        if ($is_admin || $is_teacher) {
            $html .= '<div class="ftm-section">';
            $html .= '<div class="ftm-section-title">👨‍🏫 Area Coach</div>';
            $html .= '<ul class="ftm-links">';

            if ($courseid > 1) {
                $html .= $this->render_link('📊', 'Report Classe',
                    new moodle_url('/local/competencymanager/reports.php', ['courseid' => $courseid]));
                $html .= $this->render_link('👤', 'Student Report',
                    new moodle_url('/local/competencymanager/student_report.php', ['courseid' => $courseid]));
            }

            $html .= $this->render_link('👥', 'Lista Studenti',
                new moodle_url('/local/coachmanager/index.php'));

            if ($courseid > 1) {
                $html .= $this->render_link('🎯', 'Gestione Autovalutazioni',
                    new moodle_url('/local/selfassessment/index.php', ['courseid' => $courseid]));
            }

            $html .= $this->render_link('📋', 'Report Colloqui',
                new moodle_url('/local/coachmanager/reports_v2.php'));

            $labevalparams = $courseid > 1 ? ['courseid' => $courseid] : [];
            $html .= $this->render_link('🔬', 'Valutazione Laboratorio',
                new moodle_url('/local/labeval/index.php', $labevalparams));

            $hubparams = $courseid > 1 ? ['courseid' => $courseid] : [];
            $html .= $this->render_link('📚', 'Hub Completo',
                new moodle_url('/local/ftm_hub/index.php', $hubparams));

            $html .= '</ul></div>';
        }

        // ===== STRUMENTI CORSO =====
        if (($is_admin || $is_teacher) && $courseid > 1) {
            $html .= '<div class="ftm-section">';
            $html .= '<div class="ftm-section-title">🔧 Strumenti Corso</div>';
            $html .= '<ul class="ftm-links">';

            $html .= $this->render_link('🏠', 'Dashboard',
                new moodle_url('/local/competencymanager/dashboard.php', ['courseid' => $courseid]));
            $html .= $this->render_link('📝', 'Crea Quiz',
                new moodle_url('/local/competencymanager/create_quiz.php', ['courseid' => $courseid]));
            $html .= $this->render_link('⚙️', 'Gestione Competenze',
                new moodle_url('/local/competencymanager/manage_competencies.php', ['courseid' => $courseid]));
            $html .= $this->render_link('➕', 'Assegna Competenze',
                new moodle_url('/local/competencymanager/assign_competencies.php', ['courseid' => $courseid]));
            $html .= $this->render_link('🔐', 'Autorizzazioni',
                new moodle_url('/local/competencymanager/authorize.php', ['courseid' => $courseid]));
            $html .= $this->render_link('📥', 'Esporta Dati',
                new moodle_url('/local/competencymanager/export.php', ['courseid' => $courseid]));

            $html .= '</ul></div>';
        }

        // ===== DEBUG & TEST =====
        if ($is_admin) {
            $html .= '<div class="ftm-section">';
            $html .= '<div class="ftm-section-title">🐛 Debug & Test</div>';
            $html .= '<ul class="ftm-links">';

            if ($courseid > 1) {
                $html .= $this->render_link('🔍', 'Question Check',
                    new moodle_url('/local/competencymanager/question_check.php', ['courseid' => $courseid]));
                $html .= $this->render_link('🐛', 'Debug Competencies',
                    new moodle_url('/local/competencymanager/debug_competencies.php', ['courseid' => $courseid]));
                $html .= $this->render_link('🩺', 'Diagnostics',
                    new moodle_url('/local/competencymanager/diagnostics.php', ['courseid' => $courseid]));
            }

            $html .= $this->render_link('📊', 'Coverage Manager',
                new moodle_url('/local/competencymanager/coverage_report.php'));
            $html .= $this->render_link('🤖', 'Simulatore Studente',
                new moodle_url('/local/competencymanager/simulate_student.php'));
            $html .= $this->render_link('🧪', 'Test Suite',
                new moodle_url('/local/ftm_testsuite/index.php'));

            $html .= '</ul></div>';
        }

        // ===== AREA STUDENTE =====
        if ($is_student || $is_admin || $is_teacher) {
            $html .= '<div class="ftm-section">';
            $html .= '<div class="ftm-section-title">👨‍🎓 Area Studente</div>';
            $html .= '<ul class="ftm-links">';

            if ($courseid > 1) {
                $html .= $this->render_link('📊', 'Il Mio Report',
                    new moodle_url('/local/competencymanager/student_report.php', [
                        'courseid' => $courseid,
                        'userid' => $USER->id
                    ]));
            }

            $html .= $this->render_link('✍️', 'La Mia Autovalutazione',
                new moodle_url('/local/selfassessment/compile.php'));

            $html .= '</ul></div>';
        }

        $html .= '</div>';

        $this->content->text = $html;
        return $this->content;
    }

    private function render_link($icon, $text, $url) {
        return '<li><a href="' . $url . '">' . $icon . '<span>' . $text . '</span></a></li>';
    }

    private function get_block_styles() {
        return '
        <style>
        .ftm-tools-block { font-size: 0.9rem; }
        .ftm-section { margin-bottom: 15px; }
        .ftm-section-title {
            font-weight: 600;
            color: #333;
            padding: 8px 0 5px;
            border-bottom: 2px solid #28a745;
            margin-bottom: 8px;
            font-size: 0.85rem;
        }
        .ftm-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .ftm-links li {
            margin: 4px 0;
        }
        .ftm-links a {
            display: flex;
            align-items: center;
            padding: 6px 8px;
            color: #333;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 0.85rem;
        }
        .ftm-links a:hover {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            color: #1b5e20;
            transform: translateX(3px);
        }
        .ftm-links a span {
            margin-left: 8px;
        }
        </style>';
    }
}
