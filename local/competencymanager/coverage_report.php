<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$courseid = optional_param('courseid', 0, PARAM_INT);
$sector = optional_param('sector', '', PARAM_TEXT);
$tab = optional_param('tab', 'overview', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);

if ($action === 'export' && $sector) {
    export_excel($sector, $courseid);
    exit;
}

function export_excel($sector, $courseid) {
    global $DB;
    $spreadsheet = new Spreadsheet();
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $greenFill = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '90EE90']]];
    $redFill = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FF6B6B']]];
    $yellowFill = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFD700']]];
    $borderStyle = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
    
    $coverage_data = get_coverage_data_for_export($sector, $courseid);
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('RIEPILOGO COPERTURA');
    $sheet1->setCellValue('A1', "ANALISI COPERTURA COMPETENZE $sector");
    $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet1->mergeCells('A1:G1');
    
    $headers = ['Area', 'Nome Area', 'Competenze Totali', 'Competenze Coperte', 'Competenze Mancanti', '% Copertura', 'Stato'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet1->setCellValue($col . '3', $header);
        $sheet1->getStyle($col . '3')->applyFromArray($headerStyle);
        $col++;
    }
    
    $row = 4;
    $totale_comp = 0;
    $totale_coperte = 0;
    foreach ($coverage_data['by_area'] as $area => $data) {
        $sheet1->setCellValue('A' . $row, $area);
        $sheet1->setCellValue('B' . $row, $data['nome']);
        $sheet1->setCellValue('C' . $row, $data['totale']);
        $sheet1->setCellValue('D' . $row, $data['coperte']);
        $sheet1->setCellValue('E' . $row, $data['totale'] - $data['coperte']);
        $percentuale = $data['totale'] > 0 ? round(($data['coperte'] / $data['totale']) * 100) : 0;
        $sheet1->setCellValue('F' . $row, $percentuale . '%');
        $statoCell = $sheet1->getCell('G' . $row);
        if ($percentuale == 100) {
            $statoCell->setValue('COMPLETA');
            $sheet1->getStyle('G' . $row)->applyFromArray($greenFill);
        } elseif ($percentuale >= 50) {
            $statoCell->setValue('PARZIALE');
            $sheet1->getStyle('G' . $row)->applyFromArray($yellowFill);
        } elseif ($percentuale > 0) {
            $statoCell->setValue('BASSA');
            $sheet1->getStyle('G' . $row)->applyFromArray($yellowFill);
        } else {
            $statoCell->setValue('MANCANTE');
            $sheet1->getStyle('G' . $row)->applyFromArray($redFill);
        }
        $sheet1->getStyle("A{$row}:G{$row}")->applyFromArray($borderStyle);
        $totale_comp += $data['totale'];
        $totale_coperte += $data['coperte'];
        $row++;
    }
    $row++;
    $sheet1->setCellValue('A' . $row, 'TOTALE');
    $sheet1->setCellValue('C' . $row, $totale_comp);
    $sheet1->setCellValue('D' . $row, $totale_coperte);
    $sheet1->setCellValue('E' . $row, $totale_comp - $totale_coperte);
    $sheet1->setCellValue('F' . $row, $totale_comp > 0 ? round(($totale_coperte / $totale_comp) * 100) . '%' : '0%');
    $sheet1->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
    $sheet1->getColumnDimension('A')->setWidth(8);
    $sheet1->getColumnDimension('B')->setWidth(35);
    $sheet1->getColumnDimension('C')->setWidth(18);
    $sheet1->getColumnDimension('D')->setWidth(18);
    $sheet1->getColumnDimension('E')->setWidth(18);
    $sheet1->getColumnDimension('F')->setWidth(12);
    $sheet1->getColumnDimension('G')->setWidth(15);

    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('DETTAGLIO COMPETENZE');
    $sheet2->setCellValue('A1', "DETTAGLIO COMPETENZE $sector");
    $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet2->mergeCells('A1:E1');
    $headers2 = ['Area', 'Codice Competenza', 'Profilo', 'Stato', 'Quiz'];
    $col = 'A';
    foreach ($headers2 as $header) {
        $sheet2->setCellValue($col . '3', $header);
        $sheet2->getStyle($col . '3')->applyFromArray($headerStyle);
        $col++;
    }
    $row = 4;
    foreach ($coverage_data['competencies'] as $comp) {
        $sheet2->setCellValue('A' . $row, $comp['area']);
        $sheet2->setCellValue('B' . $row, $comp['idnumber']);
        $sheet2->setCellValue('C' . $row, $comp['profilo']);
        if ($comp['ha_domande']) {
            $sheet2->setCellValue('D' . $row, 'COPERTA');
            $sheet2->getStyle('D' . $row)->applyFromArray($greenFill);
        } else {
            $sheet2->setCellValue('D' . $row, 'MANCANTE');
            $sheet2->getStyle('D' . $row)->applyFromArray($redFill);
        }
        $sheet2->setCellValue('E' . $row, '-');
        $sheet2->getStyle("A{$row}:E{$row}")->applyFromArray($borderStyle);
        $row++;
    }
    $sheet2->getColumnDimension('A')->setWidth(8);
    $sheet2->getColumnDimension('B')->setWidth(30);
    $sheet2->getColumnDimension('C')->setWidth(25);
    $sheet2->getColumnDimension('D')->setWidth(15);
    $sheet2->getColumnDimension('E')->setWidth(30);

    $sheet3 = $spreadsheet->createSheet();
    $sheet3->setTitle('DOMANDE PER QUIZ');
    $sheet3->setCellValue('A1', 'DOMANDE PER QUIZ');
    $sheet3->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $headers3 = ['Quiz', 'Competenza', 'Area', 'N.'];
    $col = 'A';
    foreach ($headers3 as $header) {
        $sheet3->setCellValue($col . '3', $header);
        $sheet3->getStyle($col . '3')->applyFromArray($headerStyle);
        $col++;
    }
    $row = 4;
    foreach ($coverage_data['questions_by_quiz'] as $item) {
        $sheet3->setCellValue('A' . $row, $item['quiz_name']);
        $sheet3->setCellValue('B' . $row, $item['competency']);
        $sheet3->setCellValue('C' . $row, $item['area']);
        $sheet3->setCellValue('D' . $row, $item['count']);
        $sheet3->getStyle("A{$row}:D{$row}")->applyFromArray($borderStyle);
        $row++;
    }
    $sheet3->getColumnDimension('A')->setWidth(40);
    $sheet3->getColumnDimension('B')->setWidth(30);
    $sheet3->getColumnDimension('C')->setWidth(8);
    $sheet3->getColumnDimension('D')->setWidth(8);

    $sheet4 = $spreadsheet->createSheet();
    $sheet4->setTitle('COMPETENZE MANCANTI');
    $sheet4->setCellValue('A1', 'COMPETENZE MANCANTI');
    $sheet4->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FF0000');
    $headers4 = ['Area', 'Codice', 'Profilo'];
    $col = 'A';
    foreach ($headers4 as $header) {
        $sheet4->setCellValue($col . '3', $header);
        $sheet4->getStyle($col . '3')->applyFromArray($headerStyle);
        $sheet4->getStyle($col . '3')->applyFromArray($redFill);
        $col++;
    }
    $row = 4;
    foreach ($coverage_data['missing'] as $comp) {
        $sheet4->setCellValue('A' . $row, $comp['area']);
        $sheet4->setCellValue('B' . $row, $comp['idnumber']);
        $sheet4->getStyle('B' . $row)->applyFromArray($redFill);
        $sheet4->setCellValue('C' . $row, $comp['profilo']);
        $sheet4->getStyle("A{$row}:C{$row}")->applyFromArray($borderStyle);
        $row++;
    }
    $sheet4->getColumnDimension('A')->setWidth(8);
    $sheet4->getColumnDimension('B')->setWidth(35);
    $sheet4->getColumnDimension('C')->setWidth(25);

    $filename = "{$sector}_Copertura_" . date('Ymd') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function get_coverage_data_for_export($sector, $courseid) {
    global $DB;
    $result = ['by_area' => [], 'competencies' => [], 'questions_by_quiz' => [], 'missing' => []];
    $area_names = get_area_names($sector);
    
    $sql = "SELECT MIN(c.id) as id, c.idnumber, MIN(c.shortname) as shortname,
            SUBSTRING_INDEX(SUBSTRING_INDEX(c.idnumber, '_', 2), '_', -1) as profilo,
            SUBSTRING(SUBSTRING_INDEX(c.idnumber, '_', -1), 1, 1) as area
            FROM {competency} c WHERE c.idnumber LIKE :sector AND c.idnumber NOT LIKE 'old%' 
            GROUP BY c.idnumber ORDER BY c.idnumber";
    $competencies = $DB->get_records_sql($sql, ['sector' => $sector . '_%']);
    
    $sql_covered = "SELECT MIN(c.id) as id, c.idnumber FROM {competency} c
                    JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id 
                    WHERE c.idnumber LIKE :sector GROUP BY c.idnumber";
    $covered = $DB->get_records_sql($sql_covered, ['sector' => $sector . '_%']);
    $covered_ids = [];
    foreach ($covered as $c) {
        $covered_ids[] = $c->idnumber;
    }
    
    if ($courseid) {
        $sql_quiz = "SELECT CONCAT(q.id, '_', c.id) as uid, q.name as quiz_name, c.idnumber as competency,
                    SUBSTRING(SUBSTRING_INDEX(c.idnumber, '_', -1), 1, 1) as area, COUNT(DISTINCT qc.questionid) as cnt
                    FROM {quiz} q JOIN {quiz_slots} qs ON qs.quizid = q.id
                    JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                    JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                    JOIN {qbank_competenciesbyquestion} qc ON qc.questionid = qv.questionid
                    JOIN {competency} c ON c.id = qc.competencyid
                    WHERE q.course = :courseid AND c.idnumber LIKE :sector 
                    GROUP BY q.id, q.name, c.id, c.idnumber ORDER BY q.name";
        $quiz_data = $DB->get_records_sql($sql_quiz, ['courseid' => $courseid, 'sector' => $sector . '_%']);
        foreach ($quiz_data as $item) {
            $result['questions_by_quiz'][] = ['quiz_name' => $item->quiz_name, 'competency' => $item->competency, 'area' => $item->area, 'count' => $item->cnt];
        }
    }
    
    $areas_data = [];
    foreach ($competencies as $comp) {
        $area = $comp->area;
        $ha_domande = in_array($comp->idnumber, $covered_ids);
        if (!isset($areas_data[$area])) {
            $areas_data[$area] = ['nome' => $area_names[$area] ?? "Area $area", 'totale' => 0, 'coperte' => 0];
        }
        $areas_data[$area]['totale']++;
        if ($ha_domande) $areas_data[$area]['coperte']++;
        $result['competencies'][] = ['idnumber' => $comp->idnumber, 'profilo' => $comp->profilo, 'area' => $area, 'ha_domande' => $ha_domande];
        if (!$ha_domande) $result['missing'][] = ['idnumber' => $comp->idnumber, 'profilo' => $comp->profilo, 'area' => $area];
    }
    ksort($areas_data);
    $result['by_area'] = $areas_data;
    return $result;
}

