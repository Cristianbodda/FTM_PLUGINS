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
 * Word document exporter for CPURC reports.
 *
 * Uses the official template and replaces merge fields with student data.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_cpurc;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/word_template_processor.php');

/**
 * Exports CPURC reports to Word format using the official template.
 */
class word_exporter {

    /** @var string Path to the template file */
    const TEMPLATE_PATH = '/local/ftm_cpurc/templates/rapporto_finale_template.docx';

    /** @var object Student data */
    private $student;

    /** @var object Report data */
    private $report;

    /** @var object User data (Moodle user) */
    private $user;

    /** @var object Coach data */
    private $coach;

    /**
     * Constructor.
     *
     * @param object $student Student record from local_ftm_cpurc_students.
     * @param object $report Report record from local_ftm_cpurc_reports (optional).
     */
    public function __construct($student, $report = null) {
        global $DB, $USER;

        $this->student = $student;
        $this->report = $report ?: new \stdClass();

        // Get Moodle user data.
        $this->user = $DB->get_record('user', ['id' => $student->userid]);

        // Get coach data.
        if (!empty($report->coachid)) {
            $this->coach = $DB->get_record('user', ['id' => $report->coachid]);
        } else {
            $this->coach = $USER;
        }
    }

    /**
     * Generate and download the Word document.
     */
    public function download() {
        global $CFG;

        $templatepath = $CFG->dirroot . self::TEMPLATE_PATH;

        if (!file_exists($templatepath)) {
            throw new \moodle_exception('templatenotfound', 'local_ftm_cpurc', '', $templatepath);
        }

        $processor = new word_template_processor($templatepath);

        // Set all merge field values.
        $processor->setValues($this->getMergeFieldValues());

        // Set checkbox states.
        $this->setCheckboxStates($processor);

        // Set narrative text replacements (report data into template hint texts).
        $this->setTextReplacements($processor);

        // Generate filename.
        $filename = $this->generateFilename();

        // Download.
        $processor->download($filename);
    }

    /**
     * Generate the filename for download.
     *
     * @return string Filename.
     */
    private function generateFilename() {
        $name = $this->student->lastname . '_' . $this->student->firstname;
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        return 'Rapporto_finale_' . $name . '_' . date('Y-m-d') . '.docx';
    }

