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
 * Word template processor using native PHP ZipArchive.
 *
 * Processes DOCX templates by replacing merge fields with actual data.
 * No external dependencies required.
 *
 * @package    local_ftm_cpurc
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_cpurc;

defined('MOODLE_INTERNAL') || die();

/**
 * Processes Word templates and replaces merge fields.
 */
class word_template_processor {

    /** @var string Path to the template file */
    private $templatepath;

    /** @var array Replacement values */
    private $values = [];

    /** @var array Checkbox replacements */
    private $checkboxes = [];

    /**
     * Constructor.
     *
     * @param string $templatepath Path to the DOCX template.
     */
    public function __construct($templatepath) {
        if (!file_exists($templatepath)) {
            throw new \moodle_exception('templatenotfound', 'local_ftm_cpurc', '', $templatepath);
        }

        $this->templatepath = $templatepath;
    }

    /**
     * Set a value for a merge field.
     *
     * @param string $field Field name (e.g., 'F3', 'F20').
     * @param string $value Replacement value.
     * @return self
     */
    public function setValue($field, $value) {
        $this->values[$field] = $value;
        return $this;
    }

    /**
     * Set multiple values at once.
     *
     * @param array $values Associative array of field => value.
     * @return self
     */
    public function setValues($values) {
        foreach ($values as $field => $value) {
            $this->setValue($field, $value);
        }
        return $this;
    }

    /**
     * Set checkbox state.
     *
     * @param string $field Field identifier.
     * @param bool $checked Whether checked or not.
     * @return self
     */
    public function setCheckbox($field, $checked) {
        $this->checkboxes[$field] = $checked;
        return $this;
    }

    /**
     * Process the template and generate output file.
     *
     * @param string $outputpath Path for the output file.
     * @return bool Success status.
     */
    public function process($outputpath) {
        // Copy template to output first.
        if (!copy($this->templatepath, $outputpath)) {
            throw new \moodle_exception('cannotcreateoutput', 'local_ftm_cpurc');
        }

        // Open the copied file for modification.
        $zip = new \ZipArchive();
        if ($zip->open($outputpath) !== true) {
            @unlink($outputpath);
            throw new \moodle_exception('cannotopentempate', 'local_ftm_cpurc');
        }

        // Process document.xml
        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml !== false) {
            $documentXml = $this->processXmlContent($documentXml);
            $zip->deleteName('word/document.xml');
            $zip->addFromString('word/document.xml', $documentXml);
        }

        // Process header files.
        for ($i = 1; $i <= 3; $i++) {
            $headerXml = $zip->getFromName("word/header{$i}.xml");
            if ($headerXml !== false) {
                $headerXml = $this->processXmlContent($headerXml);
                $zip->deleteName("word/header{$i}.xml");
                $zip->addFromString("word/header{$i}.xml", $headerXml);
            }
        }

        // Process footer files.
        for ($i = 1; $i <= 3; $i++) {
            $footerXml = $zip->getFromName("word/footer{$i}.xml");
            if ($footerXml !== false) {
                $footerXml = $this->processXmlContent($footerXml);
                $zip->deleteName("word/footer{$i}.xml");
                $zip->addFromString("word/footer{$i}.xml", $footerXml);
            }
        }

        $zip->close();