function get_area_names($sector) {
    $n = [
        'AUTOMAZIONE' => ['A'=>'A. Pianificazione','B'=>'B. Montaggio','C'=>'C. Cablaggio','D'=>'D. PLC','E'=>'E. Misure','F'=>'F. Reti','G'=>'G. Sicurezza','H'=>'H. Manutenzione'],
        'ELETTRICITA' => ['A'=>'A. Progettazione','B'=>'B. Impianti BT','C'=>'C. Quadri','D'=>'D. Reti MT/BT','E'=>'E. Collaudi','F'=>'F. Norme','G'=>'G. CAD/BIM','H'=>'H. Service'],
        'MECCANICA' => ['A'=>'A. Disegno','B'=>'B. Misure','C'=>'C. Lavorazioni','D'=>'D. CNC','E'=>'E. Materiali','F'=>'F. Assemblaggio','G'=>'G. Processi','H'=>'H. Progettazione'],
        'AUTOMOBILE' => ['A'=>'A. Diagnosi','B'=>'B. Motore','C'=>'C. Lubrificazione','D'=>'D. Scarico','E'=>'E. Trasmissione','F'=>'F. Freni','G'=>'G. Elettronica','H'=>'H. ADAS','I'=>'I. HVAC','J'=>'J. Ibridi','K'=>'K. Carrozzeria','L'=>'L. Sicurezza','M'=>'M. Cliente','N'=>'N. Manutenzione'],
        'LOGISTICA' => ['A'=>'A. Mandati','B'=>'B. Qualita','C'=>'C. Stoccaggio','D'=>'D. Spedizione','E'=>'E. Consulenza','F'=>'F. Recapito','G'=>'G. Magazzino','H'=>'H. Carico'],
        'METALCOSTRUZIONE' => ['A'=>'A. CAD','B'=>'B. Taglio','C'=>'C. Assemblaggio','D'=>'D. Saldatura','E'=>'E. Trattamenti','F'=>'F. Montaggio','G'=>'G. Qualita','H'=>'H. Sicurezza','I'=>'I. CAM','J'=>'J. Robot'],
        'CHIMFARM' => ['A'=>'A. Sicurezza','B'=>'B. Laboratorio','C'=>'C. Analisi','D'=>'D. Produzione','E'=>'E. Qualita','F'=>'F. GMP']
    ];
    return $n[$sector] ?? [];
}

