<?php
/**
 * Student Print Template - Competency Manager
 * Con GRAFICI RADAR SVG per la stampa
 *
 * ============================================
 * VERSIONE CON ESTENSIONI ADDITIVE COACHMANAGER
 * - Doppio Radar (Autovalutazione affiancato)
 * - Gap Analysis
 * - Spunti Colloquio
 * - Ordinamento sezioni configurabile
 * ============================================
 *
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();

// ============================================
// FUNZIONI DI RENDERING PER ORDINAMENTO
// ============================================

/**
 * Render sezione Valutazione (Panoramica)
 */
function render_section_valutazione($params) {
    if (!$params['printPanoramica']) return;
    $evaluation = $params['evaluation'];
    $summary = $params['summary'];
    ?>
    <div class="evaluation-box" style="border-color: <?php echo $evaluation['color']; ?>;">
        <div class="left">
            <h3 style="color: <?php echo $evaluation['color']; ?>;"><?php echo $evaluation['icon']; ?> VALUTAZIONE: <?php echo $evaluation['label']; ?></h3>
            <p><?php echo $evaluation['description']; ?></p>
            <p><strong>💡 Azione:</strong> <?php echo $evaluation['action']; ?></p>
        </div>
        <div class="right" style="background: <?php echo $evaluation['bgColor']; ?>;">
            <div class="score" style="color: <?php echo $evaluation['color']; ?>;"><?php echo ($summary['correct_total'] ?? $summary['correct_questions']); ?>/<?php echo ($summary['questions_total'] ?? $summary['total_questions']); ?></div>
            <small>risposte corrette</small>
        </div>
    </div>
    <?php
}

/**
 * Render sezione Progresso Certificazione
 */
function render_section_progressi($params) {
    if (!$params['printProgressi']) return;
    $certProgress = $params['certProgress'];
    ?>
    <div class="section">
        <div class="section-title blue">📊 PROGRESSO CERTIFICAZIONE</div>
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $certProgress['percentage']; ?>%;"></div>
                <div class="progress-text"><?php echo $certProgress['percentage']; ?>% completato</div>
            </div>
        </div>
        <div class="stats-grid">
            <div class="stat-box green"><div class="stat-number" style="color: #27ae60;"><?php echo $certProgress['certified']; ?></div><div class="stat-label">✅ Certificate (≥80%)</div></div>
            <div class="stat-box yellow"><div class="stat-number" style="color: #b7950b;"><?php echo $certProgress['inProgress']; ?></div><div class="stat-label">🔄 In corso</div></div>
            <div class="stat-box red"><div class="stat-number" style="color: #c0392b;"><?php echo $certProgress['notStarted']; ?></div><div class="stat-label">⏳ Da iniziare</div></div>
        </div>
    </div>
    <?php
}

/**
 * Render sezione Radar Panoramica Aree
 */
function render_section_radar_aree($params) {
    if (!$params['printRadarAree'] || empty($params['areasData'])) return;
    $areasData = $params['areasData'];
    ?>
    <div class="section">
        <div class="section-title blue">📊 RADAR PANORAMICA AREE</div>
        <div class="radar-container">
            <?php
            $radarAreasData = [];
            foreach ($areasData as $code => $areaData) {
                $radarAreasData[] = ['label' => $areaData['icon'] . ' ' . $areaData['name'], 'value' => $areaData['percentage']];
            }
            echo generate_svg_radar($radarAreasData, 'Panoramica Aree', 380);
            ?>
        </div>
        <table style="margin-top: 15px;"><thead><tr><th>Area</th><th>Competenze</th><th>Risposte</th><th>Punteggio</th></tr></thead><tbody>
        <?php foreach ($areasData as $code => $areaData): $band = get_evaluation_band($areaData['percentage']); ?>
        <tr><td><strong><?php echo $areaData['icon'] . ' ' . $areaData['name']; ?></strong></td><td style="text-align:center;"><?php echo $areaData['count']; ?></td><td style="text-align:center;"><?php echo $areaData['correct_questions']; ?>/<?php echo $areaData['total_questions']; ?></td><td style="text-align:center;"><span class="badge badge-<?php echo $band['class']; ?>"><?php echo $areaData['percentage']; ?>%</span></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <?php
}

/**
 * Render sezione Radar Dettaglio per Area
 */
function render_section_radar_dettagli($params) {
    if (empty($params['printRadarAreas'])) return;
    $areasData = $params['areasData'];
    $competencyDescriptions = $params['competencyDescriptions'];

    foreach ($params['printRadarAreas'] as $areaCode):
        if (!isset($areasData[$areaCode])) continue;
        $areaData = $areasData[$areaCode];
        $areaCompetencies = [];
        foreach ($areaData['competencies'] as $comp) {
            $code = $comp['idnumber'] ?: $comp['name'];
            $compInfo = $competencyDescriptions[$code] ?? null;
            $displayName = $compInfo ? ($compInfo['name'] ?? $code) : $code;
            $areaCompetencies[] = ['label' => $displayName, 'value' => $comp['percentage']];
        }
    ?>
    <div class="section">
        <div class="section-title" style="background: <?php echo $areaData['color']; ?>;"><?php echo $areaData['icon']; ?> DETTAGLIO: <?php echo $areaData['name']; ?></div>
        <div class="radar-container"><?php echo generate_svg_radar($areaCompetencies, $areaData['icon'] . ' ' . $areaData['name'] . ' - ' . $areaData['percentage'] . '%', 320, $areaData['color'] . '40', $areaData['color']); ?></div>
        <table><thead><tr><th>Codice</th><th>Competenza</th><th>Risposte</th><th>%</th><th>Valutazione</th></tr></thead><tbody>
        <?php foreach ($areaData['competencies'] as $comp): $code = $comp['idnumber'] ?: $comp['name']; $compInfo = $competencyDescriptions[$code] ?? null; $displayName = $compInfo ? ($compInfo['full_name'] ?? $compInfo['name']) : $code; $band = get_evaluation_band($comp['percentage']); ?>
        <tr><td><small><?php echo $code; ?></small></td><td><?php echo htmlspecialchars($displayName); ?></td><td style="text-align:center;"><?php echo $comp['correct_questions']; ?>/<?php echo $comp['total_questions']; ?></td><td style="text-align:center;"><span class="badge badge-<?php echo $band['class']; ?>"><?php echo $comp['percentage']; ?>%</span></td><td style="color:<?php echo $band['color']; ?>;font-weight:bold;"><?php echo $band['icon'] . ' ' . $band['label']; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <?php
    endforeach;
}

