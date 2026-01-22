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
 * Lettore Excel (.xlsx) senza dipendenze esterne
 * 
 * Un file .xlsx è uno ZIP contenente file XML.
 * Questa classe legge i dati senza usare PhpSpreadsheet.
 *
 * @package    local_competencyxmlimport
 * @copyright  2025 FTM - Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_competencyxmlimport;

defined('MOODLE_INTERNAL') || die();

/**
 * Classe per leggere file Excel .xlsx
 */
class excel_reader {

    /** @var string Namespace principale Excel */
    private $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /** @var string Percorso al file */
    private $filepath = '';

    /** @var array Nomi dei fogli */
    private $sheet_names = [];

    /** @var array Shared strings (testi condivisi) */
    private $shared_strings = [];

    /** @var ZipArchive Archivio ZIP */
    private $zip = null;

    /** @var array Cache dei dati dei fogli */
    private $sheets_data = [];
    
    /**
     * Costruttore
     * 
     * @param string $filepath Percorso al file .xlsx
     */
    public function __construct($filepath = '') {
        if ($filepath) {
            $this->load($filepath);
        }
    }
    
    /**
     * Carica un file Excel
     * 
     * @param string $filepath Percorso al file .xlsx
     * @return bool Successo
     */
    public function load($filepath) {
        $this->filepath = $filepath;
        $this->sheet_names = [];
        $this->shared_strings = [];
        $this->sheets_data = [];
        
        if (!file_exists($filepath)) {
            return false;
        }
        
        $this->zip = new \ZipArchive();
        if ($this->zip->open($filepath) !== true) {
            return false;
        }
        
        // Carica shared strings
        $this->load_shared_strings();
        
        // Carica nomi fogli
        $this->load_sheet_names();
        
        return true;
    }
    
    /**
     * Carica le stringhe condivise
     */
    private function load_shared_strings() {
        $content = $this->zip->getFromName('xl/sharedStrings.xml');
        if ($content === false) {
            return;
        }
        
        $xml = @simplexml_load_string($content);
        if ($xml === false) {
            return;
        }
        
        $xml->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        
        foreach ($xml->si as $si) {
            // Il testo può essere in <t> direttamente o in <r><t>
            $text = '';
            if (isset($si->t)) {
                $text = (string)$si->t;
            } elseif (isset($si->r)) {
                foreach ($si->r as $r) {
                    if (isset($r->t)) {
                        $text .= (string)$r->t;
                    }
                }
            }
            $this->shared_strings[] = $text;
        }
    }
    
    /**
     * Carica i nomi dei fogli
     */
    private function load_sheet_names() {
        $content = $this->zip->getFromName('xl/workbook.xml');
        if ($content === false) {
            return;
        }

        $xml = @simplexml_load_string($content);
        if ($xml === false) {
            return;
        }

        $xml->registerXPathNamespace('s', $this->ns);

        // Prova prima con xpath
        $sheets = $xml->xpath('//s:sheet');

        // Se xpath non funziona, prova accesso diretto con namespace
        if (empty($sheets)) {
            $children = $xml->children($this->ns);
            if (isset($children->sheets)) {
                $sheetsNode = $children->sheets->children($this->ns);
                foreach ($sheetsNode as $sheet) {
                    $attrs = $sheet->attributes();
                    $this->sheet_names[] = (string)$attrs['name'];
                }
                return;
            }
        }

        foreach ($sheets as $sheet) {
            $attrs = $sheet->attributes();
            $this->sheet_names[] = (string)$attrs['name'];
        }
    }
    
    /**
     * Ottiene i nomi dei fogli
     * 
     * @return array
     */
    public function get_sheet_names() {
        return $this->sheet_names;
    }
    
    /**
     * Ottiene informazioni sui fogli (nome e numero righe)
     * 
     * @return array
     */
    public function get_sheets_info() {
        $info = [];
        foreach ($this->sheet_names as $index => $name) {
            $data = $this->get_sheet_data($index);
            $info[] = [
                'index' => $index,
                'name' => $name,
                'rows' => count($data),
                'columns' => !empty($data) ? count($data[0]) : 0
            ];
        }
        return $info;
    }
    
