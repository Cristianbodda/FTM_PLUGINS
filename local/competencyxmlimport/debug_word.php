<?php
/**
 * Debug script per analizzare file Word LOGISTICA
 * Uso: Aprire nel browser e selezionare il file
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/competencyxmlimport/debug_word.php'));
$PAGE->set_title('Debug Word Parser');

echo $OUTPUT->header();
echo '<h2>Debug Word Parser - LOGISTICA</h2>';

// Se è stato caricato un file
if (!empty($_FILES['wordfile']['tmp_name'])) {
    $filepath = $_FILES['wordfile']['tmp_name'];
    $filename = $_FILES['wordfile']['name'];

    echo "<h3>File: " . s($filename) . "</h3>";

    // Estrai XML
    $zip = new ZipArchive();
    if ($zip->open($filepath) === true) {
        $xml_content = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml_content) {
            // Estrai paragrafi
            $paragraphs = [];
            preg_match_all('/<w:p[^>]*>(.*?)<\/w:p>/s', $xml_content, $matches);

            foreach ($matches[1] as $p_content) {
                preg_match_all('/<w:br[^>]*\/?>|<w:t[^>]*>([^<]*)<\/w:t>/i', $p_content, $elem_matches, PREG_SET_ORDER);

                $text_parts = [];
                foreach ($elem_matches as $match) {
                    if (stripos($match[0], '<w:br') !== false) {
                        $text_parts[] = "\n";
                    } elseif (isset($match[1])) {
                        $text_parts[] = $match[1];
                    }
                }

                $paragraph_text = implode('', $text_parts);
                $lines = explode("\n", $paragraph_text);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $paragraphs[] = $line;
                    }
                }
            }

            $text = implode("\n", $paragraphs);

            // Mostra primi 100 paragrafi
            echo '<h4>Primi 100 paragrafi estratti:</h4>';
            echo '<pre style="background:#f5f5f5; padding:15px; max-height:400px; overflow:auto; font-size:12px;">';
            $lines = explode("\n", $text);
            foreach (array_slice($lines, 0, 100) as $i => $line) {
                $num = str_pad($i + 1, 3, ' ', STR_PAD_LEFT);
                // Evidenzia pattern importanti
                $display = htmlspecialchars($line);
                if (preg_match('/^\d+\.\s*[A-Z_]+_Q\d+$/i', $line)) {
                    $display = '<span style="background:#90EE90; font-weight:bold;">' . $display . '</span> [MATCH: numbered_log_q]';
                } elseif (preg_match('/^LOG_APPR\d+_Q\d+$/i', $line)) {
                    $display = '<span style="background:#87CEEB; font-weight:bold;">' . $display . '</span> [MATCH: log_appr_q]';
                } elseif (preg_match('/^Competenza:\s*LOGISTICA_/i', $line)) {
                    $display = '<span style="background:#FFB6C1; font-weight:bold;">' . $display . '</span> [MATCH: competenza_logistica]';
                } elseif (preg_match('/^[A-D][\)\.]/i', $line)) {
                    $display = '<span style="color:#666;">' . $display . '</span>';
                }
                echo "$num: $display\n";
            }
            echo '</pre>';

            // Test pattern detection
            echo '<h4>Test Pattern Detection:</h4>';
            echo '<ul>';

            // IMPORTANTE: [A-Z0-9_]+ include numeri per matchare LOG_APPR05_Q01
            $has_numbered_log_q = (bool) preg_match('/(?:^|\n)\d+\.\s*[A-Z0-9_]+_Q\d+\s*$/um', $text);
            echo '<li>has_numbered_log_q (1. LOG_XXX_Q01): <strong>' . ($has_numbered_log_q ? 'SI' : 'NO') . '</strong></li>';

            $has_log_appr_q = (bool) preg_match('/(?:^|\n)LOG_APPR\d+_Q\d+\s*$/um', $text);
            echo '<li>has_log_appr_q (LOG_APPR01_Q01): <strong>' . ($has_log_appr_q ? 'SI' : 'NO') . '</strong></li>';

            $has_log_appr_dash = (bool) preg_match('/(?:^|\n)LOG_APPR\d+_Q\d+\s*[–—-]\s*LOGISTICA_/um', $text);
            echo '<li>has_log_appr_dash (LOG_APPR04_Q01 – LOGISTICA_XX): <strong>' . ($has_log_appr_dash ? 'SI' : 'NO') . '</strong></li>';

            $has_q_dash_logistica = (bool) preg_match('/(?:^|\n)Q\d+\s*[–—-]\s*LOGISTICA_/um', $text);
            echo '<li>has_q_dash_logistica (Q1 – LOGISTICA_XX): <strong>' . ($has_q_dash_logistica ? 'SI' : 'NO') . '</strong></li>';

            $has_competenza_logistica = (bool) preg_match('/Competenza:\s*LOGISTICA_/ui', $text);
            echo '<li>has_competenza_logistica (Competenza: LOGISTICA_): <strong>' . ($has_competenza_logistica ? 'SI' : 'NO') . '</strong></li>';

            echo '</ul>';

            // Determina formato
            echo '<h4>Formato Rilevato:</h4>';
            if ($has_numbered_log_q && $has_competenza_logistica) {
                echo '<p style="color:green; font-weight:bold;">FORMAT_15_LOGISTICA</p>';
            } elseif ($has_log_appr_dash) {
                echo '<p style="color:green; font-weight:bold;">FORMAT_17_LOG_APPR_DASH</p>';
            } elseif ($has_q_dash_logistica) {
                echo '<p style="color:green; font-weight:bold;">FORMAT_18_LOG_Q_DASH</p>';
            } elseif ($has_log_appr_q && $has_competenza_logistica) {
                echo '<p style="color:green; font-weight:bold;">FORMAT_16_LOG_APPR</p>';
            } else {
                echo '<p style="color:red; font-weight:bold;">FORMATO NON RICONOSCIUTO</p>';
                echo '<p>Pattern mancanti per LOGISTICA. Verifica struttura file.</p>';
            }

            // Test split per FORMAT_15
            if ($has_numbered_log_q) {
                echo '<h4>Test Split FORMAT_15:</h4>';
                $parts = preg_split('/(?:^|\n)\d+\.\s*([A-Z0-9_]+_Q\d+)\s*$/um', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
                echo '<p>Numero parti: ' . count($parts) . '</p>';
                echo '<p>Domande attese: ' . floor((count($parts) - 1) / 2) . '</p>';

                if (count($parts) > 1) {
                    echo '<pre style="background:#f5f5f5; padding:10px; font-size:11px;">';
                    for ($i = 0; $i < min(10, count($parts)); $i++) {
                        $preview = substr($parts[$i], 0, 80);
                        echo "Part[$i]: " . htmlspecialchars($preview) . (strlen($parts[$i]) > 80 ? '...' : '') . "\n";
                    }
                    echo '</pre>';
                }
            }

            // Test split per FORMAT_16
            if ($has_log_appr_q) {
                echo '<h4>Test Split FORMAT_16:</h4>';
                $parts = preg_split('/(?:^|\n)(LOG_APPR\d+_Q\d+)\s*\n/um', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
                echo '<p>Numero parti: ' . count($parts) . '</p>';
                echo '<p>Domande attese: ' . floor((count($parts) - 1) / 2) . '</p>';

                if (count($parts) > 1) {
                    echo '<pre style="background:#f5f5f5; padding:10px; font-size:11px;">';
                    for ($i = 0; $i < min(10, count($parts)); $i++) {
                        $preview = substr($parts[$i], 0, 80);
                        echo "Part[$i]: " . htmlspecialchars($preview) . (strlen($parts[$i]) > 80 ? '...' : '') . "\n";
                    }
                    echo '</pre>';
                }
            }

        } else {
            echo '<p style="color:red;">Impossibile leggere document.xml dal file</p>';
        }
    } else {
        echo '<p style="color:red;">Impossibile aprire il file come ZIP</p>';
    }
}

// Form upload
echo '<hr>';
echo '<h3>Carica file Word da analizzare:</h3>';
echo '<form method="post" enctype="multipart/form-data">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="file" name="wordfile" accept=".docx" required>';
echo '<button type="submit" style="margin-left:10px; padding:8px 16px;">Analizza</button>';
echo '</form>';

echo $OUTPUT->footer();
