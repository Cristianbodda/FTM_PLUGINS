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
 * ============================================
 * 
 * @package    local_competencymanager
 */

defined('MOODLE_INTERNAL') || die();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheda Competenze - <?php echo fullname($student); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10pt; line-height: 1.4; padding: 20px; background: white; color: #333; }
        .header { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white; padding: 20px 25px; margin-bottom: 20px; border-radius: 10px; }
        .header h1 { font-size: 18pt; margin-bottom: 5px; }
        .header-row { display: flex; justify-content: space-between; align-items: center; }
        .header-info p { margin: 3px 0; font-size: 9pt; }
        .header-score { text-align: right; }
        .header-score .big { font-size: 36pt; font-weight: bold; }
        .header-score .label { font-size: 9pt; opacity: 0.9; }
        .section { margin-bottom: 20px; page-break-inside: avoid; }
        .section-title { background: #34495e; color: white; padding: 8px 15px; font-weight: bold; font-size: 12pt; margin-bottom: 10px; border-radius: 5px; }
        .section-title.green { background: #27ae60; }
        .section-title.blue { background: #2980b9; }
        .section-title.orange { background: #e67e22; }
        .section-title.red { background: #c0392b; }
        .section-title.purple { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .section-title.pink { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .section-title.coral { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); color: #333; }
        .evaluation-box { border: 3px solid; padding: 15px; margin-bottom: 20px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
        .evaluation-box .left { flex: 1; }
        .evaluation-box .right { text-align: center; padding: 15px 25px; border-radius: 8px; min-width: 120px; }
        .evaluation-box h3 { font-size: 14pt; margin-bottom: 5px; }
        .evaluation-box p { font-size: 9pt; margin: 3px 0; }
        .evaluation-box .score { font-size: 22pt; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 15px; }
        th, td { border: 1px solid #bdc3c7; padding: 6px 10px; text-align: left; }
        th { background: #ecf0f1; font-weight: 600; }
        .competency-table th { background: #34495e; color: white; }
        .competency-table tr:nth-child(even) { background: #f8f9fa; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 8pt; font-weight: bold; }
        .badge-success { background: #27ae60; color: white; }
        .badge-info { background: #3498db; color: white; }
        .badge-warning { background: #f39c12; color: white; }
        .badge-orange { background: #e67e22; color: white; }
        .badge-danger { background: #c0392b; color: white; }
        .badge-primary { background: #667eea; color: white; }
        .progress-container { margin: 15px 0; }
        .progress-bar { height: 25px; background: #ecf0f1; border-radius: 12px; overflow: hidden; position: relative; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #27ae60, #2ecc71); border-radius: 12px; }
        .progress-text { position: absolute; width: 100%; text-align: center; line-height: 25px; font-weight: bold; font-size: 10pt; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 15px 0; }
        .stat-box { text-align: center; padding: 15px; border-radius: 8px; border: 1px solid #ddd; }
        .stat-box.green { background: #d5f4e6; border-color: #27ae60; }
        .stat-box.yellow { background: #fef9e7; border-color: #f39c12; }
        .stat-box.red { background: #fadbd8; border-color: #c0392b; }
        .stat-number { font-size: 28pt; font-weight: bold; }
        .stat-label { font-size: 8pt; color: #666; }
        .radar-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 30px; margin: 20px 0; }
        
        /* Stili per sezioni additive */
        .dual-radar-container { display: flex; justify-content: space-around; flex-wrap: wrap; gap: 20px; margin: 20px 0; }
        .dual-radar-panel { text-align: center; }
        .dual-radar-panel h4 { margin-bottom: 10px; font-size: 11pt; }
        .gap-row-sopra { background: #fadbd8 !important; }
        .gap-row-sotto { background: #fef9e7 !important; }
        .gap-row-allineato { background: #d5f5e3 !important; }
        .colloquio-hint { background: #f8f9fa; border-left: 4px solid; padding: 10px 15px; margin-bottom: 10px; border-radius: 0 5px 5px 0; }
        .colloquio-hint.critical { border-color: #dc3545; background: #fadbd8; }
        .colloquio-hint.warning { border-color: #f39c12; background: #fef9e7; }
        .colloquio-hint.success { border-color: #28a745; background: #d5f5e3; }
        .notes-box { background: #f8f9fa; border: 2px dashed #6c757d; padding: 15px; border-radius: 8px; margin-top: 20px; }
        .notes-line { border-bottom: 1px solid #dee2e6; min-height: 30px; margin-bottom: 10px; }
        
        .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #34495e; display: flex; justify-content: space-between; font-size: 8pt; color: #666; }
        .signature-line { border-top: 1px solid #333; width: 180px; margin-top: 40px; padding-top: 5px; text-align: center; }
        @media print { body { padding: 10px; } .header, .section-title, .badge, .stat-box, .evaluation-box, .colloquio-hint { -webkit-print-color-adjust: exact; print-color-adjust: exact; } .section { page-break-inside: avoid; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-row">
            <div class="header-info">
                <h1>📋 SCHEDA VALUTAZIONE COMPETENZE</h1>
                <p><strong>Studente:</strong> <?php echo fullname($student); ?></p>
                <p><strong>Email:</strong> <?php echo $student->email; ?></p>
                <?php if ($course): ?><p><strong>Corso:</strong> <?php echo format_string($course->fullname); ?></p><?php endif; ?>
                <p><strong>Data stampa:</strong> <?php echo date('d/m/Y H:i'); ?></p>
            </div>
            <div class="header-score">
                <div class="big"><?php echo $summary['overall_percentage']; ?>%</div>
                <div class="label">Punteggio Globale</div>
            </div>
        </div>
    </div>

    <?php if ($printPanoramica): ?>
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
    <?php endif; ?>

    <?php if ($printProgressi): ?>
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
    <?php endif; ?>

    <?php if ($printRadarAree && !empty($areasData)): ?>
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
    <?php endif; ?>

    <?php if (!empty($printRadarAreas)): ?>
    <?php foreach ($printRadarAreas as $areaCode): ?>
    <?php if (!isset($areasData[$areaCode])) continue; $areaData = $areasData[$areaCode]; $areaCompetencies = [];
    foreach ($areaData['competencies'] as $comp) {
        $code = $comp['idnumber'] ?: $comp['name'];
        $compInfo = $competencyDescriptions[$code] ?? null;
        $displayName = $compInfo ? ($compInfo['name'] ?? $code) : $code;
        $areaCompetencies[] = ['label' => $displayName, 'value' => $comp['percentage']];
    } ?>
    <div class="section">
        <div class="section-title" style="background: <?php echo $areaData['color']; ?>;"><?php echo $areaData['icon']; ?> DETTAGLIO: <?php echo $areaData['name']; ?></div>
        <div class="radar-container"><?php echo generate_svg_radar($areaCompetencies, $areaData['icon'] . ' ' . $areaData['name'] . ' - ' . $areaData['percentage'] . '%', 320, $areaData['color'] . '40', $areaData['color']); ?></div>
        <table><thead><tr><th>Codice</th><th>Competenza</th><th>Risposte</th><th>%</th><th>Valutazione</th></tr></thead><tbody>
        <?php foreach ($areaData['competencies'] as $comp): $code = $comp['idnumber'] ?: $comp['name']; $compInfo = $competencyDescriptions[$code] ?? null; $displayName = $compInfo ? ($compInfo['full_name'] ?? $compInfo['name']) : $code; $band = get_evaluation_band($comp['percentage']); ?>
        <tr><td><small><?php echo $code; ?></small></td><td><?php echo htmlspecialchars($displayName); ?></td><td style="text-align:center;"><?php echo $comp['correct_questions']; ?>/<?php echo $comp['total_questions']; ?></td><td style="text-align:center;"><span class="badge badge-<?php echo $band['class']; ?>"><?php echo $comp['percentage']; ?>%</span></td><td style="color:<?php echo $band['color']; ?>;font-weight:bold;"><?php echo $band['icon'] . ' ' . $band['label']; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($printPiano): ?>
    <?php if (!empty($actionPlan['excellence'])): ?>
    <div class="section"><div class="section-title green">🌟 ECCELLENZA</div><table><thead><tr><th>Competenza</th><th>Stato</th><th>Risultato</th></tr></thead><tbody>
    <?php foreach ($actionPlan['excellence'] as $item): ?><tr style="background:#d5f4e6;"><td><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small><?php echo $item['code']; ?></small></td><td>Certificata</td><td style="text-align:center;"><span class="badge badge-success"><?php echo $item['percentage']; ?>%</span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>

    <?php if (!empty($actionPlan['good'])): ?>
    <div class="section"><div class="section-title blue">✅ ACQUISITE</div><table><thead><tr><th>Competenza</th><th>Azione</th><th>Risultato</th></tr></thead><tbody>
    <?php foreach ($actionPlan['good'] as $item): ?><tr style="background:#d1f2eb;"><td><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small><?php echo $item['code']; ?></small></td><td>Consolidare</td><td style="text-align:center;"><span class="badge badge-info"><?php echo $item['percentage']; ?>%</span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>

    <?php if (!empty($actionPlan['toImprove'])): ?>
    <div class="section"><div class="section-title orange">⚠️ DA MIGLIORARE</div><table><thead><tr><th>Competenza</th><th>Azione</th><th>Risultato</th></tr></thead><tbody>
    <?php foreach ($actionPlan['toImprove'] as $item): ?><tr style="background:#fef9e7;"><td><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small><?php echo $item['code']; ?></small></td><td>Ripasso ed esercizi</td><td style="text-align:center;"><span class="badge badge-warning"><?php echo $item['percentage']; ?>%</span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>

    <?php if (!empty($actionPlan['critical'])): ?>
    <div class="section"><div class="section-title red">🔴 CRITICO</div><table><thead><tr><th>Competenza</th><th>Azione</th><th>Risultato</th></tr></thead><tbody>
    <?php foreach ($actionPlan['critical'] as $item): ?><tr style="background:#fadbd8;"><td><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small><?php echo $item['code']; ?></small></td><td>Formazione base</td><td style="text-align:center;"><span class="badge badge-danger"><?php echo $item['percentage']; ?>%</span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($printDettagli): ?>
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
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- SEZIONI ADDITIVE (CoachManager)             -->
    <!-- ============================================ -->

    <?php if ($printDualRadar && !empty($autovalutazioneAreas)): ?>
    <div class="section" style="page-break-before: always;">
        <div class="section-title purple">🎯 CONFRONTO: AUTOVALUTAZIONE vs PERFORMANCE</div>
        <div class="dual-radar-container">
            <div class="dual-radar-panel">
                <h4 style="color: #667eea;">🧑 Autovalutazione</h4>
                <p style="font-size: 9pt; color: #666; margin-bottom: 10px;">Come lo studente si percepisce</p>
                <?php
                $autoRadarData = [];
                foreach ($autovalutazioneAreas as $code => $area) {
                    $autoRadarData[] = ['label' => $area['icon'] . ' ' . $area['name'], 'value' => $area['percentage']];
                }
                echo generate_svg_radar($autoRadarData, '', 280, 'rgba(102,126,234,0.3)', '#667eea');
                ?>
            </div>
            <div class="dual-radar-panel">
                <h4 style="color: #28a745;">📊 Performance Reale</h4>
                <p style="font-size: 9pt; color: #666; margin-bottom: 10px;">Risultati dai quiz</p>
                <?php
                $perfRadarData = [];
                foreach ($areasData as $code => $area) {
                    $perfRadarData[] = ['label' => $area['icon'] . ' ' . $area['name'], 'value' => $area['percentage']];
                }
                echo generate_svg_radar($perfRadarData, '', 280, 'rgba(40,167,69,0.3)', '#28a745');
                ?>
            </div>
        </div>
        <div style="text-align: center; margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
            <span style="display: inline-block; padding: 5px 15px; background: #667eea; color: white; border-radius: 4px; margin-right: 20px;">🧑 Autovalutazione</span>
            <span style="display: inline-block; padding: 5px 15px; background: #28a745; color: white; border-radius: 4px;">📊 Performance Reale</span>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($printGapAnalysis && !empty($gapAnalysisData)): 
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
    <?php endif; ?>

    <?php if ($printSpuntiColloquio && !empty($colloquioHints)): ?>
    <div class="section" style="page-break-before: always;">
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
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- FINE SEZIONI ADDITIVE                       -->
    <!-- ============================================ -->

    <div class="footer">
        <div><p><strong>Generato:</strong> <?php echo date('d/m/Y H:i'); ?></p><p><strong>Sistema:</strong> Competency Manager</p></div>
        <div style="text-align:center;"><div class="signature-line">Firma Docente</div></div>
        <div style="text-align:center;"><div class="signature-line">Firma Studente</div></div>
    </div>
    <script>window.onload = function() { setTimeout(function() { window.print(); }, 500); };</script>
</body>
</html>