/**
 * Render sezione Piano d'Azione
 */
function render_section_piano($params) {
    if (!$params['printPiano']) return;
    $actionPlan = $params['actionPlan'];

    if (!empty($actionPlan['excellence'])):
    ?>
    <div class="section"><div class="section-title green">🌟 ECCELLENZA</div><table><thead><tr><th>Competenza</th><th>Stato</th><th>Risultato</th></tr></thead><tbody>
    <?php foreach ($actionPlan['excellence'] as $item): ?><tr style="background:#d5f4e6;"><td><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small><?php echo $item['code']; ?></small></td><td>Certificata</td><td style="text-align:center;"><span class="badge badge-success"><?php echo $item['percentage']; ?>%</span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif;

    if (!empty($actionPlan['good'])):
    ?>
    <div class="section"><div class="section-title blue">✅ ACQUISITE</div><table><thead><tr><th>Competenza</th><th>Azione</th><th>Risultato</th></tr></thead><tbody>
    <?php foreach ($actionPlan['good'] as $item): ?><tr style="background:#d1f2eb;"><td><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small><?php echo $item['code']; ?></small></td><td>Consolidare</td><td style="text-align:center;"><span class="badge badge-info"><?php echo $item['percentage']; ?>%</span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif;

    if (!empty($actionPlan['toImprove'])):
    ?>
    <div class="section"><div class="section-title orange">⚠️ DA MIGLIORARE</div><table><thead><tr><th>Competenza</th><th>Azione</th><th>Risultato</th></tr></thead><tbody>
    <?php foreach ($actionPlan['toImprove'] as $item): ?><tr style="background:#fef9e7;"><td><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small><?php echo $item['code']; ?></small></td><td>Ripasso ed esercizi</td><td style="text-align:center;"><span class="badge badge-warning"><?php echo $item['percentage']; ?>%</span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif;

    if (!empty($actionPlan['critical'])):
    ?>
    <div class="section"><div class="section-title red">🔴 CRITICO</div><table><thead><tr><th>Competenza</th><th>Azione</th><th>Risultato</th></tr></thead><tbody>
    <?php foreach ($actionPlan['critical'] as $item): ?><tr style="background:#fadbd8;"><td><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small><?php echo $item['code']; ?></small></td><td>Formazione base</td><td style="text-align:center;"><span class="badge badge-danger"><?php echo $item['percentage']; ?>%</span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif;
}

/**
 * Render sezione Dettaglio Competenze
 */
function render_section_dettagli($params) {
    if (!$params['printDettagli']) return;
    $competencies = $params['competencies'];
    $competencyDescriptions = $params['competencyDescriptions'];
    $areaDescriptions = $params['areaDescriptions'];
    $sector = $params['sector'];
    ?>
    <div class="section"><div class="section-title">📋 DETTAGLIO COMPETENZE</div>
    <table class="competency-table"><thead><tr><th>#</th><th>Area</th><th>Codice</th><th>Competenza</th><th>Risposte</th><th>%</th><th>Valutazione</th></tr></thead><tbody>
    <?php $i = 1; foreach ($competencies as $comp):
        $code = $comp['idnumber'] ?: $comp['name'];
        $parts = explode('_', $code);
        if ($sector == 'AUTOMOBILE' && count($parts) >= 3) { preg_match('/^([A-Z])/i', $parts[2], $matches); $aCode = isset($matches[1]) ? strtoupper($matches[1]) : 'OTHER'; } else { $aCode = count($parts) >= 2 ? $parts[1] : 'OTHER'; }
        $areaInfo = $areaDescriptions[$aCode] ?? ['icon' => '📁', 'color' => '#95a5a6'];
        $compInfo = $competencyDescriptions[$code] ?? null;
        $displayName = $compInfo ? ($compInfo['full_name'] ?? $compInfo['name']) : $code;
        $band = get_evaluation_band($comp['percentage']);
    ?>
    <tr style="border-left: 4px solid <?php echo $band['color']; ?>;"><td style="text-align:center;font-weight:bold;"><?php echo $i; ?></td><td><span class="badge" style="background:<?php echo $areaInfo['color']; ?>;color:white;"><?php echo $areaInfo['icon'] . ' ' . $aCode; ?></span></td><td><small><?php echo $code; ?></small></td><td><?php echo htmlspecialchars($displayName); ?></td><td style="text-align:center;"><?php echo $comp['correct_questions']; ?>/<?php echo $comp['total_questions']; ?></td><td style="text-align:center;"><span class="badge badge-<?php echo $band['class']; ?>"><?php echo $comp['percentage']; ?>%</span></td><td style="color:<?php echo $band['color']; ?>;font-weight:bold;"><?php echo $band['icon'] . ' ' . $band['label']; ?></td></tr>
    <?php $i++; endforeach; ?>
    </tbody></table></div>
    <?php
}

/**
 * Render sezione Doppio Radar (CoachManager)
 * VERSIONE CON GRAFICI SEPARATI IN PAGINE DIVERSE
 * Ogni grafico ha la sua legenda COMPLETA sotto (Auto, Reale, Gap)
 */