        return true;
    }

    /**
     * Generate and send for download.
     *
     * @param string $filename Download filename.
     */
    public function download($filename) {
        global $CFG;

        $outputpath = $CFG->tempdir . '/' . uniqid('cpurc_') . '.docx';

        $this->process($outputpath);

        // Send headers.
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($outputpath));
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        readfile($outputpath);

        // Cleanup output file.
        @unlink($outputpath);

        die();
    }

    /**
     * Process XML content - replace merge fields.
     *
     * @param string $content XML content.
     * @return string Processed content.
     */
    private function processXmlContent($content) {
        // First, clean up split merge fields in Word XML.
        // Word often splits text across multiple <w:t> tags.
        $content = $this->cleanupMergeFields($content);

        // Replace merge fields.
        $content = $this->replaceMergeFields($content);

        // Replace checkboxes.
        $content = $this->replaceCheckboxes($content);

        return $content;
    }

    /**
     * Clean up merge fields that are split across XML tags.
     *
     * Word often creates XML like:
     * <w:t>«</w:t></w:r><w:r><w:t>F3</w:t></w:r><w:r><w:t>»</w:t>
     *
     * This method joins them back together.
     *
     * @param string $content XML content.
     * @return string Cleaned content.
     */
    private function cleanupMergeFields($content) {
        // Pattern to find split merge fields and join them.
        // This handles cases where « and » are in separate <w:t> tags.

        // First pass: join adjacent w:t elements within the same w:r.
        $pattern = '/<w:t([^>]*)>([^<]*)<\/w:t><\/w:r><w:r[^>]*><w:t([^>]*)>([^<]*)<\/w:t>/';

        $maxIterations = 50;
        $iteration = 0;

        while ($iteration < $maxIterations) {
            $newContent = preg_replace_callback($pattern, function($matches) {
                $text1 = $matches[2];
                $text2 = $matches[4];

                // Check if this looks like a split merge field.
                if ((strpos($text1, '«') !== false && strpos($text2, '»') !== false) ||
                    (preg_match('/«[A-Z0-9_]*$/', $text1) && preg_match('/^[A-Z0-9_]*»/', $text2)) ||
                    (strpos($text1, '«') !== false) ||
                    (strpos($text2, '»') !== false)) {
                    // Join them.
                    return '<w:t' . $matches[1] . '>' . $text1 . $text2 . '</w:t>';
                }

                // Not a merge field, keep original.
                return $matches[0];
            }, $content);

            if ($newContent === $content) {
                break;
            }
            $content = $newContent;
            $iteration++;
        }

        return $content;
    }

    /**
     * Replace merge fields in content.
     *
     * @param string $content XML content.
     * @return string Processed content.
     */
    private function replaceMergeFields($content) {
        foreach ($this->values as $field => $value) {
            // Escape special XML characters.
            $escapedValue = $this->escapeXml($value);

            // Pattern 1: Simple merge field «FIELD»
            $content = str_replace('«' . $field . '»', $escapedValue, $content);

            // Pattern 2: With different quote styles.
            $content = str_replace('&laquo;' . $field . '&raquo;', $escapedValue, $content);

            // Pattern 3: Field that might have extra spaces.
            $content = str_replace('« ' . $field . ' »', $escapedValue, $content);
        }

        return $content;
    }

    /**
     * Escape special XML characters.
     *
     * @param string $value Value to escape.
     * @return string Escaped value.
     */
    private function escapeXml($value) {
        if ($value === null) {
            return '';
        }

        // Standard XML escaping.
        $value = str_replace('&', '&amp;', $value);
        $value = str_replace('<', '&lt;', $value);
        $value = str_replace('>', '&gt;', $value);
        $value = str_replace('"', '&quot;', $value);
        $value = str_replace("'", '&apos;', $value);

        return $value;
    }

    /**
     * Replace checkbox symbols.
     *
     * @param string $content XML content.
     * @return string Processed content.
     */
    private function replaceCheckboxes($content) {
        foreach ($this->checkboxes as $field => $checked) {
            if ($checked) {
                // Find unchecked near the field and make it checked.
                $pattern = '/(' . preg_quote($field, '/') . '.*?)☐/s';
                $content = preg_replace($pattern, '$1☒', $content, 1);
            } else {
                // Find checked near the field and make it unchecked.
                $pattern = '/(' . preg_quote($field, '/') . '.*?)☒/s';
                $content = preg_replace($pattern, '$1☐', $content, 1);
            }
        }

        return $content;
    }
}
