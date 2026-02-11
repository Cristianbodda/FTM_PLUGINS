<?php
/**
 * Script per testare valutazione coach su studenti MECCANICA
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/area_mapping.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/test_coach_meccanica.php'));
$PAGE->set_title('Test Coach Valutazione MECCANICA');

echo $OUTPUT->header();
echo '<h2>🔧 Test Valutazione Coach - Settore MECCANICA</h2>';

// =============================================
// SEZIONE 1: Studenti con settore MECCANICA
// =============================================
echo '<h3 style="background: #6c757d; color: white; padding: 10px;">1. Studenti con Settore MECCANICA Assegnato</h3>';

$meccanicaStudents = $DB->get_records_sql(
    "SELECT ss.id, ss.userid, ss.sector, ss.is_primary, ss.courseid,
            u.firstname, u.lastname, u.email
     FROM {local_student_sectors} ss
     JOIN {user} u ON u.id = ss.userid
     WHERE UPPER(ss.sector) = 'MECCANICA'
     ORDER BY ss.is_primary DESC, u.lastname, u.firstname"
);

if (empty($meccanicaStudents)) {
    echo '<div style="background: #fff3cd; padding: 15px; border-radius: 5px;">';
    echo '<strong>⚠️ Nessuno studente ha il settore MECCANICA assegnato.</strong><br>';
    echo 'Vai al Student Report e assegna MECCANICA come settore primario a uno studente.';
    echo '</div>';
} else {
    echo '<p>Trovati <strong>' . count($meccanicaStudents) . '</strong> studenti con MECCANICA</p>';
    echo '<table border="1" cellpadding="8" style="border-collapse: collapse; width: 100%;">';
    echo '<tr style="background: #f0f0f0;">';
    echo '<th>Studente</th><th>Email</th><th>Tipo</th><th>Corso</th><th>Azioni</th>';
    echo '</tr>';

    foreach ($meccanicaStudents as $student) {
        $tipo = $student->is_primary ? '🥇 Primario' : '🥈🥉 Secondario';
        $tipoStyle = $student->is_primary ? 'background: #d4edda;' : '';

        // Trova un corso valido per lo studente
        $courseid = $student->courseid;
        if (empty($courseid)) {
            // Cerca un corso dove lo studente è iscritto
            $enrollment = $DB->get_record_sql(
                "SELECT c.id, c.fullname
                 FROM {course} c
                 JOIN {enrol} e ON e.courseid = c.id
                 JOIN {user_enrolments} ue ON ue.enrolid = e.id
                 WHERE ue.userid = ? AND c.id > 1
                 LIMIT 1",
                [$student->userid]
            );
            $courseid = $enrollment ? $enrollment->id : 2; // Default a corso 2
        }

        $evalUrl = new moodle_url('/local/competencymanager/coach_evaluation.php', [
            'userid' => $student->userid,
            'courseid' => $courseid,
            'sector' => 'MECCANICA'
        ]);

        $reportUrl = new moodle_url('/local/competencymanager/student_report.php', [
            'userid' => $student->userid,
            'courseid' => $courseid
        ]);

        echo "<tr style='$tipoStyle'>";
        echo "<td><strong>{$student->firstname} {$student->lastname}</strong><br><small>ID: {$student->userid}</small></td>";
        echo "<td>{$student->email}</td>";
        echo "<td>{$tipo}</td>";
        echo "<td>ID: {$courseid}</td>";
        echo "<td>";
        echo "<a href='{$evalUrl}' style='background: #007bff; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; margin-right: 5px;'>📝 Valuta MECCANICA</a> ";
        echo "<a href='{$reportUrl}' style='background: #28a745; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none;'>📊 Report</a>";
        echo "</td>";
        echo "</tr>";
    }
    echo '</table>';
}

// =============================================
// SEZIONE 2: Competenze MECCANICA disponibili per valutazione
// =============================================
echo '<h3 style="background: #28a745; color: white; padding: 10px; margin-top: 30px;">2. Competenze MECCANICA Disponibili per Valutazione</h3>';

$meccanicaComps = $DB->get_records_sql(
    "SELECT c.id, c.idnumber, c.shortname, c.description
     FROM {competency} c
     JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
     WHERE cf.idnumber = 'FTM-01'
       AND c.idnumber LIKE 'MECCANICA_%'
     ORDER BY c.idnumber"
);

echo '<p>Trovate <strong>' . count($meccanicaComps) . '</strong> competenze MECCANICA valutabili</p>';

// Raggruppa per area
$areas = [];
foreach ($meccanicaComps as $comp) {
    $areaInfo = get_area_info($comp->idnumber);
    $areaCode = $areaInfo['code'];
    if (!isset($areas[$areaCode])) {
        $areas[$areaCode] = [
            'name' => $areaInfo['name'],
            'competencies' => []
        ];
    }
    $areas[$areaCode]['competencies'][] = $comp;
}

echo '<table border="1" cellpadding="5" style="border-collapse: collapse; font-size: 12px;">';
echo '<tr style="background: #f0f0f0;"><th>Area</th><th>Nome Area</th><th>Competenze</th></tr>';

foreach ($areas as $code => $area) {
    echo "<tr>";
    echo "<td><strong>{$code}</strong></td>";
    echo "<td>{$area['name']}</td>";
    echo "<td>" . count($area['competencies']) . " competenze</td>";
    echo "</tr>";
}
echo '</table>';

// =============================================
// SEZIONE 3: Link rapido per test manuale
// =============================================
echo '<h3 style="background: #ffc107; color: black; padding: 10px; margin-top: 30px;">3. Test Manuale</h3>';

echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">';
echo '<p>Se vuoi testare manualmente, usa questo URL (sostituisci i parametri):</p>';
echo '<code style="background: #e9ecef; padding: 10px; display: block; margin: 10px 0;">';
echo '/local/competencymanager/coach_evaluation.php?userid=XXX&courseid=YYY&sector=MECCANICA';
echo '</code>';
echo '<p><strong>Parametri:</strong></p>';
echo '<ul>';
echo '<li><code>userid</code> = ID dello studente da valutare</li>';
echo '<li><code>courseid</code> = ID del corso</li>';
echo '<li><code>sector</code> = MECCANICA (o altro settore)</li>';
echo '</ul>';
echo '</div>';

echo $OUTPUT->footer();