function render_section_dual_radar($params) {
    if (!$params['printDualRadar'] || empty($params['autovalutazioneAreas'])) return;
    $autovalutazioneAreas = $params['autovalutazioneAreas'];
    $areasData = $params['areasData'];

    // Prepara i dati per la legenda COMPLETA (come prima)
    $legendData = [];
    foreach ($areasData as $code => $area) {
        $autoValue = isset($autovalutazioneAreas[$code]) ? $autovalutazioneAreas[$code]['percentage'] : '-';
        $perfValue = $area['percentage'];
        $legendData[$code] = [
            'icon' => $area['icon'],
            'name' => $area['name'],
            'auto' => $autoValue,
            'perf' => $perfValue,
        ];
    }
    foreach ($autovalutazioneAreas as $code => $area) {
        if (!isset($legendData[$code])) {
            $legendData[$code] = [
                'icon' => $area['icon'],
                'name' => $area['name'],
                'auto' => $area['percentage'],
                'perf' => '-',
            ];
        }
    }

    // ============================================
    // PAGINA 1: RADAR AUTOVALUTAZIONE + LEGENDA COMPLETA
    // ============================================
    ?>
    <div class="section page-break-before" style="page-break-inside: avoid;">
        <div class="section-title purple">🧑 AUTOVALUTAZIONE - Come lo studente si percepisce</div>

        <div style="text-align: center; margin: 10px 0;">
            <?php
            $autoRadarData = [];
            foreach ($autovalutazioneAreas as $code => $area) {
                $autoRadarData[] = ['label' => $area['icon'] . ' ' . $area['name'], 'value' => $area['percentage']];
            }
            // Radar 490px (+40% totale) - maxLabelLen=250 per etichette complete
            echo generate_svg_radar($autoRadarData, '', 490, 'rgba(102,126,234,0.3)', '#667eea', 9, 250);
            ?>
        </div>

        <!-- LEGENDA COMPLETA (Auto, Reale, Gap) - DIMENSIONE +20% -->
        <div style="padding: 10px; background: #f8f9fa; border-radius: 6px; border: 1px solid #dee2e6;">
            <h6 style="margin: 0 0 8px; color: #34495e; font-size: 11pt; font-weight: bold;">📋 Legenda Aree di Competenza</h6>
            <table style="width: 100%; border-collapse: collapse; font-size: 8.5pt;">
                <thead>
                    <tr>
                        <th style="background: #34495e; color: white; padding: 5px 8px; text-align: left; border: 1px solid #2c3e50;">Area</th>
                        <th style="background: #667eea; color: white; padding: 5px 8px; text-align: center; width: 70px; border: 1px solid #5a6fd6;">🧑 Auto</th>
                        <th style="background: #28a745; color: white; padding: 5px 8px; text-align: center; width: 70px; border: 1px solid #1e7e34;">📊 Reale</th>
                        <th style="background: #34495e; color: white; padding: 5px 8px; text-align: center; width: 65px; border: 1px solid #2c3e50;">Gap</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($legendData as $code => $item):
                        $autoVal = is_numeric($item['auto']) ? $item['auto'] : 0;
                        $perfVal = is_numeric($item['perf']) ? $item['perf'] : 0;
                        $gap = $autoVal - $perfVal;
                        $gapColor = $gap > 15 ? '#dc3545' : ($gap < -15 ? '#f39c12' : '#28a745');
                        $gapIcon = $gap > 15 ? '⬆️' : ($gap < -15 ? '⬇️' : '✅');
                        $bgColor = ($gap > 15) ? '#fadbd8' : (($gap < -15) ? '#fef9e7' : '#d5f5e3');
                    ?>
                    <tr style="background: <?php echo $bgColor; ?>;">
                        <td style="padding: 5px 8px; border: 1px solid #dee2e6;"><strong><?php echo $item['icon']; ?></strong> <?php echo htmlspecialchars($item['name']); ?></td>
                        <td style="padding: 5px 8px; text-align: center; border: 1px solid #dee2e6;">
                            <?php if (is_numeric($item['auto'])): ?>
                            <span style="padding: 2px 6px; background: #667eea; color: white; border-radius: 3px; font-weight: bold;"><?php echo round($item['auto']); ?>%</span>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td style="padding: 5px 8px; text-align: center; border: 1px solid #dee2e6;">
                            <?php if (is_numeric($item['perf'])): ?>
                            <span style="padding: 2px 6px; background: #28a745; color: white; border-radius: 3px; font-weight: bold;"><?php echo round($item['perf']); ?>%</span>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td style="padding: 5px 8px; text-align: center; border: 1px solid #dee2e6; color: <?php echo $gapColor; ?>; font-weight: bold;">
                            <?php if (is_numeric($item['auto']) && is_numeric($item['perf'])): ?>
                            <?php echo $gapIcon . ($gap > 0 ? '+' : '') . round($gap); ?>%
                            <?php else: ?>-<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    // ============================================
    // PAGINA 2: RADAR PERFORMANCE + LEGENDA COMPLETA
    // ============================================
    ?>
    <div class="section page-break-before" style="page-break-inside: avoid;">
        <div class="section-title green">📊 PERFORMANCE REALE - Risultati dai Quiz</div>

        <div style="text-align: center; margin: 10px 0;">
            <?php
            $perfRadarData = [];
            foreach ($areasData as $code => $area) {
                $perfRadarData[] = ['label' => $area['icon'] . ' ' . $area['name'], 'value' => $area['percentage']];
            }
            // Radar 490px (+40% totale) - maxLabelLen=250 per etichette complete
            echo generate_svg_radar($perfRadarData, '', 490, 'rgba(40,167,69,0.3)', '#28a745', 9, 250);
            ?>
        </div>

        <!-- LEGENDA COMPLETA (Auto, Reale, Gap) - DIMENSIONE +20% -->
        <div style="padding: 10px; background: #f8f9fa; border-radius: 6px; border: 1px solid #dee2e6;">
            <h6 style="margin: 0 0 8px; color: #34495e; font-size: 11pt; font-weight: bold;">📋 Legenda Aree di Competenza</h6>
            <table style="width: 100%; border-collapse: collapse; font-size: 8.5pt;">
                <thead>
                    <tr>
                        <th style="background: #34495e; color: white; padding: 5px 8px; text-align: left; border: 1px solid #2c3e50;">Area</th>
                        <th style="background: #667eea; color: white; padding: 5px 8px; text-align: center; width: 70px; border: 1px solid #5a6fd6;">🧑 Auto</th>
                        <th style="background: #28a745; color: white; padding: 5px 8px; text-align: center; width: 70px; border: 1px solid #1e7e34;">📊 Reale</th>
                        <th style="background: #34495e; color: white; padding: 5px 8px; text-align: center; width: 65px; border: 1px solid #2c3e50;">Gap</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($legendData as $code => $item):
                        $autoVal = is_numeric($item['auto']) ? $item['auto'] : 0;
                        $perfVal = is_numeric($item['perf']) ? $item['perf'] : 0;
                        $gap = $autoVal - $perfVal;
                        $gapColor = $gap > 15 ? '#dc3545' : ($gap < -15 ? '#f39c12' : '#28a745');
                        $gapIcon = $gap > 15 ? '⬆️' : ($gap < -15 ? '⬇️' : '✅');
                        $bgColor = ($gap > 15) ? '#fadbd8' : (($gap < -15) ? '#fef9e7' : '#d5f5e3');
                    ?>
                    <tr style="background: <?php echo $bgColor; ?>;">
                        <td style="padding: 5px 8px; border: 1px solid #dee2e6;"><strong><?php echo $item['icon']; ?></strong> <?php echo htmlspecialchars($item['name']); ?></td>
                        <td style="padding: 5px 8px; text-align: center; border: 1px solid #dee2e6;">
                            <?php if (is_numeric($item['auto'])): ?>
                            <span style="padding: 2px 6px; background: #667eea; color: white; border-radius: 3px; font-weight: bold;"><?php echo round($item['auto']); ?>%</span>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td style="padding: 5px 8px; text-align: center; border: 1px solid #dee2e6;">
                            <?php if (is_numeric($item['perf'])): ?>
                            <span style="padding: 2px 6px; background: #28a745; color: white; border-radius: 3px; font-weight: bold;"><?php echo round($item['perf']); ?>%</span>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td style="padding: 5px 8px; text-align: center; border: 1px solid #dee2e6; color: <?php echo $gapColor; ?>; font-weight: bold;">
                            <?php if (is_numeric($item['auto']) && is_numeric($item['perf'])): ?>
                            <?php echo $gapIcon . ($gap > 0 ? '+' : '') . round($gap); ?>%
                            <?php else: ?>-<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

/**
 * Render sezione Gap Analysis (CoachManager)
 */
function render_section_gap_analysis($params) {
    if (!$params['printGapAnalysis'] || empty($params['gapAnalysisData'])) return;
    $gapAnalysisData = $params['gapAnalysisData'];
    $countSopra = count(array_filter($gapAnalysisData, fn($g) => $g['tipo'] === 'sopravvalutazione'));
    $countSotto = count(array_filter($gapAnalysisData, fn($g) => $g['tipo'] === 'sottovalutazione'));
    $countAllineato = count(array_filter($gapAnalysisData, fn($g) => $g['tipo'] === 'allineato'));
    ?>
    <div class="section">
        <div class="section-title pink">📊 GAP ANALYSIS: AUTOVALUTAZIONE vs PERFORMANCE</div>

        <div class="stats-grid">
            <div class="stat-box red">
                <div class="stat-number" style="color: #dc3545;"><?php echo $countSopra; ?></div>
                <div class="stat-label">⬆️ Sopravvalutazione</div>
            </div>
            <div class="stat-box green">
                <div class="stat-number" style="color: #28a745;"><?php echo $countAllineato; ?></div>
                <div class="stat-label">✅ Allineato</div>
            </div>
            <div class="stat-box yellow">
                <div class="stat-number" style="color: #f39c12;"><?php echo $countSotto; ?></div>
                <div class="stat-label">⬇️ Sottovalutazione</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="background: #34495e; color: white;">Competenza</th>
                    <th style="background: #667eea; color: white; text-align: center; width: 80px;">🧑 Auto</th>
                    <th style="background: #28a745; color: white; text-align: center; width: 80px;">📊 Reale</th>
                    <th style="background: #34495e; color: white; text-align: center; width: 80px;">Gap</th>
                    <th style="background: #34495e; color: white; text-align: center; width: 120px;">Analisi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gapAnalysisData as $gap): ?>
                <tr style="background: <?php echo $gap['bg']; ?>;">
                    <td>
                        <strong><?php echo htmlspecialchars($gap['name']); ?></strong><br>
                        <small style="color: #666;"><?php echo $gap['idnumber']; ?></small>
                    </td>
                    <td style="text-align: center;">
                        <span class="badge badge-primary"><?php echo round($gap['autovalutazione']); ?>%</span>
                    </td>
                    <td style="text-align: center;">
                        <span class="badge badge-success"><?php echo round($gap['performance']); ?>%</span>
                    </td>
                    <td style="text-align: center; color: <?php echo $gap['colore']; ?>; font-weight: bold;">
                        <?php echo $gap['icona'] . ' ' . ($gap['differenza'] > 0 ? '+' : '') . round($gap['differenza']) . '%'; ?>
                    </td>
                    <td style="text-align: center;">
                        <?php if ($gap['tipo'] === 'sopravvalutazione'): ?>
                        <span class="badge badge-danger">⬆️ Sopravvalutazione</span>
                        <?php elseif ($gap['tipo'] === 'sottovalutazione'): ?>
                        <span class="badge badge-warning">⬇️ Sottovalutazione</span>
                        <?php else: ?>
                        <span class="badge badge-success">✅ Allineato</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px; font-size: 9pt;">
            <strong>Legenda:</strong>
            <span style="color: #dc3545;">⬆️ Sopravvalutazione</span> = Lo studente si percepisce più competente (gap > 15%) |
            <span style="color: #28a745;">✅ Allineato</span> = Percezione coerente (gap ≤ 15%) |
            <span style="color: #f39c12;">⬇️ Sottovalutazione</span> = Lo studente si sottostima (gap < -15%)
        </div>
    </div>
    <?php
}