    /**
     * Legge i dati di un foglio
     *
     * @param int|string $sheet Indice (0-based) o nome del foglio
     * @return array Array 2D con i dati
     */
    public function get_sheet_data($sheet) {
        // Determina indice
        if (is_string($sheet)) {
            $index = array_search($sheet, $this->sheet_names);
            if ($index === false) {
                return [];
            }
        } else {
            $index = (int)$sheet;
        }

        // Cache
        if (isset($this->sheets_data[$index])) {
            return $this->sheets_data[$index];
        }

        // Leggi foglio
        $sheet_file = 'xl/worksheets/sheet' . ($index + 1) . '.xml';
        $content = $this->zip->getFromName($sheet_file);
        if ($content === false) {
            return [];
        }

        $xml = @simplexml_load_string($content);
        if ($xml === false) {
            return [];
        }

        $xml->registerXPathNamespace('s', $this->ns);

        $data = [];

        // Gestisci namespace - prova prima con namespace, poi senza
        $sheetData = $xml->sheetData;
        if (!$sheetData || count($sheetData->row) == 0) {
            // Prova con namespace esplicito
            $children = $xml->children($this->ns);
            if (isset($children->sheetData)) {
                $sheetData = $children->sheetData;
            }
        }

        if (!$sheetData) {
            return [];
        }

        $rows = $sheetData->row;
        if (count($rows) == 0) {
            $rows = $sheetData->children($this->ns)->row ?? [];
        }

        foreach ($rows as $row) {
            $row_data = [];
            $row_attrs = $row->attributes();
            $row_num = (int)$row_attrs['r'];

            // Ottieni celle - prova con e senza namespace
            $cells = $row->c;
            if (count($cells) == 0) {
                $cells = $row->children($this->ns)->c ?? [];
            }

            foreach ($cells as $cell) {
                $cell_attrs = $cell->attributes();
                $cell_ref = (string)$cell_attrs['r'];
                $col_index = $this->column_to_index($cell_ref);

                // Riempi colonne vuote
                while (count($row_data) < $col_index) {
                    $row_data[] = '';
                }

                // Leggi valore
                $value = $this->get_cell_value($cell);
                $row_data[] = $value;
            }

            $data[] = $row_data;
        }

        $this->sheets_data[$index] = $data;
        return $data;
    }
    
    /**
     * Ottiene il valore di una cella
     *
     * @param SimpleXMLElement $cell
     * @return mixed
     */
    private function get_cell_value($cell) {
        $attrs = $cell->attributes();
        $type = isset($attrs['t']) ? (string)$attrs['t'] : '';

        // Ottieni il valore <v> - prova con e senza namespace
        $value = '';
        if (isset($cell->v)) {
            $value = (string)$cell->v;
        } else {
            $children = $cell->children($this->ns);
            if (isset($children->v)) {
                $value = (string)$children->v;
            }
        }

        // Tipo stringa condivisa
        if ($type === 's') {
            $index = (int)$value;
            return isset($this->shared_strings[$index]) ? $this->shared_strings[$index] : '';
        }

        // Tipo stringa inline - gestisci namespace
        if ($type === 'inlineStr') {
            // Prova prima senza namespace
            if (isset($cell->is->t)) {
                return (string)$cell->is->t;
            }
            // Prova con namespace esplicito
            $children = $cell->children($this->ns);
            if (isset($children->is)) {
                $isChildren = $children->is->children($this->ns);
                if (isset($isChildren->t)) {
                    return (string)$isChildren->t;
                }
            }
            return '';
        }

        // Tipo booleano
        if ($type === 'b') {
            return $value === '1';
        }

        // Numero o altro
        return $value;
    }
    
