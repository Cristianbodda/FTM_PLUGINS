<?php
/**
 * FTM Design Helper
 *
 * Gestisce il caricamento del Design System FTM con toggle sicuro.
 *
 * @package    local_ftm_common
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ftm_common;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper per gestire il Design System FTM
 */
class design_helper {

    /** @var string Versione del design system */
    const VERSION = '1.0';

    /** @var string Default design mode */
    const DEFAULT_MODE = 'old';

    /**
     * Carica il design system se richiesto
     *
     * Uso:
     *   ?design=new  → Carica nuovo design
     *   ?design=old  → Design originale (default)
     *
     * @param \moodle_page $PAGE L'oggetto PAGE di Moodle
     * @param bool $force_new Forza il nuovo design ignorando il parametro URL
     * @return bool True se il nuovo design è stato caricato
     */
    public static function load_design($PAGE, $force_new = false) {
        $design_mode = optional_param('design', self::DEFAULT_MODE, PARAM_ALPHA);

        if ($force_new || $design_mode === 'new') {
            $PAGE->requires->css('/local/ftm_common/styles/ftm_design_system.css');
            return true;
        }

        return false;
    }

    /**
     * Verifica se il nuovo design è attivo
     *
     * @return bool True se il nuovo design è attivo
     */
    public static function is_new_design_active() {
        $design_mode = optional_param('design', self::DEFAULT_MODE, PARAM_ALPHA);
        return $design_mode === 'new';
    }

    /**
     * Genera il link per switchare tra design
     *
     * @param \moodle_url $current_url URL corrente
     * @return array ['new' => url_new, 'old' => url_old]
     */
    public static function get_toggle_urls($current_url) {
        $url_new = clone $current_url;
        $url_new->param('design', 'new');

        $url_old = clone $current_url;
        $url_old->param('design', 'old');

        return [
            'new' => $url_new,
            'old' => $url_old
        ];
    }