/**
 * Render sezione Spunti Colloquio (CoachManager)
 */
function render_section_spunti($params) {
    if (!$params['printSpuntiColloquio'] || empty($params['colloquioHints'])) return;
    $colloquioHints = $params['colloquioHints'];
    ?>
    <div class="section page-break-before">
        <div class="section-title coral">💬 SPUNTI PER IL COLLOQUIO</div>

        <?php if (!empty($colloquioHints['critici'])): ?>
        <div style="margin-bottom: 20px;">
            <h4 style="color: #dc3545; border-bottom: 2px solid #dc3545; padding-bottom: 5px;">🔴 Priorità Alta - Gap Critici (<?php echo count($colloquioHints['critici']); ?>)</h4>
            <?php foreach ($colloquioHints['critici'] as $hint): ?>
            <div class="colloquio-hint critical">
                <strong style="color: #dc3545;"><?php echo htmlspecialchars($hint['competenza']); ?></strong>
                <span class="badge badge-danger" style="margin-left: 10px;">Gap: <?php echo round($hint['gap']['differenza']); ?>%</span>
                <p style="margin: 8px 0 5px; font-size: 10pt;"><?php echo $hint['messaggio']; ?></p>
                <p style="margin: 0; font-size: 10pt; color: #2980b9; font-style: italic;">💡 Domanda suggerita: "<?php echo $hint['domanda']; ?>"</p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($colloquioHints['attenzione'])): ?>
        <div style="margin-bottom: 20px;">
            <h4 style="color: #f39c12; border-bottom: 2px solid #f39c12; padding-bottom: 5px;">⚠️ Attenzione - Gap Moderati (<?php echo count($colloquioHints['attenzione']); ?>)</h4>
            <?php foreach ($colloquioHints['attenzione'] as $hint): ?>
            <div class="colloquio-hint warning">
                <strong style="color: #b7950b;"><?php echo htmlspecialchars($hint['competenza']); ?></strong>
                <span class="badge badge-warning" style="margin-left: 10px;">Gap: <?php echo round($hint['gap']['differenza']); ?>%</span>
                <p style="margin: 8px 0 5px; font-size: 10pt;"><?php echo $hint['messaggio']; ?></p>
                <p style="margin: 0; font-size: 10pt; color: #2980b9; font-style: italic;">💡 "<?php echo $hint['domanda']; ?>"</p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($colloquioHints['positivi'])): ?>
        <div style="margin-bottom: 20px;">
            <h4 style="color: #28a745; border-bottom: 2px solid #28a745; padding-bottom: 5px;">✅ Punti di Forza da Valorizzare</h4>
            <table>
                <thead>
                    <tr>
                        <th style="background: #d5f5e3; border: 1px solid #28a745;">Competenza</th>
                        <th style="background: #d5f5e3; border: 1px solid #28a745; width: 100px; text-align: center;">Stato</th>
                        <th style="background: #d5f5e3; border: 1px solid #28a745;">Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($colloquioHints['positivi'], 0, 8) as $hint): ?>
                    <tr>
                        <td style="border: 1px solid #ddd;"><?php echo htmlspecialchars($hint['competenza']); ?></td>
                        <td style="border: 1px solid #ddd; text-align: center;">
                            <span style="color: #28a745; font-weight: bold;"><?php echo $hint['gap']['icona']; ?></span>
                        </td>
                        <td style="border: 1px solid #ddd; font-size: 9pt;"><?php echo $hint['messaggio']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Box note coach -->
        <div class="notes-box">
            <h5 style="margin: 0 0 10px; color: #6c757d;">📝 Note del Coach:</h5>
            <div class="notes-line"></div>
            <div class="notes-line"></div>
            <div class="notes-line"></div>
            <div class="notes-line"></div>
        </div>
    </div>
    <?php
}

