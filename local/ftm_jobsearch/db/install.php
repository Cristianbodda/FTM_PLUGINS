<?php
/**
 * FTM Job Search - Post-install: popola citta Canton Ticino.
 *
 * @package    local_ftm_jobsearch
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_ftm_jobsearch_install() {
    global $DB;

    $cities = [
        ['nome' => 'Lugano',      'lat' => 46.0037, 'lng' => 8.9511],
        ['nome' => 'Bellinzona',   'lat' => 46.1954, 'lng' => 9.0248],
        ['nome' => 'Locarno',     'lat' => 46.1678, 'lng' => 8.7973],
        ['nome' => 'Mendrisio',   'lat' => 45.8686, 'lng' => 8.9844],
        ['nome' => 'Chiasso',     'lat' => 45.8403, 'lng' => 9.0228],
        ['nome' => 'Biasca',      'lat' => 46.3566, 'lng' => 8.9699],
        ['nome' => 'Giubiasco',   'lat' => 46.1726, 'lng' => 9.0084],
        ['nome' => 'Muralto',     'lat' => 46.1718, 'lng' => 8.8043],
        ['nome' => 'Pregassona',  'lat' => 46.0220, 'lng' => 8.9778],
        ['nome' => 'Manno',       'lat' => 46.0508, 'lng' => 8.9178],
        ['nome' => 'Agno',        'lat' => 45.9940, 'lng' => 8.9022],
        ['nome' => 'Rivera',      'lat' => 46.0983, 'lng' => 8.9264],
        ['nome' => 'Cadenazzo',   'lat' => 46.1584, 'lng' => 8.9524],
        ['nome' => 'Tenero',      'lat' => 46.1758, 'lng' => 8.8394],
        ['nome' => 'Gordola',     'lat' => 46.1817, 'lng' => 8.8688],
        ['nome' => 'Losone',      'lat' => 46.1709, 'lng' => 8.7711],
        ['nome' => 'Minusio',     'lat' => 46.1778, 'lng' => 8.8188],
        ['nome' => 'Stabio',      'lat' => 45.8527, 'lng' => 8.9387],
        ['nome' => 'Balerna',     'lat' => 45.8519, 'lng' => 9.0066],
        ['nome' => 'Arbedo',      'lat' => 46.2139, 'lng' => 9.0355],
    ];

    foreach ($cities as $city) {
        if (!$DB->record_exists('local_ftm_jobsearch_cities', ['nome' => $city['nome']])) {
            $DB->insert_record('local_ftm_jobsearch_cities', (object)$city);
        }
    }
}