    /**
     * Get all merge field values mapped from database.
     *
     * @return array Associative array of field => value.
     */
    private function getMergeFieldValues() {
        $values = [];

        // === ORGANIZZATORE ===
        $values['F20'] = $this->getCoachInitials();

        // === PARTECIPANTE - DATI ANAGRAFICI BASE ===
        // Template has «F3», «DATI_ANAGRAFICI_base» in same cell.
        // F3 = lastname, DATI_ANAGRAFICI_base = firstname (produces "Lastname, Firstname").
        $lastname = $this->user ? $this->user->lastname : ($this->student->lastname ?? '');
        $firstname = $this->user ? $this->user->firstname : ($this->student->firstname ?? '');
        $values['F3'] = $lastname;
        $values['DATI_ANAGRAFICI_base'] = $firstname;
        $values['F6'] = $this->student->address_street ?? '';
        $values['F7'] = $this->student->address_cap ?? '';
        $values['F8'] = $this->student->address_city ?? '';
        $values['F11'] = $this->student->avs_number ?? '';

        // === DATI ANAGRAFICI ALTRI ===
        $values['DATI_ANAGRAFICI_altri'] = $this->formatDate($this->student->birthdate);

        // === DATI PERCORSO ===
        $values['F22'] = $this->formatDate($this->student->date_start);
        $values['F23'] = $this->formatDate($this->student->date_end_planned);
        $values['F24'] = $this->formatDate($this->student->date_end_actual);
        $values['F25'] = ($this->student->occupation_grade ?? '100');
        $values['F26'] = $this->student->urc_office ?? '';
        $values['F27'] = $this->student->urc_consultant ?? '';

        // Giorni partecipazione effettiva.
        $values['F32'] = $this->calculateEffectiveDays();
        $values['F33'] = ''; // Reserved.

        // === ASSENZE ===
        $values['F34'] = $this->student->absence_a ?? '0';  // Vacanze.
        $values['F35'] = $this->student->absence_b ?? '0';  // Gravidanza.
        $values['F36'] = $this->student->absence_c ?? '0';  // Infortunio.
        $values['F37'] = $this->student->absence_d ?? '0';  // Congedo maternità.
        $values['F38'] = $this->student->absence_e ?? '0';  // Protezione civile.
        $values['F39'] = $this->student->absence_f ?? '0';  // F.
        $values['F40'] = $this->student->absence_g ?? '0';  // Altre giustificate.
        $values['F41'] = $this->student->absence_h ?? '0';  // Festivi.
        $values['F42'] = $this->student->absence_i ?? '0';  // I.
        $values['F43'] = $this->student->absence_total ?? '0';  // Totale.

        // === COLLOQUI ===
        $values['F44'] = $this->student->interviews ?? '0';
        $values['F74'] = $this->getInterviewsDetail();
        $values['COLLOQUI_DASSUNZIONE'] = $this->getInterviewsDetail();

        // === ESITO ===
        $values['DATI_PERCORSO_altri'] = $this->student->last_profession ?? '';
        $values['F29'] = $this->student->last_profession ?? '';
        $values['F30'] = $this->report->hired_company ?? '';

        // === SEZIONI NARRATIVE ===
        // Situazione iniziale.
        $values['SITUAZIONE_INIZIALE'] = $this->report->initial_situation ?? '';

        // Valutazione competenze settore.
        $values['VALUTAZIONE_SETTORE'] = $this->report->sector_competency_text ?? '';
        $values['POSSIBILI_SETTORI'] = $this->report->possible_sectors ?? '';
        $values['SINTESI_CONCLUSIVA'] = $this->report->final_summary ?? '';

        // Osservazioni competenze trasversali.
        $values['OSS_PERSONALI'] = $this->report->obs_personal ?? '';
        $values['OSS_SOCIALI'] = $this->report->obs_social ?? '';
        $values['OSS_METODOLOGICHE'] = $this->report->obs_methodological ?? '';

        // Osservazioni ricerca impiego.
        $values['OSS_CANALI_RICERCA'] = $this->report->obs_search_channels ?? '';
        $values['OSS_VALUTAZIONE_RICERCA'] = $this->report->obs_search_evaluation ?? '';

        // Osservazioni TIC.
        $values['OSS_TIC'] = $this->report->obs_tic ?? '';

        // Colloqui (update F44 with report data if available).
        $interviews_count = $this->report->interviews_count ?? ($this->student->interviews ?? 0);
        $values['F44'] = (string) $interviews_count;

        // === COMPETENCY RATING GRIDS (Section 3 + 4) ===
        // Each cell in the template has «XX_YY» where XX=row code, YY=scale code.
        // Replace with "X" if selected, empty string if not.
        $scaleKeys = ['molto_buone' => 'MB', 'buone' => 'B', 'sufficienti' => 'S', 'insufficienti' => 'I', 'nv' => 'NV'];

        // 3.1 Personal (P0-P4), 3.2 Social (S0-S1), 3.3 Methodological (M0-M4), 3.4 TIC (T0).
        $competencyMap = [
            'personal_competencies' => ['prefix' => 'P', 'count' => 5],
            'social_competencies' => ['prefix' => 'S', 'count' => 2],
            'methodological_competencies' => ['prefix' => 'M', 'count' => 5],
            'tic_competencies' => ['prefix' => 'T', 'count' => 1],
        ];

        foreach ($competencyMap as $field => $config) {
            $saved = [];
            if (!empty($this->report->$field)) {
                $saved = json_decode($this->report->$field, true) ?: [];
            }
            for ($i = 0; $i < $config['count']; $i++) {
                $selectedValue = $saved[$i] ?? '';
                foreach ($scaleKeys as $scaleValue => $scaleCode) {
                    $fieldKey = $config['prefix'] . $i . '_' . $scaleCode;
                    $values[$fieldKey] = ($selectedValue === $scaleValue) ? 'X' : '';
                }
            }
        }

        // 4. Search competencies (R0-R4).
        $savedSearch = [];
        if (!empty($this->report->search_competencies)) {
            $savedSearch = json_decode($this->report->search_competencies, true) ?: [];
        }
        for ($i = 0; $i < 5; $i++) {
            $selectedValue = $savedSearch[$i] ?? '';
            foreach ($scaleKeys as $scaleValue => $scaleCode) {
                $fieldKey = 'R' . $i . '_' . $scaleCode;
                $values[$fieldKey] = ($selectedValue === $scaleValue) ? 'X' : '';
            }
        }

        // 4.3 Overall search assessment (VO).
        $searchOverall = $this->report->search_overall ?? '';
        foreach ($scaleKeys as $scaleValue => $scaleCode) {
            $values['VO_' . $scaleCode] = ($searchOverall === $scaleValue) ? 'X' : '';
        }

        // Search channels (comma-separated list for template).
        if (!empty($this->report->search_channels)) {
            $channels = json_decode($this->report->search_channels, true) ?: [];
            $values['CANALI_RICERCA'] = implode(', ', $channels);
        }

        return $values;
    }

    /**
     * Set checkbox states in the processor.
     *
     * @param word_template_processor $processor The processor instance.
     */
    private function setCheckboxStates($processor) {
        // Dossier completo.
        $processor->setCheckbox('dossier_complete_si', !empty($this->report->dossier_complete));
        $processor->setCheckbox('dossier_complete_no', empty($this->report->dossier_complete));

        // Assunzione.
        $hired = !empty($this->report->hired);
        $processor->setCheckbox('hired_si', $hired);
        $processor->setCheckbox('hired_no', !$hired);

        // Competency ratings are handled via 'x' markers in tables.
    }