    /**
     * Converte riferimento colonna (A, B, AA, etc) in indice numerico
     * 
     * @param string $cell_ref Riferimento cella (es. "A1", "BC23")
     * @return int Indice colonna (0-based)
     */
    private function column_to_index($cell_ref) {
        // Estrai solo le lettere
        preg_match('/^([A-Z]+)/', $cell_ref, $matches);
        $col_letters = $matches[1] ?? 'A';
        
        $index = 0;
        $len = strlen($col_letters);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($col_letters[$i]) - ord('A') + 1);
        }
        return $index - 1; // 0-based
    }
    
    /**
     * Ottiene gli header (prima riga) di un foglio
     * 
     * @param int|string $sheet Indice o nome del foglio
     * @return array
     */
    public function get_headers($sheet) {
        $data = $this->get_sheet_data($sheet);
        return !empty($data) ? $data[0] : [];
    }
    
    /**
     * Ottiene i dati come array associativo (header => valore)
     * 
     * @param int|string $sheet Indice o nome del foglio
     * @return array
     */
    public function get_sheet_as_assoc($sheet) {
        $data = $this->get_sheet_data($sheet);
        if (count($data) < 2) {
            return [];
        }
        
        $headers = $data[0];
        $result = [];
        
        for ($i = 1; $i < count($data); $i++) {
            $row = [];
            foreach ($headers as $col_index => $header) {
                if (!empty($header)) {
                    $row[$header] = isset($data[$i][$col_index]) ? $data[$i][$col_index] : '';
                }
            }
            $result[] = $row;
        }
        
        return $result;
    }
    
    /**
     * Cerca automaticamente le colonne per la verifica
     *
     * @param int|string $sheet Indice o nome del foglio
     * @param string $word_prefix Prefisso competenze dal Word (es. "CHIMFARM", "MECCANICA")
     * @return array ['question_col' => X, 'competency_col' => Y, 'answer_col' => Z]
     */
    public function auto_detect_columns($sheet, $word_prefix = '') {
        $headers = $this->get_headers($sheet);

        $result = [
            'question_col' => null,
            'competency_col' => null,
            'answer_col' => null,
            'headers' => $headers,
            'all_competency_cols' => [] // Lista tutte le colonne competenza trovate
        ];

        // Prima passa: trova tutte le colonne competenza candidate
        $competency_candidates = [];

        foreach ($headers as $index => $header) {
            if (empty($header)) continue;
            $header_lower = strtolower($header);
            $header_upper = strtoupper($header);

            // Colonna domanda
            if ($result['question_col'] === null) {
                if ($header === 'Q' || $header_lower === 'domanda' ||
                    preg_match('/^q\d*$/i', $header) || $header_lower === 'question') {
                    $result['question_col'] = $index;
                }
            }

            // Colonne competenza - raccogli tutte le candidate
            if (strpos($header_lower, 'competenz') !== false ||
                strpos($header_lower, 'codice') !== false ||
                (strpos($header, '_') !== false && preg_match('/[A-Z]{3,}/', $header))) {

                $competency_candidates[] = [
                    'index' => $index,
                    'header' => $header,
                    'header_upper' => $header_upper
                ];
                $result['all_competency_cols'][] = ['index' => $index, 'name' => $header];
            }

            // Colonna risposta
            if ($result['answer_col'] === null) {
                if (strpos($header_lower, 'risposta') !== false ||
                    strpos($header_lower, 'answer') !== false ||
                    strpos($header_lower, 'correct') !== false) {
                    $result['answer_col'] = $index;
                }
            }
        }

        // Scegli la colonna competenza migliore
        if (!empty($competency_candidates)) {
            $best_candidate = null;

            // Se abbiamo un prefisso dal Word, cerca colonna che corrisponde
            if (!empty($word_prefix)) {
                $prefix_upper = strtoupper($word_prefix);
                foreach ($competency_candidates as $candidate) {
                    // Match esatto del prefisso nel nome colonna
                    if (strpos($candidate['header_upper'], $prefix_upper) !== false) {
                        $best_candidate = $candidate['index'];
                        break;
                    }
                }
            }

            // Fallback: prendi la prima colonna competenza trovata
            if ($best_candidate === null) {
                $best_candidate = $competency_candidates[0]['index'];
            }

            $result['competency_col'] = $best_candidate;
        }

        return $result;
    }
    
    /**
     * Chiude il file
     */
    public function close() {
        if ($this->zip) {
            $this->zip->close();
            $this->zip = null;
        }
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }
}
