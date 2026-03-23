<?php
/**
 * Student Report PDF Generator
 * Replica completa della stampa personalizzata via TCPDF
 *
 * Questo file viene incluso da student_report.php quando output=pdf.
 * Tutte le variabili calcolate in student_report.php sono disponibili.
 * Segue lo stesso ordine sezioni ($sectionOrder) del print template.
 *
 * @package    local_competencymanager
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/pdflib.php');
require_once(__DIR__ . '/gap_comments_mapping.php');

// ============================================
// FILTRO SETTORE (identico a student_report_print.php)
// ============================================
if (!empty($printSectorFilter) && $printSectorFilter !== 'all') {
    $filterSector = strtoupper($printSectorFilter);

    $competencies = array_filter($competencies, function($comp) use ($filterSector) {
        $idnumber = $comp['idnumber'] ?? '';
        $parts = explode('_', $idnumber);
        return strtoupper($parts[0] ?? '') === $filterSector;
    });
    $competencies = array_values($competencies);

    $areasData = array_filter($areasData, function($area, $code) use ($filterSector) {
        $parts = explode('_', $code);
        return strtoupper($parts[0] ?? '') === $filterSector || strpos($code, $filterSector) === 0;
    }, ARRAY_FILTER_USE_BOTH);

    if (!empty($autovalutazioneAreas)) {
        $autovalutazioneAreas = array_filter($autovalutazioneAreas, function($area, $code) use ($filterSector) {
            $parts = explode('_', $code);
            return strtoupper($parts[0] ?? '') === $filterSector || strpos($code, $filterSector) === 0;
        }, ARRAY_FILTER_USE_BOTH);
    }

    if (!empty($gapAnalysisData)) {
        $gapAnalysisData = array_filter($gapAnalysisData, function($gap, $code) use ($filterSector) {
            $parts = explode('_', $code);
            return strtoupper($parts[0] ?? '') === $filterSector;
        }, ARRAY_FILTER_USE_BOTH);
    }

    $totalQuestions = 0;
    $correctQuestions = 0;
    foreach ($competencies as $comp) {
        $totalQuestions += $comp['total_questions'] ?? 0;
        $correctQuestions += $comp['correct_questions'] ?? 0;
    }
    $summary['total_questions'] = $totalQuestions;
    $summary['questions_total'] = $totalQuestions;
    $summary['correct_questions'] = $correctQuestions;
    $summary['correct_total'] = $correctQuestions;
    $summary['overall_percentage'] = $totalQuestions > 0 ? round(($correctQuestions / $totalQuestions) * 100, 1) : 0;
    $summary['total_competencies'] = count($competencies);

    $evaluation = get_evaluation_band($summary['overall_percentage']);
    $certProgress = generate_certification_progress($competencies);
}

// ============================================
// HELPER: Strip emoji (TCPDF non supporta Unicode emoji, li rende come "?")
// ============================================
function pdf_strip_emoji($text) {
    // Rimuove emoji Unicode (range principali: Emoticons, Symbols, Transport, Misc, Dingbats, etc.)
    $clean = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $text); // Emoticons
    $clean = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', '', $clean); // Misc Symbols & Pictographs
    $clean = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $clean); // Transport & Map
    $clean = preg_replace('/[\x{1F1E0}-\x{1F1FF}]/u', '', $clean); // Flags
    $clean = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $clean);   // Misc Symbols (⚡⚠️ etc.)
    $clean = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $clean);   // Dingbats (✅ etc.)
    $clean = preg_replace('/[\x{FE00}-\x{FE0F}]/u', '', $clean);   // Variation Selectors
    $clean = preg_replace('/[\x{200D}]/u', '', $clean);             // Zero Width Joiner
    $clean = preg_replace('/[\x{20E3}]/u', '', $clean);             // Combining Enclosing Keycap
    $clean = preg_replace('/[\x{E0020}-\x{E007F}]/u', '', $clean); // Tags
    return trim($clean);
}

// ============================================
// HELPER: Scrivi SVG nel PDF
// ============================================
function pdf_write_svg($pdf, $svgContent, $widthMm = 180) {
    // TCPDF non supporta rgba() - converti in hex con fill-opacity
    $svgContent = preg_replace_callback(
        '/fill\s*=\s*"rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d.]+)\s*\)"/',
        function($m) {
            $hex = sprintf('#%02x%02x%02x', $m[1], $m[2], $m[3]);
            return 'fill="' . $hex . '" fill-opacity="' . $m[4] . '"';
        },
        $svgContent
    );
    // TCPDF non supporta hex a 8 cifre (#RRGGBBAA) - converti in hex 6 cifre + fill-opacity
    $svgContent = preg_replace_callback(
        '/fill\s*=\s*"(#[0-9a-fA-F]{6})([0-9a-fA-F]{2})"/',
        function($m) {
            $opacity = round(hexdec($m[2]) / 255, 2);
            return 'fill="' . $m[1] . '" fill-opacity="' . $opacity . '"';
        },
        $svgContent
    );

    $svgHeight = $widthMm * 0.75;
    if (preg_match('/viewBox\s*=\s*"[\d.]+\s+[\d.]+\s+([\d.]+)\s+([\d.]+)"/i', $svgContent, $vb)) {
        $ratio = floatval($vb[2]) / max(floatval($vb[1]), 1);
        $svgHeight = $widthMm * $ratio;
    }

    if ($pdf->GetY() + $svgHeight > $pdf->getPageHeight() - 20) {
        $pdf->AddPage();
    }

    $margins = $pdf->GetMargins();
    $printW = $pdf->getPageWidth() - $margins['left'] - $margins['right'];
    $x = $margins['left'] + max(0, ($printW - $widthMm) / 2);

    $tmpFile = tempnam(sys_get_temp_dir(), 'ftm_svg_');
    file_put_contents($tmpFile, $svgContent);
    try {
        $pdf->ImageSVG($tmpFile, $x, $pdf->GetY(), $widthMm, $svgHeight);
        $pdf->SetY($pdf->GetY() + $svgHeight + 3);
    } catch (Exception $e) {
        $pdf->writeHTML('<p style="color:#999; text-align:center;">Grafico non disponibile</p>', true, false, true, false, '');
    }
    @unlink($tmpFile);
}

// ============================================
// HELPER: Section title bar
// ============================================
function pdf_section_title($pdf, $title, $bg = '#dd0000', $newPage = false) {
    if ($newPage) {
        $pdf->AddPage();
    }
    $h = '<table cellpadding="8" style="width:100%;"><tr><td style="background-color:' . $bg . '; color:#ffffff; font-size:12pt; font-weight:bold;">' . $title . '</td></tr></table><br/>';
    $pdf->writeHTML($h, true, false, true, false, '');
}

// ============================================
// PDF RENDER: Valutazione
// ============================================
function pdf_render_valutazione($pdf, $p) {
    if (empty($p['printPanoramica'])) return;
    $ev = $p['evaluation'];
    $su = $p['summary'];

    $html = '<table cellpadding="8" style="width:100%; border:3px solid ' . $ev['color'] . ';">';
    $html .= '<tr><td width="75%" style="background-color:' . ($ev['bgColor'] ?? '#f8f9fa') . ';">';
    $html .= '<span style="font-size:14pt; color:' . $ev['color'] . '; font-weight:bold;">VALUTAZIONE: ' . s($ev['label']) . '</span><br/>';
    $html .= '<span style="font-size:9pt;">' . s($ev['description']) . '</span><br/>';
    $html .= '<span style="font-size:9pt;"><strong>Azione:</strong> ' . s($ev['action']) . '</span>';
    $html .= '</td><td width="25%" style="text-align:center; background-color:' . ($ev['bgColor'] ?? '#f8f9fa') . ';">';
    $html .= '<span style="font-size:22pt; font-weight:bold; color:' . $ev['color'] . ';">' . ($su['correct_total'] ?? $su['correct_questions']) . '/' . ($su['questions_total'] ?? $su['total_questions']) . '</span><br/>';
    $html .= '<small>risposte corrette</small></td></tr></table><br/>';
    $pdf->writeHTML($html, true, false, true, false, '');
}

// ============================================
// PDF RENDER: Progressi
// ============================================
function pdf_render_progressi($pdf, $p) {
    if (empty($p['printProgressi'])) return;
    $c = $p['certProgress'];

    pdf_section_title($pdf, 'PROGRESSO CERTIFICAZIONE');

    $pct = $c['percentage'];
    $html = '<table cellpadding="0" style="width:100%; border:1px solid #dee2e6;">';
    $html .= '<tr><td style="background-color:#27ae60; width:' . max($pct, 1) . '%; color:#ffffff; text-align:center; font-weight:bold; padding:6px;">' . $pct . '% completato</td>';
    if ($pct < 100) {
        $html .= '<td style="background-color:#ecf0f1; width:' . (100 - $pct) . '%;"></td>';
    }
    $html .= '</tr></table><br/>';

    $html .= '<table cellpadding="8" style="width:100%;">';
    $html .= '<tr>';
    $html .= '<td width="33%" style="text-align:center; background-color:#d5f4e6; border:1px solid #27ae60;"><span style="font-size:20pt; font-weight:bold; color:#27ae60;">' . $c['certified'] . '</span><br/><small>Certificate (&gt;=80%)</small></td>';
    $html .= '<td width="33%" style="text-align:center; background-color:#fef9e7; border:1px solid #f39c12;"><span style="font-size:20pt; font-weight:bold; color:#f39c12;">' . $c['inProgress'] . '</span><br/><small>In corso</small></td>';
    $html .= '<td width="34%" style="text-align:center; background-color:#fadbd8; border:1px solid #c0392b;"><span style="font-size:20pt; font-weight:bold; color:#c0392b;">' . $c['notStarted'] . '</span><br/><small>Da iniziare</small></td>';
    $html .= '</tr></table><br/>';
    $pdf->writeHTML($html, true, false, true, false, '');
}

// ============================================
// PDF RENDER: Radar Aree
// ============================================
function pdf_render_radar_aree($pdf, $p) {
    if (empty($p['printRadarAree']) || empty($p['areasData'])) return;

    pdf_section_title($pdf, 'RADAR PANORAMICA AREE', '#dd0000', true);

    $radarData = [];
    foreach ($p['areasData'] as $area) {
        $radarData[] = ['label' => pdf_strip_emoji($area['icon'] . ' ' . $area['name']), 'value' => $area['percentage']];
    }
    $svg = generate_svg_radar($radarData, 'Panoramica Aree', 380);
    pdf_write_svg($pdf, $svg, 170);

    $html = '<table cellpadding="4" style="width:100%; font-size:9pt; border:1px solid #dee2e6;">';
    $html .= '<tr style="background-color:#ecf0f1;"><th>Area</th><th style="text-align:center;">Competenze</th><th style="text-align:center;">Risposte</th><th style="text-align:center;">Punteggio</th></tr>';
    foreach ($p['areasData'] as $area) {
        $band = get_evaluation_band($area['percentage']);
        $html .= '<tr><td><strong>' . s($area['name']) . '</strong></td>';
        $html .= '<td style="text-align:center;">' . $area['count'] . '</td>';
        $html .= '<td style="text-align:center;">' . $area['correct_questions'] . '/' . $area['total_questions'] . '</td>';
        $html .= '<td style="text-align:center;"><span style="background-color:' . $band['color'] . '; color:#fff; padding:2px 6px; font-weight:bold;">' . $area['percentage'] . '%</span></td></tr>';
    }
    $html .= '</table><br/>';
    $pdf->writeHTML($html, true, false, true, false, '');
}

// ============================================
// PDF RENDER: Radar Dettagli (per area)
// ============================================
function pdf_render_radar_dettagli($pdf, $p) {
    if (empty($p['printRadarAreas'])) return;

    $defaultColors = [
        'A' => '#3498db', 'B' => '#e67e22', 'C' => '#9b59b6',
        'D' => '#1abc9c', 'E' => '#e74c3c', 'F' => '#f39c12', 'default' => '#bc3d2f',
    ];
    $compDesc = $p['competencyDescriptions'];

    foreach ($p['printRadarAreas'] as $areaCode) {
        if (!isset($p['areasData'][$areaCode])) continue;
        $area = $p['areasData'][$areaCode];

        $areaColor = $area['color'] ?? null;
        if (empty($areaColor)) {
            $k = strtoupper(substr($areaCode, 0, 1));
            $areaColor = $defaultColors[$k] ?? $defaultColors['default'];
        }

        pdf_section_title($pdf, 'DETTAGLIO: ' . $area['name'], $areaColor, true);

        $areaComps = [];
        foreach ($area['competencies'] as $comp) {
            $code = $comp['idnumber'] ?: $comp['name'];
            $info = $compDesc[$code] ?? null;
            $name = $info ? ($info['name'] ?? $code) : $code;
            $areaComps[] = ['label' => $name, 'value' => $comp['percentage']];
        }

        $svg = generate_svg_radar($areaComps, $area['name'] . ' - ' . $area['percentage'] . '%', 320, $areaColor . '40', $areaColor);
        pdf_write_svg($pdf, $svg, 155);

        $html = '<table cellpadding="3" style="width:100%; font-size:8pt; border:1px solid #dee2e6;">';
        $html .= '<tr style="background-color:#34495e; color:#ffffff;"><th>Codice</th><th>Competenza</th><th style="text-align:center;">Risposte</th><th style="text-align:center;">%</th><th>Valutazione</th></tr>';
        foreach ($area['competencies'] as $comp) {
            $code = $comp['idnumber'] ?: $comp['name'];
            $info = $compDesc[$code] ?? null;
            $name = $info ? ($info['full_name'] ?? $info['name']) : $code;
            $band = get_evaluation_band($comp['percentage']);
            $html .= '<tr><td><small>' . s($code) . '</small></td><td>' . s($name) . '</td>';
            $html .= '<td style="text-align:center;">' . $comp['correct_questions'] . '/' . $comp['total_questions'] . '</td>';
            $html .= '<td style="text-align:center;"><span style="background-color:' . $band['color'] . '; color:#fff; padding:1px 5px; font-weight:bold;">' . $comp['percentage'] . '%</span></td>';
            $html .= '<td style="color:' . $band['color'] . '; font-weight:bold;">' . s($band['label']) . '</td></tr>';
        }
        $html .= '</table><br/>';
        $pdf->writeHTML($html, true, false, true, false, '');
    }
}

// ============================================
// PDF RENDER: Piano d'Azione
// ============================================
function pdf_render_piano($pdf, $p) {
    if (empty($p['printPiano'])) return;
    $plan = $p['actionPlan'];

    $cats = [
        'excellence' => ['t' => 'ECCELLENZA', 'c' => '#27ae60', 'bg' => '#d5f4e6', 'a' => 'Certificata'],
        'good'       => ['t' => 'ACQUISITE', 'c' => '#dd0000', 'bg' => '#d1f2eb', 'a' => 'Consolidare'],
        'toImprove'  => ['t' => 'DA MIGLIORARE', 'c' => '#e67e22', 'bg' => '#fef9e7', 'a' => 'Ripasso'],
        'critical'   => ['t' => 'CRITICO', 'c' => '#c0392b', 'bg' => '#fadbd8', 'a' => 'Formazione base'],
    ];

    foreach ($cats as $key => $cat) {
        if (empty($plan[$key])) continue;
        pdf_section_title($pdf, $cat['t'], $cat['c']);

        $planHeader = '<tr style="background-color:#ecf0f1;"><th>Competenza</th><th style="text-align:center;">Azione</th><th style="text-align:center;">Risultato</th></tr>';
        $planChunks = array_chunk($plan[$key], 15);

        foreach ($planChunks as $ci => $chunk) {
            if ($ci > 0) {
                $pdf->AddPage();
                $pdf->writeHTML('<span style="font-size:8pt; color:#999;">' . s($cat['t']) . ' (continua)</span><br/>', true, false, true, false, '');
            }
            $html = '<table cellpadding="4" style="width:100%; font-size:9pt; border:1px solid #dee2e6;">';
            $html .= $planHeader;
            foreach ($chunk as $item) {
                $html .= '<tr style="background-color:' . $cat['bg'] . ';"><td><strong>' . s($item['name']) . '</strong><br/><small>' . s($item['code']) . '</small></td>';
                $html .= '<td style="text-align:center;">' . $cat['a'] . '</td>';
                $html .= '<td style="text-align:center;"><span style="background-color:' . $cat['c'] . '; color:#fff; padding:2px 6px; font-weight:bold;">' . $item['percentage'] . '%</span></td></tr>';
            }
            $html .= '</table><br/>';
            $pdf->writeHTML($html, true, false, true, false, '');
        }
    }
}

// ============================================
// PDF RENDER: Dettagli Competenze
// ============================================
function pdf_render_dettagli($pdf, $p) {
    if (empty($p['printDettagli'])) return;

    pdf_section_title($pdf, 'DETTAGLIO COMPETENZE', '#bc3d2f', true);

    $compDesc = $p['competencyDescriptions'];
    $areaDesc = $p['areaDescriptions'];
    $sector = $p['sector'];
    $comps = $p['competencies'];
    $total = count($comps);

    // Header della tabella (riusato ad ogni chunk)
    $header = '<tr style="background-color:#34495e; color:#ffffff;">';
    $header .= '<th width="4%">#</th><th width="8%">Area</th><th width="12%">Codice</th>';
    $header .= '<th width="36%">Competenza</th><th width="12%" style="text-align:center;">Risposte</th>';
    $header .= '<th width="10%" style="text-align:center;">%</th><th width="18%">Valutazione</th></tr>';

    // Dividi in blocchi da 15 righe per evitare troncamenti
    $chunkSize = 15;
    $chunks = array_chunk($comps, $chunkSize);

    $i = 1;
    foreach ($chunks as $ci => $chunk) {
        if ($ci > 0) {
            $pdf->AddPage();
            $pdf->writeHTML('<span style="font-size:8pt; color:#999;">DETTAGLIO COMPETENZE (continua)</span><br/>', true, false, true, false, '');
        }
        $html = '<table cellpadding="3" style="width:100%; font-size:7pt; border:1px solid #dee2e6;">';
        $html .= $header;

        foreach ($chunk as $comp) {
            $code = $comp['idnumber'] ?: $comp['name'];
            $parts = explode('_', $code);
            if ($sector == 'AUTOMOBILE' && count($parts) >= 3) {
                preg_match('/^([A-Z])/i', $parts[2], $m);
                $aCode = isset($m[1]) ? strtoupper($m[1]) : 'OTHER';
            } else {
                $aCode = count($parts) >= 2 ? $parts[1] : 'OTHER';
            }
            $areaInfo = $areaDesc[$aCode] ?? ['icon' => '', 'color' => '#95a5a6'];
            $info = $compDesc[$code] ?? null;
            $name = $info ? ($info['full_name'] ?? $info['name']) : $code;
            $band = get_evaluation_band($comp['percentage']);

            $html .= '<tr style="border-left:4px solid ' . $band['color'] . ';">';
            $html .= '<td style="text-align:center; font-weight:bold;">' . $i . '</td>';
            $html .= '<td><span style="background-color:' . $areaInfo['color'] . '; color:#fff; padding:1px 4px; font-weight:bold; font-size:6pt;">' . $aCode . '</span></td>';
            $html .= '<td><small>' . s($code) . '</small></td>';
            $html .= '<td>' . s($name) . '</td>';
            $html .= '<td style="text-align:center;">' . $comp['correct_questions'] . '/' . $comp['total_questions'] . '</td>';
            $html .= '<td style="text-align:center;"><span style="background-color:' . $band['color'] . '; color:#fff; padding:1px 5px; font-weight:bold;">' . $comp['percentage'] . '%</span></td>';
            $html .= '<td style="color:' . $band['color'] . '; font-weight:bold;">' . s($band['label']) . '</td></tr>';
            $i++;
        }
        $html .= '</table><br/>';
        $pdf->writeHTML($html, true, false, true, false, '');
    }
}

// ============================================
// PDF RENDER: Dual Radar (Auto vs Performance)
// ============================================
function pdf_render_dual_legend($pdf, $legendData) {
    $html = '<table cellpadding="4" style="width:100%; font-size:8pt; border:1px solid #dee2e6;">';
    $html .= '<tr><th style="background-color:#34495e; color:#fff;" width="46%">Area</th>';
    $html .= '<th style="background-color:#667eea; color:#fff; text-align:center;" width="18%">Auto</th>';
    $html .= '<th style="background-color:#28a745; color:#fff; text-align:center;" width="18%">Reale</th>';
    $html .= '<th style="background-color:#34495e; color:#fff; text-align:center;" width="18%">Gap</th></tr>';

    foreach ($legendData as $item) {
        $av = is_numeric($item['auto']) ? $item['auto'] : 0;
        $pv = is_numeric($item['perf']) ? $item['perf'] : 0;
        $gap = $av - $pv;
        $gc = $gap > 15 ? '#dc3545' : ($gap < -15 ? '#f39c12' : '#28a745');
        $bg = $gap > 15 ? '#fadbd8' : ($gap < -15 ? '#fef9e7' : '#d5f5e3');

        $html .= '<tr style="background-color:' . $bg . ';">';
        $html .= '<td style="border:1px solid #dee2e6;"><strong>' . pdf_strip_emoji($item['icon']) . '</strong> ' . s($item['name']) . '</td>';
        $html .= '<td style="text-align:center; border:1px solid #dee2e6;">';
        $html .= is_numeric($item['auto']) ? '<span style="background-color:#667eea; color:#fff; padding:1px 4px; font-weight:bold;">' . round($item['auto']) . '%</span>' : '-';
        $html .= '</td><td style="text-align:center; border:1px solid #dee2e6;">';
        $html .= is_numeric($item['perf']) ? '<span style="background-color:#28a745; color:#fff; padding:1px 4px; font-weight:bold;">' . round($item['perf']) . '%</span>' : '-';
        $html .= '</td><td style="text-align:center; border:1px solid #dee2e6; color:' . $gc . '; font-weight:bold;">';
        $html .= (is_numeric($item['auto']) && is_numeric($item['perf'])) ? (($gap > 0 ? '+' : '') . round($gap) . '%') : '-';
        $html .= '</td></tr>';
    }
    $html .= '</table><br/>';
    $pdf->writeHTML($html, true, false, true, false, '');
}

function pdf_render_dual_radar($pdf, $p) {
    if (empty($p['printDualRadar']) || empty($p['autovalutazioneAreas'])) return;

    $autoAreas = $p['autovalutazioneAreas'];
    $areasData = $p['areasData'];
    $autoQName = $p['autovalutazioneQuizName'] ?? 'Autovalutazione Competenze';
    $quizNames = $p['selectedQuizNames'] ?? [];

    $legendData = [];
    foreach ($areasData as $code => $area) {
        $legendData[$code] = [
            'icon' => $area['icon'], 'name' => $area['name'],
            'auto' => isset($autoAreas[$code]) ? $autoAreas[$code]['percentage'] : '-',
            'perf' => $area['percentage'],
        ];
    }
    foreach ($autoAreas as $code => $area) {
        if (!isset($legendData[$code])) {
            $legendData[$code] = ['icon' => $area['icon'], 'name' => $area['name'], 'auto' => $area['percentage'], 'perf' => '-'];
        }
    }

    // Page 1: Auto radar
    pdf_section_title($pdf, 'AUTOVALUTAZIONE - Come lo studente si percepisce', '#667eea', true);
    $html = '<table cellpadding="4" style="width:100%;"><tr><td style="text-align:center; background-color:#f0f0ff; border:1px solid #667eea; font-size:9pt; color:#667eea; font-weight:bold;">Fonte: ' . s($autoQName) . '</td></tr></table><br/>';
    $pdf->writeHTML($html, true, false, true, false, '');

    $autoRadar = [];
    foreach ($autoAreas as $area) {
        $autoRadar[] = ['label' => pdf_strip_emoji($area['icon'] . ' ' . $area['name']), 'value' => $area['percentage']];
    }
    $svg = generate_svg_radar($autoRadar, '', 360, 'rgba(102,126,234,0.3)', '#667eea', 8, 200);
    pdf_write_svg($pdf, $svg, 155);
    pdf_render_dual_legend($pdf, $legendData);

    // Page 2: Performance radar
    $qText = !empty($quizNames) ? implode(', ', $quizNames) : 'Quiz Competenze';
    pdf_section_title($pdf, 'PERFORMANCE REALE - Risultati dai Quiz', '#27ae60', true);
    $html = '<table cellpadding="4" style="width:100%;"><tr><td style="text-align:center; background-color:#f0fff0; border:1px solid #28a745; font-size:9pt; color:#28a745; font-weight:bold;">Fonte: ' . s($qText) . '</td></tr></table><br/>';
    $pdf->writeHTML($html, true, false, true, false, '');

    $perfRadar = [];
    foreach ($areasData as $area) {
        $perfRadar[] = ['label' => pdf_strip_emoji($area['icon'] . ' ' . $area['name']), 'value' => $area['percentage']];
    }
    $svg = generate_svg_radar($perfRadar, '', 360, 'rgba(40,167,69,0.3)', '#28a745', 8, 200);
    pdf_write_svg($pdf, $svg, 155);
    pdf_render_dual_legend($pdf, $legendData);
}

// ============================================
// PDF RENDER: Gap Analysis
// ============================================
function pdf_render_gap_analysis($pdf, $p) {
    if (empty($p['printGapAnalysis']) || empty($p['gapAnalysisData'])) return;
    $gapData = $p['gapAnalysisData'];

    $cSopra = count(array_filter($gapData, fn($g) => $g['tipo'] === 'sopravvalutazione'));
    $cSotto = count(array_filter($gapData, fn($g) => $g['tipo'] === 'sottovalutazione'));
    $cAllin = count(array_filter($gapData, fn($g) => $g['tipo'] === 'allineato'));

    pdf_section_title($pdf, 'GAP ANALYSIS: AUTOVALUTAZIONE vs PERFORMANCE', '#f093fb');

    $html = '<table cellpadding="6" style="width:100%;"><tr>';
    $html .= '<td width="33%" style="text-align:center; background-color:#fadbd8; border:1px solid #dc3545;"><span style="font-size:20pt; font-weight:bold; color:#dc3545;">' . $cSopra . '</span><br/><small>Sopravvalutazione</small></td>';
    $html .= '<td width="33%" style="text-align:center; background-color:#d5f5e3; border:1px solid #28a745;"><span style="font-size:20pt; font-weight:bold; color:#28a745;">' . $cAllin . '</span><br/><small>Allineato</small></td>';
    $html .= '<td width="34%" style="text-align:center; background-color:#fef9e7; border:1px solid #f39c12;"><span style="font-size:20pt; font-weight:bold; color:#f39c12;">' . $cSotto . '</span><br/><small>Sottovalutazione</small></td>';
    $html .= '</tr></table><br/>';

    // Header tabella gap (riusato per ogni chunk)
    $gapHeader = '<tr><th style="background-color:#34495e; color:#fff;" width="34%">Competenza</th>';
    $gapHeader .= '<th style="background-color:#667eea; color:#fff; text-align:center;" width="13%">Auto</th>';
    $gapHeader .= '<th style="background-color:#28a745; color:#fff; text-align:center;" width="13%">Reale</th>';
    $gapHeader .= '<th style="background-color:#34495e; color:#fff; text-align:center;" width="13%">Gap</th>';
    $gapHeader .= '<th style="background-color:#34495e; color:#fff; text-align:center;" width="27%">Analisi</th></tr>';

    // Dividi in blocchi da 12 righe per evitare troncamenti
    $gapChunks = array_chunk(array_values($gapData), 12);
    foreach ($gapChunks as $ci => $chunk) {
        if ($ci > 0) {
            $pdf->AddPage();
            $pdf->writeHTML('<span style="font-size:8pt; color:#999;">GAP ANALYSIS (continua)</span><br/>', true, false, true, false, '');
        }
        $html = '<table cellpadding="4" style="width:100%; font-size:8pt; border:1px solid #dee2e6;">';
        $html .= $gapHeader;

        foreach ($chunk as $gap) {
            $abg = '#f8f9fa';
            $alab = 'Allineato';
            if ($gap['tipo'] === 'sopravvalutazione') { $abg = '#dc3545'; $alab = 'Sopravvalutazione'; }
            elseif ($gap['tipo'] === 'sottovalutazione') { $abg = '#f39c12'; $alab = 'Sottovalutazione'; }
            else { $abg = '#28a745'; }

            $html .= '<tr style="background-color:' . $gap['bg'] . ';">';
            $html .= '<td><strong>' . s($gap['name']) . '</strong><br/><small style="color:#666;">' . s($gap['idnumber']) . '</small></td>';
            $html .= '<td style="text-align:center;"><span style="background-color:#667eea; color:#fff; padding:1px 4px; font-weight:bold;">' . round($gap['autovalutazione']) . '%</span></td>';
            $html .= '<td style="text-align:center;"><span style="background-color:#28a745; color:#fff; padding:1px 4px; font-weight:bold;">' . round($gap['performance']) . '%</span></td>';
            $html .= '<td style="text-align:center; color:' . $gap['colore'] . '; font-weight:bold;">' . $gap['icona'] . ' ' . ($gap['differenza'] > 0 ? '+' : '') . round($gap['differenza']) . '%</td>';
            $html .= '<td style="text-align:center;"><span style="background-color:' . $abg . '; color:#fff; padding:1px 6px; font-weight:bold; font-size:7pt;">' . $alab . '</span></td>';
            $html .= '</tr>';
        }
        $html .= '</table><br/>';
        $pdf->writeHTML($html, true, false, true, false, '');
    }
}

// ============================================
// PDF RENDER: Spunti Colloquio
// ============================================
function pdf_render_spunti($pdf, $p) {
    if (empty($p['printSpuntiColloquio']) || empty($p['colloquioHints'])) return;
    $hints = $p['colloquioHints'];

    pdf_section_title($pdf, 'SPUNTI PER IL COLLOQUIO', '#f5576c', true);

    if (!empty($hints['critici'])) {
        $html = '<h4 style="color:#dc3545; border-bottom:2px solid #dc3545; padding-bottom:5px;">Priorita Alta (' . count($hints['critici']) . ')</h4>';
        foreach ($hints['critici'] as $h) {
            $html .= '<table cellpadding="8" style="width:100%; background-color:#fff5f5; border-left:4px solid #dc3545; margin-bottom:6px;"><tr><td>';
            $html .= '<strong style="color:#dc3545;">' . s($h['competenza']) . '</strong>';
            $html .= ' <span style="background-color:#dc3545; color:#fff; padding:1px 4px; font-size:8pt;">Gap: ' . round($h['gap']['differenza']) . '%</span><br/>';
            $html .= '<span style="font-size:9pt;">' . s($h['messaggio']) . '</span><br/>';
            $html .= '<span style="font-size:9pt; color:#2980b9;">Domanda: "' . s($h['domanda']) . '"</span>';
            $html .= '</td></tr></table>';
        }
        $pdf->writeHTML($html, true, false, true, false, '');
    }

    if (!empty($hints['attenzione'])) {
        $html = '<h4 style="color:#f39c12; border-bottom:2px solid #f39c12; padding-bottom:5px;">Attenzione (' . count($hints['attenzione']) . ')</h4>';
        foreach ($hints['attenzione'] as $h) {
            $html .= '<table cellpadding="8" style="width:100%; background-color:#fffbf0; border-left:4px solid #f39c12; margin-bottom:6px;"><tr><td>';
            $html .= '<strong style="color:#b7950b;">' . s($h['competenza']) . '</strong>';
            $html .= ' <span style="background-color:#f39c12; color:#fff; padding:1px 4px; font-size:8pt;">Gap: ' . round($h['gap']['differenza']) . '%</span><br/>';
            $html .= '<span style="font-size:9pt;">' . s($h['messaggio']) . '</span><br/>';
            $html .= '<span style="font-size:9pt; color:#2980b9;">"' . s($h['domanda']) . '"</span>';
            $html .= '</td></tr></table>';
        }
        $pdf->writeHTML($html, true, false, true, false, '');
    }

    if (!empty($hints['positivi'])) {
        $html = '<h4 style="color:#28a745; border-bottom:2px solid #28a745; padding-bottom:5px;">Punti di Forza</h4>';
        $html .= '<table cellpadding="4" style="width:100%; font-size:9pt; border:1px solid #dee2e6;">';
        $html .= '<tr><th style="background-color:#d5f5e3; color:#155724;">Competenza</th>';
        $html .= '<th style="background-color:#d5f5e3; color:#155724; text-align:center;" width="15%">Stato</th>';
        $html .= '<th style="background-color:#d5f5e3; color:#155724;" width="45%">Note</th></tr>';
        foreach (array_slice($hints['positivi'], 0, 8) as $h) {
            // Converti emoji icona in testo per TCPDF.
            $stato_text = pdf_strip_emoji($h['gap']['icona'] ?? '');
            if (empty($stato_text)) {
                $stato_text = ($h['gap']['tipo'] ?? '') === 'allineato' ? 'OK' : ($h['gap']['tipo'] ?? '');
            }
            $html .= '<tr style="background-color:#f8fff9;"><td style="border:1px solid #ddd;" width="40%">' . s($h['competenza']) . '</td>';
            $html .= '<td style="border:1px solid #ddd; text-align:center; color:#28a745; font-weight:bold;" width="15%">' . s($stato_text) . '</td>';
            $html .= '<td style="border:1px solid #ddd; font-size:8pt;" width="45%">' . s($h['messaggio']) . '</td></tr>';
        }
        $html .= '</table><br/>';
        $pdf->writeHTML($html, true, false, true, false, '');
    }

    // Notes box
    $html = '<table cellpadding="12" style="width:100%; border:2px dashed #6c757d; background-color:#f8f9fa;"><tr><td>';
    $html .= '<strong style="color:#6c757d;">Note del Coach:</strong><br/><br/>';
    for ($n = 0; $n < 4; $n++) {
        $html .= '<hr style="border:0; border-bottom:1px solid #dee2e6; margin:12px 0;"/>';
    }
    $html .= '</td></tr></table><br/>';
    $pdf->writeHTML($html, true, false, true, false, '');
}

// ============================================
// PDF RENDER: Suggerimenti Rapporto
// ============================================
function pdf_render_suggerimenti($pdf, $p) {
    if (empty($p['printSuggerimentiRapporto'])) return;

    if (empty($p['gapAnalysisData'])) {
        pdf_section_title($pdf, 'SUGGERIMENTI RAPPORTO', '#667eea', true);
        $pdf->writeHTML('<table cellpadding="10" style="width:100%; background-color:#fff3cd; border:1px solid #ffc107;"><tr><td><strong>Dati non disponibili.</strong> Servono quiz autovalutazione completati e Gap Analysis attiva.</td></tr></table><br/>', true, false, true, false, '');
        return;
    }

    $gapData = $p['gapAnalysisData'];
    $tono = $p['tonoCommenti'] ?? 'formale';
    $sName = $p['studentName'] ?? 'Lo studente';
    $sogA = $p['sogliaAllineamento'] ?? 10;
    $sogM = $p['sogliaMonitorare'] ?? 25;

    $commentiAree = [];
    foreach ($gapData as $g) {
        $id = $g['idnumber'] ?? '';
        $parts = explode('_', $id);
        $ak = count($parts) >= 2 ? $parts[0] . '_' . $parts[1] : $id;
        $c = generate_gap_comment($ak, $g['autovalutazione'] ?? 0, $g['performance'] ?? 0, $tono, $sName, $sogA, $sogM);
        $c['competenza_nome'] = $g['name'] ?? $ak;
        $c['idnumber'] = $id;
        $commentiAree[$ak][] = $c;
    }

    $tutti = [];
    foreach ($commentiAree as $ac) { foreach ($ac as $c) { $tutti[] = $c; } }
    $riepilogo = generate_global_summary($tutti, $tono);

    $cC = count(array_filter($tutti, fn($c) => $c['tipo'] === 'sopravvalutazione_critica'));
    $cM = count(array_filter($tutti, fn($c) => $c['tipo'] === 'sopravvalutazione_lieve'));
    $cO = count(array_filter($tutti, fn($c) => $c['tipo'] === 'allineato'));
    $cP = count(array_filter($tutti, fn($c) => $c['tipo'] === 'sottovalutazione'));

    $tLabel = $tono === 'formale' ? 'Report formale' : 'Uso interno coach';
    pdf_section_title($pdf, 'SUGGERIMENTI RAPPORTO (' . $tLabel . ')', '#667eea', true);

    // Stats
    $html = '<table cellpadding="4" style="width:100%; background-color:#f5f7fa;">';
    $html .= '<tr><td colspan="4"><strong>Riepilogo Analisi Gap</strong></td></tr><tr>';
    $html .= '<td width="25%" style="text-align:center; background-color:#fadbd8; border:1px solid #dc3545;"><span style="font-size:16pt; font-weight:bold; color:#dc3545;">' . $cC . '</span><br/><small>Critici</small></td>';
    $html .= '<td width="25%" style="text-align:center; background-color:#fef9e7; border:1px solid #f39c12;"><span style="font-size:16pt; font-weight:bold; color:#f39c12;">' . $cM . '</span><br/><small>Monitorare</small></td>';
    $html .= '<td width="25%" style="text-align:center; background-color:#d5f5e3; border:1px solid #28a745;"><span style="font-size:16pt; font-weight:bold; color:#28a745;">' . $cO . '</span><br/><small>Allineati</small></td>';
    $html .= '<td width="25%" style="text-align:center; background-color:#d1ecf1; border:1px solid #17a2b8;"><span style="font-size:16pt; font-weight:bold; color:#17a2b8;">' . $cP . '</span><br/><small>Potenziale</small></td>';
    $html .= '</tr><tr><td colspan="4" style="font-size:9pt; padding:8px;">' . s($riepilogo) . '</td></tr></table><br/>';
    $pdf->writeHTML($html, true, false, true, false, '');

    $critici = array_filter($tutti, fn($c) => $c['tipo'] === 'sopravvalutazione_critica');
    $monit = array_filter($tutti, fn($c) => $c['tipo'] === 'sopravvalutazione_lieve');
    $allin = array_filter($tutti, fn($c) => $c['tipo'] === 'allineato');
    $sotto = array_filter($tutti, fn($c) => $c['tipo'] === 'sottovalutazione');

    // Critici
    if (!empty($critici)) {
        $html = '<h4 style="color:#dc3545; border-bottom:3px solid #dc3545;">Aree Critiche (' . count($critici) . ')</h4>';
        foreach ($critici as $c) {
            $html .= '<table cellpadding="8" style="width:100%; background-color:#fff5f5; border-left:4px solid #dc3545; margin-bottom:6px;"><tr><td>';
            $html .= '<strong style="color:#dc3545;">' . s($c['area_nome'] ?? $c['competenza_nome']) . '</strong>';
            $html .= ' <span style="background-color:#dc3545; color:#fff; padding:1px 4px; font-size:7pt;">Gap: ' . round($c['gap'], 1) . '%</span><br/>';
            $html .= '<span style="font-size:9pt;">' . s($c['commento']) . '</span>';
            if (!empty($c['attivita_bassa'])) {
                $html .= '<br/><strong style="color:#c0392b; font-size:8pt;">Cautele:</strong> <span style="font-size:8pt;">' . s(implode(', ', array_slice($c['attivita_bassa'], 0, 3))) . '</span>';
            }
            $html .= '</td></tr></table>';
        }
        $pdf->writeHTML($html, true, false, true, false, '');
    }

    // Monitorare
    if (!empty($monit)) {
        $html = '<h4 style="color:#f39c12; border-bottom:3px solid #f39c12;">Aree da Monitorare (' . count($monit) . ')</h4>';
        foreach ($monit as $c) {
            $html .= '<table cellpadding="8" style="width:100%; background-color:#fffbf0; border-left:4px solid #f39c12; margin-bottom:6px;"><tr><td>';
            $html .= '<strong style="color:#b7950b;">' . s($c['area_nome'] ?? $c['competenza_nome']) . '</strong>';
            $html .= ' <span style="background-color:#f39c12; color:#fff; padding:1px 4px; font-size:7pt;">Gap: ' . round($c['gap'], 1) . '%</span><br/>';
            $html .= '<span style="font-size:9pt;">' . s($c['commento']) . '</span>';
            if (!empty($c['attivita_alta'])) {
                $html .= '<br/><strong style="font-size:8pt;">Con supervisione:</strong> <span style="font-size:8pt;">' . s(implode(', ', array_slice($c['attivita_alta'], 0, 3))) . '</span>';
            }
            $html .= '</td></tr></table>';
        }
        $pdf->writeHTML($html, true, false, true, false, '');
    }

    // Allineati
    if (!empty($allin)) {
        $html = '<h4 style="color:#28a745; border-bottom:3px solid #28a745;">Aree Allineate (' . count($allin) . ')</h4>';
        $html .= '<table cellpadding="4" style="width:100%; font-size:9pt; border:1px solid #dee2e6;">';
        $html .= '<tr><th style="background-color:#d4edda; color:#155724;" width="30%">Area</th>';
        $html .= '<th style="background-color:#d4edda; color:#155724; text-align:center;" width="12%">Gap</th>';
        $html .= '<th style="background-color:#d4edda; color:#155724;" width="30%">Attivita</th>';
        $html .= '<th style="background-color:#d4edda; color:#155724;" width="28%">Ruoli</th></tr>';
        foreach (array_slice($allin, 0, 10) as $c) {
            $html .= '<tr style="background-color:#f8fff9;">';
            $html .= '<td style="border-bottom:1px solid #c3e6cb;"><strong>' . s($c['area_nome'] ?? $c['competenza_nome']) . '</strong></td>';
            $html .= '<td style="border-bottom:1px solid #c3e6cb; text-align:center;"><span style="background-color:#28a745; color:#fff; padding:1px 4px; font-weight:bold;">' . round($c['gap'], 1) . '%</span></td>';
            $html .= '<td style="border-bottom:1px solid #c3e6cb; font-size:8pt;">' . (!empty($c['attivita_alta']) ? s(implode(', ', array_slice($c['attivita_alta'], 0, 2))) : '-') . '</td>';
            $html .= '<td style="border-bottom:1px solid #c3e6cb; font-size:8pt;">' . (!empty($c['ruoli_adatti']) ? s(implode(', ', array_slice($c['ruoli_adatti'], 0, 2))) : '-') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table><br/>';
        $pdf->writeHTML($html, true, false, true, false, '');
    }

    // Sottovalutati
    if (!empty($sotto)) {
        $html = '<h4 style="color:#17a2b8; border-bottom:3px solid #17a2b8;">Potenziale da Valorizzare (' . count($sotto) . ')</h4>';
        foreach ($sotto as $c) {
            $html .= '<table cellpadding="8" style="width:100%; background-color:#f0f9ff; border-left:4px solid #17a2b8; margin-bottom:6px;"><tr><td>';
            $html .= '<strong style="color:#138496;">' . s($c['area_nome'] ?? $c['competenza_nome']) . '</strong>';
            $html .= ' <span style="background-color:#17a2b8; color:#fff; padding:1px 4px; font-size:7pt;">Gap: ' . round($c['gap'], 1) . '%</span><br/>';
            $html .= '<span style="font-size:9pt;">' . s($c['commento']) . '</span>';
            if (!empty($c['attivita_alta'])) {
                $html .= '<br/><strong style="color:#0c5460; font-size:8pt;">Gia pronto per:</strong> <span style="font-size:8pt;">' . s(implode(', ', array_slice($c['attivita_alta'], 0, 3))) . '</span>';
            }
            $html .= '</td></tr></table>';
        }
        $pdf->writeHTML($html, true, false, true, false, '');
    }

    // Legend
    $html = '<table cellpadding="6" style="width:100%; font-size:8pt; background-color:#f8f9fa;"><tr><td>';
    $html .= '<strong>Soglie:</strong> Allineato: gap &lt; ' . $sogA . '% | Monitorare: ' . $sogA . '-' . $sogM . '% | Critico: &gt; ' . $sogM . '% | Sottovalutazione: performance &gt; auto';
    $html .= '</td></tr></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
}

// ============================================
// PDF RENDER: Overlay Radar
// ============================================
function pdf_render_overlay_radar($pdf, $p) {
    if (empty($p['printOverlayRadar'])) return;

    $labels = $p['overlayLabels'] ?? [];
    $oQuiz = $p['overlayQuiz'] ?? [];
    $oAuto = $p['overlayAuto'] ?? [];
    $oLab = $p['overlayLabeval'] ?? [];
    $oCoach = $p['overlayCoach'] ?? [];

    if (empty($labels) || count($labels) < 3) return;

    pdf_section_title($pdf, 'CONFRONTO MULTI-FONTE (Overlay)', '#667eea', true);

    // Rilevamento = (Quiz+Lab)/2 - usa !== null per includere valori 0 legittimi
    $rilev = [];
    for ($i = 0; $i < count($labels); $i++) {
        $q = $oQuiz[$i] ?? null;
        $l = $oLab[$i] ?? null;
        $src = 0; $tot = 0;
        if ($q !== null) { $tot += $q; $src++; }
        if ($l !== null) { $tot += $l; $src++; }
        $rilev[] = $src > 0 ? round($tot / $src, 1) : 0;
    }

    $datasets = [];
    $datasets[] = ['data' => $rilev, 'label' => 'Rilevamento (Quiz+Lab)', 'fill' => 'rgba(40,167,69,0.15)', 'stroke' => '#28a745'];

    $hasAuto = array_filter($oAuto, function($v) { return $v !== null; });
    if (!empty($hasAuto)) {
        $datasets[] = ['data' => array_map(function($v) { return $v ?? 0; }, $oAuto), 'label' => 'Autovalutazione', 'fill' => 'rgba(102,126,234,0.15)', 'stroke' => '#667eea'];
    }

    $hasCoach = array_filter($oCoach, function($v) { return $v !== null; });
    if (!empty($hasCoach)) {
        $datasets[] = ['data' => array_map(function($v) { return $v ?? 0; }, $oCoach), 'label' => 'Formatore', 'fill' => 'rgba(220,53,69,0.15)', 'stroke' => '#dc3545'];
    }

    $svg = generate_svg_overlay_radar($datasets, $labels, 490, 'Sovrapposizione Confronto Multi-Fonte');
    pdf_write_svg($pdf, $svg, 180);

    // Comparison table
    // Calcola larghezze colonne in base a quante fonti ci sono.
    $numDataCols = 2; // Rilevamento + Media + Gap Max sono sempre presenti, ma area è la prima.
    if (!empty($hasAuto)) $numDataCols++;
    if (!empty($hasCoach)) $numDataCols++;
    $areaWidth = 30;
    $dataWidth = intval((100 - $areaWidth) / ($numDataCols + 2)); // +2 per Media e Gap Max.

    $html = '<table cellpadding="4" style="width:100%; font-size:8pt; border:1px solid #dee2e6;">';
    $html .= '<tr style="background-color:#f0f4f8;"><th width="' . $areaWidth . '%">Area</th>';
    $html .= '<th style="text-align:center; color:#28a745;" width="' . $dataWidth . '%">Rilevamento</th>';
    if (!empty($hasAuto)) $html .= '<th style="text-align:center; color:#667eea;" width="' . $dataWidth . '%">Autovalutazione</th>';
    if (!empty($hasCoach)) $html .= '<th style="text-align:center; color:#dc3545;" width="' . $dataWidth . '%">Formatore</th>';
    $html .= '<th style="text-align:center;" width="' . $dataWidth . '%">Media</th><th style="text-align:center;" width="' . $dataWidth . '%">Gap Max</th></tr>';

    for ($i = 0; $i < count($labels); $i++) {
        $vals = [$rilev[$i]];
        if (!empty($hasAuto)) $vals[] = ($oAuto[$i] ?? 0);
        if (!empty($hasCoach)) $vals[] = ($oCoach[$i] ?? 0);
        $valid = array_filter($vals, function($v) { return $v !== null && $v !== false; });
        $media = !empty($valid) ? round(array_sum($valid) / count($valid), 1) : 0;
        $gapMax = !empty($valid) ? round(max($valid) - min($valid), 1) : 0;
        $gc = $gapMax > 25 ? '#dc3545' : ($gapMax > 10 ? '#ffc107' : '#28a745');

        $html .= '<tr><td style="font-weight:bold;">' . s($labels[$i]) . '</td>';
        $html .= '<td style="text-align:center;">' . $rilev[$i] . '%</td>';
        if (!empty($hasAuto)) $html .= '<td style="text-align:center;">' . ($oAuto[$i] ?? 0) . '%</td>';
        if (!empty($hasCoach)) $html .= '<td style="text-align:center;">' . ($oCoach[$i] ?? 0) . '%</td>';
        $html .= '<td style="text-align:center; font-weight:bold;">' . $media . '%</td>';
        $html .= '<td style="text-align:center; color:' . $gc . '; font-weight:bold;">' . $gapMax . '%</td></tr>';
    }

    $html .= '</table><br/>';
    $html .= '<table cellpadding="4" style="width:100%; font-size:8pt; color:#666;"><tr><td>';
    $html .= 'Rilevamento: media Quiz + Laboratorio | Autovalutazione: percezione studente | Formatore: valutazione coach (Bloom) | Gap Max: differenza massima tra fonti';
    $html .= '</td></tr></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
}

// ============================================
// INIZIALIZZAZIONE PDF
// ============================================
$studentFullname = fullname($student);
$pdfFilename = 'Report_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentFullname) . '_' . date('Ymd');

$pdf = new pdf('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('FTM Competency Manager');
$pdf->SetAuthor('Fondazione Terzo Millennio');
$pdf->SetTitle('Scheda Competenze - ' . $studentFullname);
$pdf->SetSubject('Report Competenze Studente');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetFooterData([0, 0, 0], [0, 0, 0]);

$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(12, 15, 12);
$pdf->SetAutoPageBreak(true, 18);
$pdf->setFontSubsetting(true);
$pdf->SetFont('helvetica', '', 9, '', true);

// ============================================
// PAGINA 1: HEADER
// ============================================
$pdf->AddPage();

$html = '<table cellpadding="10" style="width:100%;">';
$html .= '<tr><td style="background-color:#dd0000; color:#ffffff; font-size:16pt; text-align:center; font-weight:bold;">SCHEDA VALUTAZIONE COMPETENZE</td></tr>';
$html .= '<tr><td style="background-color:#bc3d2f; color:#ffffff; font-size:10pt; text-align:center;">Fondazione Terzo Millennio - Formazione Professionale</td></tr>';
$html .= '</table><br/>';

$sectorDisplay = !empty($studentPrimarySector) ? strtoupper($studentPrimarySector)
    : (!empty($currentSector) ? strtoupper($currentSector)
    : (!empty($sector) ? strtoupper($sector) : 'Non definito'));
$hasFilter = !empty($printSectorFilter) && $printSectorFilter !== 'all';

$html .= '<table cellpadding="6" style="border:1px solid #dee2e6; width:100%;">';
$html .= '<tr><td width="70%" style="background-color:#f8f9fa;">';
$html .= '<strong>Studente:</strong> ' . s($studentFullname) . '<br/>';
$html .= '<strong>Email:</strong> ' . s($student->email) . '<br/>';
if ($course) {
    $html .= '<strong>Corso:</strong> ' . s(format_string($course->fullname)) . '<br/>';
}
$html .= '<strong>Settore:</strong> <span style="background-color:#dd0000; color:#fff; padding:2px 8px; font-weight:bold;">' . s($sectorDisplay) . '</span>';
if ($hasFilter && strtoupper($printSectorFilter) !== $sectorDisplay) {
    $html .= ' <span style="background-color:#ffc107; color:#333; padding:2px 6px; font-size:8pt;">Filtro: ' . strtoupper(s($printSectorFilter)) . '</span>';
}
$html .= '<br/><strong>Data:</strong> ' . date('d/m/Y H:i');
$html .= '</td><td width="30%" style="text-align:center; background-color:#f8f9fa;">';
$html .= '<span style="font-size:36pt; font-weight:bold; color:#dd0000;">' . $summary['overall_percentage'] . '%</span><br/>';
$html .= '<span style="font-size:9pt; color:#666;">Punteggio Globale</span>';
$html .= '</td></tr></table><br/>';

$pdf->writeHTML($html, true, false, true, false, '');

// ============================================
// RENDER SEZIONI NELL'ORDINE CONFIGURATO
// ============================================
$renderParams = [
    'printPanoramica'    => $printPanoramica,
    'printProgressi'     => $printProgressi,
    'printRadarAree'     => $printRadarAree,
    'printRadarAreas'    => $printRadarAreas ?? [],
    'printPiano'         => $printPiano,
    'printDettagli'      => $printDettagli,
    'printDualRadar'     => $printDualRadar,
    'printGapAnalysis'   => $printGapAnalysis,
    'printSpuntiColloquio' => $printSpuntiColloquio,
    'evaluation'         => $evaluation,
    'summary'            => $summary,
    'certProgress'       => $certProgress,
    'areasData'          => $areasData,
    'competencies'       => $competencies,
    'competencyDescriptions' => $competencyDescriptions,
    'areaDescriptions'   => $areaDescriptions,
    'sector'             => $sector,
    'actionPlan'         => $actionPlan,
    'autovalutazioneAreas' => $autovalutazioneAreas ?? [],
    'gapAnalysisData'    => $gapAnalysisData ?? [],
    'colloquioHints'     => $colloquioHints ?? [],
    'selectedQuizNames'  => $selectedQuizNames ?? [],
    'autovalutazioneQuizName' => $autovalutazioneQuizName ?? null,
    'printSuggerimentiRapporto' => $printSuggerimentiRapporto ?? false,
    'tonoCommenti'       => $tonoCommenti ?? 'formale',
    'sogliaAllineamento' => $sogliaAllineamento ?? 10,
    'sogliaMonitorare'   => $sogliaMonitorare ?? 25,
    'studentName'        => fullname($student),
    'printOverlayRadar'  => $printOverlayRadar ?? false,
    'overlayAreas'       => $overlayAreas ?? [],
    'overlayLabels'      => $overlayLabels ?? [],
    'overlayQuiz'        => $overlayQuiz ?? [],
    'overlayAuto'        => $overlayAuto ?? [],
    'overlayLabeval'     => $overlayLabeval ?? [],
    'overlayCoach'       => $overlayCoach ?? [],
];

$pdfRenderers = [
    'valutazione'    => 'pdf_render_valutazione',
    'progressi'      => 'pdf_render_progressi',
    'radar_aree'     => 'pdf_render_radar_aree',
    'radar_dettagli' => 'pdf_render_radar_dettagli',
    'piano'          => 'pdf_render_piano',
    'dettagli'       => 'pdf_render_dettagli',
    'dual_radar'     => 'pdf_render_dual_radar',
    'gap_analysis'   => 'pdf_render_gap_analysis',
    'spunti'         => 'pdf_render_spunti',
    'suggerimenti'   => 'pdf_render_suggerimenti',
    'overlay_radar'  => 'pdf_render_overlay_radar',
];

foreach ($sectionOrder as $sectionKey => $order) {
    if (isset($pdfRenderers[$sectionKey])) {
        $pdfRenderers[$sectionKey]($pdf, $renderParams);
    }
}

// ============================================
// FOOTER
// ============================================
$html = '<br/><table cellpadding="6" style="width:100%; border-top:3px solid #dd0000; font-size:8pt;">';
$html .= '<tr>';
$html .= '<td width="33%"><strong style="color:#bc3d2f;">Generato:</strong> ' . date('d/m/Y H:i') . '</td>';
$html .= '<td width="34%" style="text-align:center;"><strong style="color:#bc3d2f;">FTM Competency Manager v2.0</strong></td>';
$html .= '<td width="33%" style="text-align:right;"><strong style="color:#bc3d2f;">Studente:</strong> ' . s($studentFullname) . '</td>';
$html .= '</tr></table>';
$html .= '<br/><table cellpadding="4" style="width:100%;">';
$html .= '<tr>';
$html .= '<td width="50%" style="text-align:center;"><br/><br/><hr style="width:150px; border:0; border-top:1px solid #bc3d2f;"/><span style="font-size:8pt; color:#969696;">Firma Docente</span></td>';
$html .= '<td width="50%" style="text-align:center;"><br/><br/><hr style="width:150px; border:0; border-top:1px solid #bc3d2f;"/><span style="font-size:8pt; color:#969696;">Firma Studente</span></td>';
$html .= '</tr></table>';
$pdf->writeHTML($html, true, false, true, false, '');

// ============================================
// OUTPUT PDF
// ============================================
$pdf->Output($pdfFilename . '.pdf', 'D');
exit;