    /**
     * Set text replacements for narrative report content.
     * Replaces hint/placeholder texts in the template with actual report data.
     *
     * @param word_template_processor $processor The processor instance.
     */
    private function setTextReplacements($processor) {
        // Replace hint/placeholder texts that are NOT merge fields.
        // The narrative merge fields (SITUAZIONE_INIZIALE, VALUTAZIONE_SETTORE, etc.)
        // are already handled by getMergeFieldValues() via «FIELD» replacement.
        //
        // Here we only replace static hint texts in the template that should be
        // overwritten with actual data or cleared.

        // Allegati hint text.
        $allegati = $this->report->allegati ?? '';
        if (!empty($allegati)) {
            $processor->replaceText('indicare quali allegati', $allegati);
        }

        // Hint texts in Section 1 (these are STATIC text, not merge fields).
        // They appear alongside the merge fields and should be removed when data is present.
        if (!empty($this->report->initial_situation)) {
            $processor->replaceText(
                'Sintesi situazione iniziale e obiettivi, inserire una storia della carriera professionale e formativa della PCI.',
                ''
            );
        }

        if (!empty($this->report->initial_situation_sector)) {
            $processor->replaceText(
                'Es.: Generico, meccanica, automazione, logistica, elettrico ecc',
                ''
            );
        }

        // Hint text in Section 2.
        if (!empty($this->report->sector_competency_text)) {
            $processor->replaceText(
                '(sulla base dei documenti redatti durante il percorso indicare quali competenze tecniche sono state rilevate',
                ''
            );
            $processor->replaceText(
                ', inserire qui anche se sono stati fatti anche stage che hanno permesso di rilevare/confermare competenze pratiche)',
                ''
            );
        }
    }

    /**
     * Get coach initials (e.g., "CB" for Cristian Bodda).
     *
     * @return string Coach initials.
     */
    private function getCoachInitials() {
        if (!$this->coach) {
            return '';
        }

        $first = mb_substr($this->coach->firstname, 0, 1, 'UTF-8');
        $last = mb_substr($this->coach->lastname, 0, 1, 'UTF-8');

        return mb_strtoupper($first . $last, 'UTF-8');
    }

    /**
     * Get full name from Moodle user.
     *
     * @return string Full name.
     */
    private function getFullName() {
        if ($this->user) {
            return trim($this->user->lastname . ', ' . $this->user->firstname);
        }
        return trim(($this->student->lastname ?? '') . ', ' . ($this->student->firstname ?? ''));
    }

    /**
     * Format a timestamp as date.
     *
     * @param int $timestamp Unix timestamp.
     * @return string Formatted date (dd.mm.yyyy).
     */
    private function formatDate($timestamp) {
        if (empty($timestamp)) {
            return '';
        }
        return date('d.m.Y', $timestamp);
    }

    /**
     * Calculate effective participation days.
     *
     * @return string Number of days.
     */
    private function calculateEffectiveDays() {
        $start = $this->student->date_start ?? 0;
        $end = $this->student->date_end_actual ?? $this->student->date_end_planned ?? time();

        if (empty($start)) {
            return '30'; // Default.
        }

        // Calculate working days (excluding weekends).
        $days = 0;
        $current = $start;

        while ($current <= $end) {
            $dayOfWeek = date('N', $current);
            if ($dayOfWeek < 6) { // Mon-Fri.
                $days++;
            }
            $current += 86400; // +1 day.
        }

        // Subtract absences.
        $absences = $this->student->absence_total ?? 0;
        $effective = max(0, $days - $absences);

        return (string) $effective;
    }

    /**
     * Get interviews detail text.
     *
     * @return string Interviews detail.
     */
    private function getInterviewsDetail() {
        if (!empty($this->report->interviews_json)) {
            $interviews = json_decode($this->report->interviews_json, true);
            if (is_array($interviews) && !empty($interviews)) {
                $details = [];
                foreach ($interviews as $interview) {
                    $company = $interview['company'] ?? '';
                    $date = $interview['date'] ?? '';
                    if ($company) {
                        $details[] = $company . ($date ? ' (' . $date . ')' : '');
                    }
                }
                return implode(', ', $details);
            }
        }
        return '';
    }

    /**
     * Get rating text from numeric value.
     *
     * @param int $rating Rating 1-5.
     * @return string Rating label.
     */
    private function getRatingText($rating) {
        $labels = [
            1 => 'Insufficienti',
            2 => 'Sufficienti',
            3 => 'Buone',
            4 => 'Molto buone',
            5 => 'N.V.',
        ];
        return $labels[$rating] ?? '';
    }

    /**
     * Save generated document to file.
     *
     * @param string $filepath Path to save the file.
     * @return bool Success status.
     */
    public function saveToFile($filepath) {
        global $CFG;

        $templatepath = $CFG->dirroot . self::TEMPLATE_PATH;

        if (!file_exists($templatepath)) {
            throw new \moodle_exception('templatenotfound', 'local_ftm_cpurc', '', $templatepath);
        }

        $processor = new word_template_processor($templatepath);
        $processor->setValues($this->getMergeFieldValues());
        $this->setCheckboxStates($processor);
        $this->setTextReplacements($processor);

        return $processor->process($filepath);
    }
}
