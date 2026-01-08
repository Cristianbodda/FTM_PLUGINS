<?php
/**
 * Student Print Template - VERSIONE 7
 * Template di stampa separato per student.php
 * Con descrizioni DINAMICHE dal database
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
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10pt; line-height: 1.3; padding: 15px; background: white; }
        .header { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white; padding: 15px 20px; margin-bottom: 15px; border-radius: 8px; }
        .header h1 { font-size: 16pt; margin-bottom: 3px; }
        .header-row { display: flex; justify-content: space-between; align-items: center; }
        .header-info p { margin: 2px 0; font-size: 9pt; }
        .header-score { text-align: right; }
        .header-score .big { font-size: 32pt; font-weight: bold; }
        .header-score .label { font-size: 9pt; opacity: 0.9; }
        .section { margin-bottom: 15px; page-break-inside: avoid; }
        .section-title { background: #34495e; color: white; padding: 6px 12px; font-weight: bold; font-size: 11pt; margin-bottom: 8px; border-radius: 4px; }
        .section-title.green { background: #27ae60; }
        .section-title.orange { background: #e67e22; }
        .section-title.red { background: #c0392b; }
        .section-title.blue { background: #2980b9; }
        .evaluation-box { border: 2px solid; padding: 12px; margin-bottom: 15px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; }
        .evaluation-box .left { flex: 1; }
        .evaluation-box .right { text-align: center; padding: 10px 20px; border-radius: 6px; min-width: 100px; }
        .evaluation-box h3 { font-size: 13pt; margin-bottom: 5px; }
        .evaluation-box p { font-size: 9pt; margin: 2px 0; }
        .evaluation-box .score { font-size: 20pt; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 10px; }
        th, td { border: 1px solid #bdc3c7; padding: 5px 8px; text-align: left; }
        th { background: #ecf0f1; font-weight: 600; }
        .competency-table th { background: #34495e; color: white; }
        .competency-table tr:nth-child(even) { background: #f8f9fa; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 8pt; font-weight: bold; }
        .badge-success { background: #27ae60; color: white; }
        .badge-warning { background: #f39c12; color: white; }
        .badge-danger { background: #c0392b; color: white; }
        .badge-info { background: #3498db; color: white; }
        .progress-container { margin: 10px 0; }
        .progress-bar { height: 20px; background: #ecf0f1; border-radius: 10px; overflow: hidden; position: relative; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #27ae60, #2ecc71); border-radius: 10px; }
        .progress-text { position: absolute; width: 100%; text-align: center; line-height: 20px; font-weight: bold; font-size: 9pt; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 10px 0; }
        .stat-box { text-align: center; padding: 10px; border-radius: 6px; border: 1px solid #ddd; }
        .stat-box.green { background: #d5f4e6; border-color: #27ae60; }
        .stat-box.yellow { background: #fef9e7; border-color: #f39c12; }
        .stat-box.red { background: #fadbd8; border-color: #c0392b; }
        .stat-number { font-size: 24pt; font-weight: bold; }
        .stat-label { font-size: 8pt; color: #666; }
        .radar-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 20px; margin: 15px 0; }
        .radar-item { text-align: center; background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #e0e0e0; }
        .radar-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 15px 0; }
        .footer { margin-top: 20px; padding-top: 10px; border-top: 2px solid #34495e; display: flex; justify-content: space-between; font-size: 8pt; color: #666; }
        .signature-line { border-top: 1px solid #333; width: 200px; margin-top: 30px; padding-top: 5px; }
        @media print { body { padding: 10px; } .header, .section-title, .badge, .stat-box { -webkit-print-color-adjust: exact; print-color-adjust: exact; } .section { page-break-inside: avoid; } }
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
                <p><strong>Data:</strong> <?php echo date('d/m/Y H:i'); ?></p>
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

    <?php if ($printRadarAree && !empty($areasData)): ?>
    <div class="section">
        <div class="section-title" style="background: #667eea;">📊 PANORAMICA PER AREE</div>
        <div class="radar-container">
            <?php
            $radarAreasData = [];
            foreach ($areasData as $code => $areaInfo) {
                $radarAreasData[] = ['label' => $areaInfo['icon'] . ' ' . $areaInfo['name'], 'value' => $areaInfo['percentage']];
            }
            echo generate_svg_radar($radarAreasData, 'Competenze per Area', 350);
            ?>
        </div>
        <div style="display: flex; justify-content: space-around; margin-top: 10px; font-size: 9pt;">
            <div><strong style="color: #27ae60;">✅ Aree forti (≥60%):</strong>
                <?php 
                $strong = array_filter($areasData, fn($a) => $a['percentage'] >= 60);
                echo implode(', ', array_map(fn($a) => $a['icon'] . ' ' . $a['name'] . ' (' . $a['percentage'] . '%)', $strong)) ?: 'Nessuna';
                ?>
            </div>
            <div><strong style="color: #c0392b;">⚠️ Aree critiche (<50%):</strong>
                <?php 
                $weak = array_filter($areasData, fn($a) => $a['percentage'] < 50);
                echo implode(', ', array_map(fn($a) => $a['icon'] . ' ' . $a['name'] . ' (' . $a['percentage'] . '%)', $weak)) ?: 'Nessuna';
                ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($printProgressi): ?>
    <div class="section">
        <div class="section-title blue">📊 PROGRESSO VERSO LA CERTIFICAZIONE</div>
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $certProgress['percentage']; ?>%;"></div>
                <div class="progress-text"><?php echo $certProgress['percentage']; ?>% completato</div>
            </div>
        </div>
        <div class="stats-grid">
            <div class="stat-box green"><div class="stat-number" style="color: #27ae60;"><?php echo $certProgress['certified']; ?></div><div class="stat-label">✅ Certificate (≥80%)</div></div>
            <div class="stat-box yellow"><div class="stat-number" style="color: #b7950b;"><?php echo $certProgress['inProgress']; ?></div><div class="stat-label">🔄 In corso (1-79%)</div></div>
            <div class="stat-box red"><div class="stat-number" style="color: #c0392b;"><?php echo $certProgress['notStarted']; ?></div><div class="stat-label">⏳ Da iniziare (0%)</div></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($printPiano): ?>
    <?php if (!empty($actionPlan['excellence'])): ?>
    <div class="section">
        <div class="section-title green">🌟 ECCELLENZA - Padronanza completa</div>
        <table><thead><tr><th style="width: 55%;">Competenza</th><th style="width: 20%;">Stato</th><th style="width: 25%;">Risultato</th></tr></thead><tbody>
            <?php foreach ($actionPlan['excellence'] as $item): ?>
            <tr style="background: #d5f4e6;"><td><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small style="color: #666;"><?php echo $item['code']; ?></small></td><td>Certificata</td><td style="text-align: center;"><span class="badge badge-success"><?php echo $item['percentage']; ?>%</span></td></tr>
            <?php endforeach; ?>
        </tbody></table>
    </div>
    <?php endif; ?>

    <?php if (!empty($actionPlan['good'])): ?>
    <div class="section">
        <div class="section-title" style="background: #2980b9;">✅ BUONO - Competenze acquisite</div>
        <table><thead><tr><th>Competenza</th><th>Azione</th><th>Risultato</th></tr></thead><tbody>
            <?php foreach ($actionPlan['good'] as $item): ?>
            <tr><td><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small style="color: #666;"><?php echo $item['code']; ?></small></td><td>Consolidare</td><td style="text-align: center;"><span class="badge badge-info"><?php echo $item['percentage']; ?>%</span></td></tr>
            <?php endforeach; ?>
        </tbody></table>
    </div>
    <?php endif; ?>

    <?php if (!empty($actionPlan['toImprove'])): ?>
    <div class="section">
        <div class="section-title orange">⚠️ DA MIGLIORARE - Richiede attenzione</div>
        <table><thead><tr><th>Competenza</th><th>Azione Richiesta</th><th>Risultato</th></tr></thead><tbody>
            <?php foreach ($actionPlan['toImprove'] as $item): ?>
            <tr style="background: #fef9e7;"><td><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small style="color: #666;"><?php echo $item['code']; ?></small></td><td>Ripasso ed esercizi</td><td style="text-align: center;"><span class="badge badge-warning"><?php echo $item['percentage']; ?>%</span></td></tr>
            <?php endforeach; ?>
        </tbody></table>
    </div>
    <?php endif; ?>

    <?php if (!empty($actionPlan['critical'])): ?>
    <div class="section">
        <div class="section-title red">🔴 CRITICO - Formazione urgente</div>
        <table><thead><tr><th>Competenza</th><th>Azione Richiesta</th><th>Risultato</th></tr></thead><tbody>
            <?php foreach ($actionPlan['critical'] as $item): ?>
            <tr style="background: #fadbd8;"><td><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small style="color: #666;"><?php echo $item['code']; ?></small></td><td>Formazione base completa</td><td style="text-align: center;"><span class="badge badge-danger"><?php echo $item['percentage']; ?>%</span></td></tr>
            <?php endforeach; ?>
        </tbody></table>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($printRadarAreas)): ?>
    <div class="section">
        <div class="section-title" style="background: #9b59b6;">🔍 DETTAGLIO PER AREA</div>
        <div class="radar-grid">
            <?php foreach ($printRadarAreas as $areaCode):
                if (!isset($areasData[$areaCode])) continue;
                $areaInfo = $areasData[$areaCode];
                $radarCompData = [];
                foreach ($areaInfo['competencies'] as $comp) {
                    $code = $comp['idnumber'] ?: $comp['name'];
                    $compInfo = $competencyDescriptions[$code] ?? null;
                    $label = $compInfo ? $compInfo['name'] : (!empty($comp['description']) ? $comp['description'] : $code);
                    $radarCompData[] = ['label' => $label, 'value' => $comp['percentage']];
                }
                if (!empty($radarCompData)):
            ?>
            <div class="radar-item"><?php echo generate_svg_radar($radarCompData, $areaInfo['icon'] . ' ' . $areaInfo['name'] . ' (' . $areaInfo['percentage'] . '%)', 280); ?></div>
            <?php endif; endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($printDettagli): ?>
    <div class="section">
        <div class="section-title">📋 DETTAGLIO COMPETENZE</div>
        <table class="competency-table">
            <thead><tr><th style="width: 4%;">#</th><th style="width: 15%;">Codice</th><th style="width: 38%;">Competenza</th><th style="width: 12%;">Risposte</th><th style="width: 10%;">Punteggio</th><th style="width: 21%;">Valutazione</th></tr></thead>
            <tbody>
                <?php $i = 1; foreach ($competencies as $comp):
                    $code = $comp['idnumber'] ?: $comp['name'];
                    $compInfo = $competencyDescriptions[$code] ?? null;
                    $displayName = $compInfo ? ($compInfo['full_name'] ?? $compInfo['name']) : (!empty($comp['description']) ? $comp['description'] : $code);
                    $band = get_evaluation_band($comp['percentage']);
                ?>
                <tr>
                    <td style="text-align: center; font-weight: bold;"><?php echo $i; ?></td>
                    <td><small><?php echo $code; ?></small></td>
                    <td><strong><?php echo htmlspecialchars($displayName); ?></strong></td>
                    <td style="text-align: center;"><?php echo $comp['correct_questions'] . '/' . $comp['total_questions']; ?></td>
                    <td style="text-align: center;"><span class="badge badge-<?php echo $band['class']; ?>"><?php echo $comp['percentage']; ?>%</span></td>
                    <td style="color: <?php echo $band['color']; ?>; font-weight: bold;"><?php echo $band['icon'] . ' ' . $band['label']; ?></td>
                </tr>
                <?php $i++; endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="footer">
        <div><p><strong>Documento generato:</strong> <?php echo date('d/m/Y H:i'); ?></p><p><strong>Sistema:</strong> Report Competenze Moodle v7</p></div>
        <div style="text-align: right;"><div class="signature-line">Firma Docente</div></div>
        <div style="text-align: right;"><div class="signature-line">Firma Studente</div></div>
    </div>
    <script>window.onload = function() { window.print(); };</script>
</body>
</html>