// ============================================
// FINE FUNZIONI DI RENDERING
// ============================================

// ============================================
// FILTRO SETTORE PER STAMPA
// ============================================
if (!empty($printSectorFilter) && $printSectorFilter !== 'all') {
    $filterSector = strtoupper($printSectorFilter);

    // Filtra competenze
    $competencies = array_filter($competencies, function($comp) use ($filterSector) {
        $idnumber = $comp['idnumber'] ?? '';
        $parts = explode('_', $idnumber);
        return strtoupper($parts[0] ?? '') === $filterSector;
    });
    $competencies = array_values($competencies);

    // Filtra areasData (mantieni solo aree che iniziano con il settore)
    $areasData = array_filter($areasData, function($area, $code) use ($filterSector) {
        // Il code potrebbe essere SETTORE_AREA o solo AREA
        $parts = explode('_', $code);
        return strtoupper($parts[0] ?? '') === $filterSector || strpos($code, $filterSector) === 0;
    }, ARRAY_FILTER_USE_BOTH);

    // Filtra autovalutazioneAreas se presente
    if (!empty($autovalutazioneAreas)) {
        $autovalutazioneAreas = array_filter($autovalutazioneAreas, function($area, $code) use ($filterSector) {
            $parts = explode('_', $code);
            return strtoupper($parts[0] ?? '') === $filterSector || strpos($code, $filterSector) === 0;
        }, ARRAY_FILTER_USE_BOTH);
    }

    // Filtra gapAnalysisData se presente
    if (!empty($gapAnalysisData)) {
        $gapAnalysisData = array_filter($gapAnalysisData, function($gap, $code) use ($filterSector) {
            $parts = explode('_', $code);
            return strtoupper($parts[0] ?? '') === $filterSector;
        }, ARRAY_FILTER_USE_BOTH);
    }

    // Ricalcola summary per il settore filtrato
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

    // Ricalcola evaluation
    $evaluation = get_evaluation_band($summary['overall_percentage']);

    // Ricalcola certProgress
    $certProgress = generate_certification_progress($competencies);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheda Competenze - <?php echo fullname($student); ?></title>
    <!-- Google Font: Didact Gothic (font aziendale FTM) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Didact+Gothic&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           VARIABILI COLORI AZIENDALI FTM
           ============================================ */
        :root {
            --ftm-red: #dd0000;
            --ftm-red-dark: #bc3d2f;
            --ftm-red-light: #dd3333;
            --ftm-gray: #eaeaea;
            --ftm-gray-dark: #969696;
            --ftm-white: #ffffff;
            --ftm-black: #000000;
        }

        /* ============================================
           RESET E BASE
           ============================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Didact Gothic', 'Segoe UI', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            padding: 15px 20px;
            padding-top: 85px; /* Spazio maggiore per header ripetuto con logo */
            background: white;
            color: #333;
        }

        /* ============================================
           HEADER RIPETUTO SU OGNI PAGINA (running header)
           Con logo FTM e colori aziendali
           ============================================ */
        .running-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 55px;
            background: linear-gradient(135deg, var(--ftm-red) 0%, var(--ftm-red-dark) 100%);
            color: white;
            padding: 8px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 9pt;
            z-index: 1000;
            border-bottom: 3px solid var(--ftm-red-dark);
        }
        .running-header .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .running-header .logo-box {
            background: white;
            padding: 4px 8px;
            border-radius: 4px;
            display: flex;
            align-items: center;
        }
        .running-header .logo-box img {
            height: 32px;
            width: auto;
        }
        .running-header .student-name {
            font-weight: bold;
            font-size: 10pt;
        }
        .running-header .center {
            font-size: 9pt;
            text-align: center;
        }
        .running-header .right {
            font-size: 8pt;
            text-align: right;
        }

        /* ============================================
           HEADER PRINCIPALE (prima pagina)
           Con logo FTM grande e colori aziendali
           ============================================ */
        .header {
            background: linear-gradient(135deg, var(--ftm-red) 0%, var(--ftm-red-dark) 100%);
            color: white;
            padding: 20px 25px;
            margin-bottom: 25px;
            border-radius: 10px;
            page-break-after: avoid;
        }
        .header-logo-row {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 2px solid rgba(255,255,255,0.3);
        }
        .header-logo-row .logo-box {
            background: white;
            padding: 8px 12px;
            border-radius: 6px;
            margin-right: 15px;
            display: flex;
            align-items: center;
        }
        .header-logo-row .logo-box img {
            height: 45px;
            width: auto;
        }
        .header-logo-row .org-name {
            font-size: 11pt;
            font-weight: normal;
            color: #000000;
            background: white;
            padding: 8px 12px;
            border-radius: 6px;
        }
        .header-logo-row .org-name strong {
            color: #000000;
        }
        .header h1 { font-size: 18pt; margin-bottom: 8px; }
        .header-row { display: flex; justify-content: space-between; align-items: center; }
        .header-info p { margin: 4px 0; font-size: 9pt; }
        .header-score { text-align: right; }
        .header-score .big { font-size: 36pt; font-weight: bold; }
        .header-score .label { font-size: 9pt; opacity: 0.9; }

        /* ============================================
           SEZIONI - PAGE BREAK CONTROL
           ============================================ */
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
            orphans: 3;
            widows: 3;
        }
        .section-title {
            background: var(--ftm-red-dark);
            color: white;
            padding: 10px 15px;
            font-weight: bold;
            font-size: 12pt;
            margin-bottom: 12px;
            border-radius: 5px;
            page-break-after: avoid;
        }
        .section-title.green { background: #27ae60; }
        .section-title.blue { background: var(--ftm-red); }
        .section-title.orange { background: #e67e22; }
        .section-title.red { background: #c0392b; }
        .section-title.purple { background: linear-gradient(135deg, var(--ftm-red) 0%, var(--ftm-red-dark) 100%); }
        .section-title.pink { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .section-title.coral { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); color: #333; }

        /* ============================================
           EVALUATION BOX
           ============================================ */
        .evaluation-box {
            border: 3px solid;
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            page-break-inside: avoid;
        }
        .evaluation-box .left { flex: 1; }
        .evaluation-box .right { text-align: center; padding: 15px 25px; border-radius: 8px; min-width: 120px; }
        .evaluation-box h3 { font-size: 14pt; margin-bottom: 5px; }
        .evaluation-box p { font-size: 9pt; margin: 4px 0; }
        .evaluation-box .score { font-size: 22pt; font-weight: bold; }

        /* ============================================
           TABELLE - OTTIMIZZATE PER STAMPA
           ============================================ */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-bottom: 18px;
        }
        th, td {
            border: 1px solid #bdc3c7;
            padding: 4px 6px;
            text-align: left;
            line-height: 1.2;
        }
        th {
            background: #ecf0f1;
            font-weight: 600;
            padding: 5px 6px;
        }
        /* Thead ripetuto su ogni pagina per tabelle lunghe */
        thead { display: table-header-group; }
        tbody { display: table-row-group; }
        tr { page-break-inside: avoid; }

        .competency-table th { background: #34495e; color: white; }
        .competency-table tr:nth-child(even) { background: #f8f9fa; }

        /* ============================================
           BADGES
           ============================================ */
        .badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 8pt; font-weight: bold; }
        .badge-success { background: #27ae60; color: white; }
        .badge-info { background: #3498db; color: white; }
        .badge-warning { background: #f39c12; color: white; }
        .badge-orange { background: #e67e22; color: white; }
        .badge-danger { background: #c0392b; color: white; }
        .badge-primary { background: #667eea; color: white; }

        /* ============================================
           PROGRESS BAR
           ============================================ */
        .progress-container { margin: 18px 0; }
        .progress-bar { height: 28px; background: #ecf0f1; border-radius: 14px; overflow: hidden; position: relative; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #27ae60, #2ecc71); border-radius: 14px; }
        .progress-text { position: absolute; width: 100%; text-align: center; line-height: 28px; font-weight: bold; font-size: 10pt; }

        /* ============================================
           STATS GRID
           ============================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin: 18px 0;
            page-break-inside: avoid;
        }
        .stat-box { text-align: center; padding: 18px; border-radius: 8px; border: 1px solid #ddd; }
        .stat-box.green { background: #d5f4e6; border-color: #27ae60; }
        .stat-box.yellow { background: #fef9e7; border-color: #f39c12; }
        .stat-box.red { background: #fadbd8; border-color: #c0392b; }
        .stat-number { font-size: 28pt; font-weight: bold; }
        .stat-label { font-size: 8pt; color: #666; }

        /* ============================================
           RADAR CONTAINERS
           ============================================ */
        .radar-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin: 20px 0;
            page-break-inside: avoid;
        }
        .dual-radar-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 25px;
            margin: 20px 0;
        }
        .dual-radar-panel {
            text-align: center;
            width: 100%;
            page-break-inside: avoid;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            background: #fafafa;
        }
        .dual-radar-panel h4 { margin-bottom: 10px; font-size: 14pt; font-weight: bold; }

        /* ============================================
           GAP ANALYSIS ROWS
           ============================================ */
        .gap-row-sopra { background: #fadbd8 !important; }
        .gap-row-sotto { background: #fef9e7 !important; }
        .gap-row-allineato { background: #d5f5e3 !important; }

        /* ============================================
           COLLOQUIO HINTS
           ============================================ */
        .colloquio-hint {
            background: #f8f9fa;
            border-left: 4px solid;
            padding: 12px 15px;
            margin-bottom: 12px;
            border-radius: 0 5px 5px 0;
            page-break-inside: avoid;
        }
        .colloquio-hint.critical { border-color: #dc3545; background: #fadbd8; }
        .colloquio-hint.warning { border-color: #f39c12; background: #fef9e7; }
        .colloquio-hint.success { border-color: #28a745; background: #d5f5e3; }

        /* ============================================
           NOTES BOX
           ============================================ */
        .notes-box {
            background: #f8f9fa;
            border: 2px dashed #6c757d;
            padding: 18px;
            border-radius: 8px;
            margin-top: 20px;
            page-break-inside: avoid;
        }
        .notes-line { border-bottom: 1px solid #dee2e6; min-height: 32px; margin-bottom: 12px; }

        /* ============================================
           FOOTER
           ============================================ */
        .footer {
            margin-top: 35px;
            padding-top: 18px;
            border-top: 3px solid var(--ftm-red);
            display: flex;
            justify-content: space-between;
            font-size: 8pt;
            color: #666;
            page-break-inside: avoid;
        }
        .footer strong { color: var(--ftm-red-dark); }
        .signature-line {
            border-top: 1px solid var(--ftm-red-dark);
            width: 180px;
            margin-top: 45px;
            padding-top: 5px;
            text-align: center;
            color: var(--ftm-gray-dark);
        }

        /* ============================================
           @PAGE RULES - MARGINI STAMPA E NUMERAZIONE
           ============================================ */
        @page {
            size: A4;
            margin: 18mm 12mm 20mm 12mm; /* top right bottom left */

            /* Footer con numero pagina (supporto browser limitato) */
            @bottom-center {
                content: "Pagina " counter(page) " di " counter(pages);
                font-size: 8pt;
                color: #666;
            }
        }
        @page :first {
            margin-top: 12mm;
        }

        /* ============================================
           @MEDIA PRINT - OTTIMIZZAZIONI STAMPA
           ============================================ */
        @media print {
            body {
                padding: 0;
                padding-top: 75px; /* Maggiore distanza dall'header */
                font-size: 9pt;
            }

            /* Running header visibile in stampa */
            .running-header {
                position: fixed;
                top: 0;
            }

            /* Righe tabella più sottili in stampa */
            th, td {
                padding: 3px 5px !important;
                line-height: 1.1 !important;
            }

            /* Mantieni colori in stampa */
            .header, .section-title, .badge, .stat-box, .evaluation-box,
            .colloquio-hint, .running-header, .progress-fill {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            /* Page break controls */
            .section { page-break-inside: avoid; }
            .section-title { page-break-after: avoid; }
            h3, h4, h5 { page-break-after: avoid; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; }
            thead { display: table-header-group; }

            /* Evita orfani e vedove */
            p, li { orphans: 3; widows: 3; }

            /* Nascondi elementi non necessari */
            .no-print { display: none !important; }

            /* Footer sempre in fondo */
            .footer { page-break-before: avoid; }
        }

        /* ============================================
           UTILITY CLASSES
           ============================================ */
        .page-break-before { page-break-before: always; }
        .page-break-after { page-break-after: always; }
        .no-break { page-break-inside: avoid; }
    </style>
</head>
<body>
    <?php
    // URL del logo FTM - usa file locale nella cartella pix del plugin
    $logoUrl = new moodle_url('/local/competencymanager/pix/ftm_logo.png');
    ?>

    <!-- ============================================
         RUNNING HEADER (ripetuto su ogni pagina)
         Con logo FTM
         ============================================ -->
    <div class="running-header">
        <div class="logo-section">
            <div class="logo-box">
                <img src="<?php echo $logoUrl; ?>" alt="FTM Logo">
            </div>
            <span class="student-name"><?php echo fullname($student); ?></span>
        </div>
        <div class="center">
            <strong>SCHEDA COMPETENZE</strong><br>
            <?php echo $course ? format_string($course->shortname) : ''; ?>
        </div>
        <div class="right">
            Score: <strong><?php echo $summary['overall_percentage']; ?>%</strong><br>
            <?php echo date('d/m/Y'); ?>
        </div>
    </div>

    <!-- ============================================
         HEADER PRINCIPALE (prima pagina)
         Con logo FTM grande
         ============================================ -->
    <div class="header">
        <!-- Riga logo e nome organizzazione -->
        <div class="header-logo-row">
            <div class="logo-box">
                <img src="<?php echo $logoUrl; ?>" alt="Fondazione Terzo Millennio">
            </div>
            <div class="org-name">
                <strong>Fondazione Terzo Millennio</strong><br>
                Formazione Professionale
            </div>
        </div>

        <div class="header-row">
            <div class="header-info">
                <h1>SCHEDA VALUTAZIONE COMPETENZE</h1>
                <p><strong>Studente:</strong> <?php echo fullname($student); ?></p>
                <p><strong>Email:</strong> <?php echo $student->email; ?></p>
                <?php if ($course): ?><p><strong>Corso:</strong> <?php echo format_string($course->fullname); ?></p><?php endif; ?>
                <?php if (!empty($printSectorFilter) && $printSectorFilter !== 'all'): ?>
                <p><strong>Settore:</strong> <span style="background: #ffc107; color: #333; padding: 2px 8px; border-radius: 4px;"><?php echo strtoupper($printSectorFilter); ?></span></p>
                <?php endif; ?>
                <p><strong>Data stampa:</strong> <?php echo date('d/m/Y H:i'); ?></p>
            </div>
            <div class="header-score">
                <div class="big"><?php echo $summary['overall_percentage']; ?>%</div>
                <div class="label">Punteggio Globale</div>
            </div>
        </div>
    </div>

    <?php
    // ============================================
    // RENDERING SEZIONI NELL'ORDINE CONFIGURATO
    // ============================================
    // Prepara i parametri per le funzioni di rendering
    $renderParams = [
        'printPanoramica' => $printPanoramica,
        'printProgressi' => $printProgressi,
        'printRadarAree' => $printRadarAree,
        'printRadarAreas' => $printRadarAreas ?? [],
        'printPiano' => $printPiano,
        'printDettagli' => $printDettagli,
        'printDualRadar' => $printDualRadar,
        'printGapAnalysis' => $printGapAnalysis,
        'printSpuntiColloquio' => $printSpuntiColloquio,
        'evaluation' => $evaluation,
        'summary' => $summary,
        'certProgress' => $certProgress,
        'areasData' => $areasData,
        'competencies' => $competencies,
        'competencyDescriptions' => $competencyDescriptions,
        'areaDescriptions' => $areaDescriptions,
        'sector' => $sector,
        'actionPlan' => $actionPlan,
        'autovalutazioneAreas' => $autovalutazioneAreas ?? [],
        'gapAnalysisData' => $gapAnalysisData ?? [],
        'colloquioHints' => $colloquioHints ?? [],
    ];

    // Mappa sezioni -> funzioni di rendering
    $sectionRenderers = [
        'valutazione'    => 'render_section_valutazione',
        'progressi'      => 'render_section_progressi',
        'radar_aree'     => 'render_section_radar_aree',
        'radar_dettagli' => 'render_section_radar_dettagli',
        'piano'          => 'render_section_piano',
        'dettagli'       => 'render_section_dettagli',
        'dual_radar'     => 'render_section_dual_radar',
        'gap_analysis'   => 'render_section_gap_analysis',
        'spunti'         => 'render_section_spunti',
    ];

    // Render sezioni nell'ordine specificato
    foreach ($sectionOrder as $sectionKey => $order) {
        if (isset($sectionRenderers[$sectionKey])) {
            $rendererFn = $sectionRenderers[$sectionKey];
            $rendererFn($renderParams);
        }
    }
    ?>

    <!-- ============================================ -->
    <!-- FINE SEZIONI (ordinate dinamicamente)       -->
    <!-- ============================================ -->

    <!-- ============================================
         FOOTER FINALE
         ============================================ -->
    <div class="footer">
        <div>
            <p><strong>Generato:</strong> <?php echo date('d/m/Y H:i'); ?></p>
            <p><strong>Sistema:</strong> FTM Competency Manager v2.0</p>
        </div>
        <div style="text-align:center;">
            <div class="signature-line">Firma Docente</div>
        </div>
        <div style="text-align:center;">
            <div class="signature-line">Firma Studente</div>
        </div>
    </div>

    <script>
    // ============================================
    // AUTO-PRINT E OTTIMIZZAZIONI
    // ============================================
    window.onload = function() {
        // Attendi il rendering completo prima di stampare
        setTimeout(function() {
            window.print();
        }, 800);
    };

    // Evento before print per ottimizzazioni last-minute
    window.onbeforeprint = function() {
        // Forza ricalcolo layout
        document.body.offsetHeight;
    };
    </script>
</body>
</html>