    /**
     * Genera HTML per il toggle button del design
     *
     * @param \moodle_url $current_url URL corrente
     * @return string HTML del toggle
     */
    public static function render_toggle_button($current_url) {
        $is_new = self::is_new_design_active();
        $urls = self::get_toggle_urls($current_url);

        $current_label = $is_new ? 'Nuovo Design' : 'Design Classico';
        $switch_url = $is_new ? $urls['old'] : $urls['new'];
        $switch_label = $is_new ? 'Torna al Classico' : 'Prova Nuovo Design';

        $html = '<div class="ftm-design-toggle" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999;">';
        $html .= '<div style="background: #343a40; color: white; padding: 10px 15px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); font-size: 13px;">';
        $html .= '<span style="opacity: 0.7; margin-right: 10px;">Design: <strong>' . $current_label . '</strong></span>';
        $html .= '<a href="' . $switch_url->out() . '" style="background: #f5a623; color: white; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-weight: 600;">' . $switch_label . '</a>';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Applica le classi del design system al container principale
     *
     * @param bool $include_page_bg Include la classe per lo sfondo pagina
     * @return string Classi CSS da aggiungere
     */
    public static function get_container_classes($include_page_bg = true) {
        $classes = ['ftm-container'];

        if ($include_page_bg && self::is_new_design_active()) {
            $classes[] = 'ftm-page-bg';
        }

        return implode(' ', $classes);
    }

    /**
     * Genera HTML per l'header FTM standard
     *
     * @param string $title Titolo principale
     * @param string $subtitle Sottotitolo opzionale
     * @return string HTML dell'header
     */
    public static function render_header($title, $subtitle = '') {
        if (!self::is_new_design_active()) {
            // Fallback per design classico
            return '<h2>' . s($title) . '</h2>' . ($subtitle ? '<p>' . s($subtitle) . '</p>' : '');
        }

        $html = '<div class="ftm-header">';
        $html .= '<h1 class="ftm-header-title">' . s($title) . '</h1>';
        if ($subtitle) {
            $html .= '<p class="ftm-header-subtitle">' . s($subtitle) . '</p>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Genera HTML per una stat card
     *
     * @param string $number Numero/valore principale
     * @param string $label Label descrittiva
     * @param string $icon Icona (emoji o HTML)
     * @param string $color Colore accent: yellow, green, blue, orange, red, teal
     * @return string HTML della card
     */
    public static function render_stat_card($number, $label, $icon = '', $color = 'blue') {
        if (!self::is_new_design_active()) {
            // Fallback per design classico
            return '<div style="display: inline-block; margin: 10px; padding: 15px; border: 1px solid #ddd; border-radius: 8px;">' .
                   '<strong style="font-size: 24px;">' . s($number) . '</strong><br>' .
                   '<small>' . s($label) . '</small></div>';
        }

        $html = '<div class="ftm-stat-card ' . s($color) . '">';
        if ($icon) {
            $html .= '<div class="ftm-stat-icon">' . $icon . '</div>';
        }
        $html .= '<div class="ftm-stat-content">';
        $html .= '<div class="ftm-stat-number">' . s($number) . '</div>';
        $html .= '<div class="ftm-stat-label">' . s($label) . '</div>';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Genera HTML per un badge
     *
     * @param string $text Testo del badge
     * @param string $type Tipo: success, warning, danger, info, primary, teal
     * @return string HTML del badge
     */
    public static function render_badge($text, $type = 'info') {
        if (!self::is_new_design_active()) {
            $colors = [
                'success' => '#28a745',
                'warning' => '#ffc107',
                'danger' => '#dc3545',
                'info' => '#17a2b8',
                'primary' => '#f5a623',
                'teal' => '#1a5a5a'
            ];
            $bg = $colors[$type] ?? '#6c757d';
            return '<span style="background: ' . $bg . '; color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px;">' . s($text) . '</span>';
        }

        return '<span class="ftm-badge ftm-badge-' . s($type) . '">' . s($text) . '</span>';
    }

    /**
     * Genera HTML per un alert
     *
     * @param string $message Messaggio
     * @param string $type Tipo: success, warning, danger, info
     * @return string HTML dell'alert
     */
    public static function render_alert($message, $type = 'info') {
        if (!self::is_new_design_active()) {
            $colors = [
                'success' => '#d4edda',
                'warning' => '#fff3cd',
                'danger' => '#f8d7da',
                'info' => '#d1ecf1'
            ];
            $bg = $colors[$type] ?? '#e9ecef';
            return '<div style="background: ' . $bg . '; padding: 15px; border-radius: 8px; margin-bottom: 15px;">' . $message . '</div>';
        }

        return '<div class="ftm-alert ftm-alert-' . s($type) . '">' . $message . '</div>';
    }

    /**
     * Genera HTML per progress bar
     *
     * @param float $percentage Percentuale (0-100)
     * @param string $label Label opzionale dentro la barra
     * @param string $type Tipo: success, warning, danger
     * @param bool $large Usa dimensione grande
     * @return string HTML della progress bar
     */
    public static function render_progress($percentage, $label = '', $type = 'success', $large = false) {
        $percentage = max(0, min(100, $percentage));
        $display_label = $label ?: round($percentage) . '%';

        if (!self::is_new_design_active()) {
            return '<div style="background: #e9ecef; border-radius: 20px; overflow: hidden; height: ' . ($large ? '40px' : '24px') . ';">' .
                   '<div style="background: #28a745; width: ' . $percentage . '%; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">' .
                   $display_label . '</div></div>';
        }

        $size_class = $large ? 'ftm-progress-lg' : '';
        return '<div class="ftm-progress ' . $size_class . '">' .
               '<div class="ftm-progress-bar ' . s($type) . '" style="width: ' . $percentage . '%;">' .
               $display_label . '</div></div>';
    }
}