function get_sectors_list() {
    global $DB;
    return $DB->get_records_sql("SELECT SUBSTRING_INDEX(idnumber, '_', 1) as sector, COUNT(DISTINCT idnumber) as unique_competencies
        FROM {competency} WHERE idnumber LIKE '%\\_%\\_%' AND idnumber NOT LIKE 'old%' GROUP BY SUBSTRING_INDEX(idnumber, '_', 1) ORDER BY sector");
}

function get_courses_with_quiz() {
    global $DB;
    return $DB->get_records_sql("SELECT c.id, c.shortname, COUNT(DISTINCT q.id) as num_quiz FROM {course} c
        JOIN {quiz} q ON q.course = c.id WHERE c.id > 1 GROUP BY c.id, c.shortname ORDER BY c.shortname");
}

function detect_sector_from_course($n) {
    $n = strtoupper($n);
    $m = ['ELETTRIC'=>'ELETTRICITA','AUTOMAZ'=>'AUTOMAZIONE','ELETTRON'=>'AUTOMAZIONE','AUTOVEICOLO'=>'AUTOMOBILE','CHIMFARM'=>'CHIMFARM','CHIMICA'=>'CHIMFARM','LOGISTICA'=>'LOGISTICA','MECCANICA'=>'MECCANICA','METAL'=>'METALCOSTRUZIONE','GENERICO'=>'GEN'];
    foreach ($m as $k => $v) if (strpos($n, $k) !== false) return $v;
    return '';
}

function get_coverage_stats($sector, $courseid) {
    global $DB;
    $f = $DB->get_field_sql("SELECT COUNT(DISTINCT idnumber) FROM {competency} WHERE idnumber LIKE :s AND idnumber NOT LIKE 'old%'", ['s' => $sector.'_%']);
    $c = $DB->get_field_sql("SELECT COUNT(DISTINCT c.idnumber) FROM {competency} c JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id WHERE c.idnumber LIKE :s", ['s' => $sector.'_%']);
    $d = $DB->get_field_sql("SELECT COUNT(*) - COUNT(DISTINCT idnumber) FROM {competency} WHERE idnumber LIKE :s AND idnumber NOT LIKE 'old%'", ['s' => $sector.'_%']);
    return ['framework' => (int)$f, 'covered' => (int)$c, 'missing' => (int)$f - (int)$c, 'duplicates' => (int)$d, 'coverage' => $f > 0 ? round(($c/$f)*100,1) : 0];
}

function get_coverage_by_profile($sector) {
    global $DB;
    return $DB->get_records_sql("SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(c.idnumber, '_', 2), '_', -1) as profilo,
        COUNT(DISTINCT c.idnumber) as framework, COUNT(DISTINCT CASE WHEN qc.id IS NOT NULL THEN c.idnumber END) as covered
        FROM {competency} c LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
        WHERE c.idnumber LIKE :s AND c.idnumber NOT LIKE 'old%' GROUP BY profilo ORDER BY profilo", ['s' => $sector.'_%']);
}

function get_missing_competencies($sector) {
    global $DB;
    return $DB->get_records_sql("SELECT MIN(c.id) as id, c.idnumber, MIN(c.shortname) as shortname, SUBSTRING_INDEX(SUBSTRING_INDEX(c.idnumber, '_', 2), '_', -1) as profilo
        FROM {competency} c LEFT JOIN {qbank_competenciesbyquestion} qc ON qc.competencyid = c.id
        WHERE c.idnumber LIKE :s AND c.idnumber NOT LIKE 'old%' AND qc.id IS NULL GROUP BY c.idnumber ORDER BY c.idnumber", ['s' => $sector.'_%']);
}

function get_problems($sector, $courseid) {
    global $DB;
    $p = [];
    $d = $DB->get_field_sql("SELECT COUNT(*) - COUNT(DISTINCT idnumber) FROM {competency} WHERE idnumber LIKE :s AND idnumber NOT LIKE 'old%'", ['s' => $sector.'_%']);
    if ($d > 0) $p[] = ['type' => 'warning', 'title' => 'Duplicati', 'description' => "$d competenze duplicate"];
    $s = get_coverage_stats($sector, $courseid);
    if ($s['missing'] > 0) $p[] = ['type' => 'danger', 'title' => 'Mancanti', 'description' => "{$s['missing']} senza domande"];
    return $p;
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/coverage_report.php'));
$PAGE->set_title('FTM Coverage Manager');
$PAGE->set_heading('FTM Coverage Manager');
$PAGE->set_pagelayout('admin');
echo $OUTPUT->header();

$sectors = get_sectors_list();
$courses = get_courses_with_quiz();
if ($courseid && !$sector) {
    $course = $DB->get_record('course', ['id' => $courseid]);
    if ($course) $sector = detect_sector_from_course($course->shortname);
}
$stats = $profiles = $missing = $problems = null;
if ($sector) {
    $stats = get_coverage_stats($sector, $courseid);
    $profiles = get_coverage_by_profile($sector);
    $missing = get_missing_competencies($sector);
    $problems = get_problems($sector, $courseid);
}
?>
<style>
.stat-box{text-align:center;padding:20px;background:#f8f9fa;border-radius:8px;margin-bottom:15px}
.stat-box .number{font-size:2.5em;font-weight:bold;color:#1e88e5}
.progress-bar-custom{height:25px;border-radius:4px;background:#e9ecef;overflow:hidden}
.progress-bar-custom .fill{height:100%}
</style>
<div class="container-fluid">
<h2>FTM Coverage Manager</h2>
<div class="row mb-4">
<div class="col-md-5">
<label><b>Corso</b></label>
<select class="form-control" onchange="location.href='?courseid='+this.value+'&sector=<?php echo $sector; ?>'">
<option value="">-- Seleziona --</option>
<?php foreach ($courses as $c): ?>
<option value="<?php echo $c->id; ?>" <?php echo $courseid==$c->id?'selected':''; ?>><?php echo $c->shortname; ?> (<?php echo $c->num_quiz; ?>)</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-5">
<label><b>Settore</b></label>
<select class="form-control" onchange="location.href='?courseid=<?php echo $courseid; ?>&sector='+this.value">
<option value="">-- Seleziona --</option>
<?php foreach ($sectors as $s): ?>
<option value="<?php echo $s->sector; ?>" <?php echo $sector==$s->sector?'selected':''; ?>><?php echo $s->sector; ?> (<?php echo $s->unique_competencies; ?>)</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-2">
<?php if ($sector): ?>
<label>&nbsp;</label><br>
<a href="?action=export&sector=<?php echo $sector; ?>&courseid=<?php echo $courseid; ?>" class="btn btn-success">Esporta Excel</a>
<?php endif; ?>
</div>
</div>
<?php if ($sector && $stats): ?>
<ul class="nav nav-tabs mb-3">
<li class="nav-item"><a class="nav-link <?php echo $tab=='overview'?'active':''; ?>" href="?courseid=<?php echo $courseid; ?>&sector=<?php echo $sector; ?>&tab=overview">Panoramica</a></li>
<li class="nav-item"><a class="nav-link <?php echo $tab=='problems'?'active':''; ?>" href="?courseid=<?php echo $courseid; ?>&sector=<?php echo $sector; ?>&tab=problems">Problemi (<?php echo count($problems); ?>)</a></li>
<li class="nav-item"><a class="nav-link <?php echo $tab=='missing'?'active':''; ?>" href="?courseid=<?php echo $courseid; ?>&sector=<?php echo $sector; ?>&tab=missing">Mancanti (<?php echo $stats['missing']; ?>)</a></li>
</ul>
<?php if ($tab == 'overview'): ?>
<div class="row">
<div class="col-md-3"><div class="stat-box"><div class="number"><?php echo $stats['framework']; ?></div><div>Framework</div></div></div>
<div class="col-md-3"><div class="stat-box"><div class="number"><?php echo $stats['coverage']; ?>%</div><div>Copertura</div></div></div>
<div class="col-md-3"><div class="stat-box"><div class="number" style="color:#28a745"><?php echo $stats['covered']; ?></div><div>Coperte</div></div></div>
<div class="col-md-3"><div class="stat-box"><div class="number" style="color:#dc3545"><?php echo $stats['missing']; ?></div><div>Mancanti</div></div></div>
</div>
<div class="card mb-3"><div class="card-header">Copertura <?php echo $sector; ?></div><div class="card-body">
<div class="progress-bar-custom"><div class="fill" style="width:<?php echo $stats['coverage']; ?>%;background:<?php echo $stats['coverage']>=70?'#28a745':($stats['coverage']>=40?'#ffc107':'#dc3545'); ?>"></div></div>
<div class="text-center mt-2"><b><?php echo $stats['covered']; ?>/<?php echo $stats['framework']; ?></b> (<?php echo $stats['coverage']; ?>%)</div>
</div></div>
<div class="card"><div class="card-header">Per Profilo</div><div class="card-body">
<table class="table table-sm"><thead><tr><th>Profilo</th><th>Tot</th><th>Cop</th><th>Gap</th><th>%</th></tr></thead><tbody>
<?php foreach ($profiles as $p): $pct = $p->framework > 0 ? round(($p->covered / $p->framework) * 100) : 0; ?>
<tr><td><b><?php echo $p->profilo; ?></b></td><td><?php echo $p->framework; ?></td><td><?php echo $p->covered; ?></td><td><?php echo $p->framework - $p->covered; ?></td><td><?php echo $pct; ?>%</td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php elseif ($tab == 'problems'): ?>
<div class="card"><div class="card-header">Problemi</div><div class="card-body">
<?php if (empty($problems)): ?><div class="alert alert-success">Nessun problema!</div>
<?php else: foreach ($problems as $p): ?><div class="alert alert-<?php echo $p['type']; ?>"><b><?php echo $p['title']; ?></b>: <?php echo $p['description']; ?></div><?php endforeach; endif; ?>
</div></div>
<?php elseif ($tab == 'missing'): ?>
<div class="card"><div class="card-header">Mancanti (<?php echo count($missing); ?>)</div><div class="card-body">
<table class="table table-sm"><thead><tr><th>Codice</th><th>Profilo</th></tr></thead><tbody>
<?php foreach ($missing as $m): ?><tr><td><code><?php echo $m->idnumber; ?></code></td><td><?php echo $m->profilo; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; endif; ?>
<div class="card mt-3"><div class="card-header">Link</div><div class="card-body">
<a href="../ftm_hub/index.php" class="btn btn-primary">Hub</a>
<a href="simulate_student.php" class="btn btn-success">Simulatore</a>
<?php if ($courseid): ?><a href="reports.php?courseid=<?php echo $courseid; ?>" class="btn btn-info">Report</a><?php endif; ?>
</div></div>
</div>
<?php echo $OUTPUT->footer();
