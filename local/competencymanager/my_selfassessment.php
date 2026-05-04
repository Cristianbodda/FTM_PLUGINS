<?php
/**
 * DEPRECATO: redirect al nuovo sistema di autovalutazione.
 * Questa pagina era il vecchio sistema; dal 14/04/2026 è stata rimpiazzata
 * da local/selfassessment/compile.php. Manteniamo il file come redirect
 * per non rompere i link esistenti (risorse URL dentro i corsi, ecc.).
 *
 * @package    local_competencymanager
 */

require_once(__DIR__ . '/../../config.php');

require_login();

redirect(new moodle_url('/local/selfassessment/compile.php'));
