<?php
/**
 * Script per generare dati LabEval automatici
 *
 * Genera sessioni di valutazione laboratorio per utenti test.
 *
 * @package    local_ftm_testsuite
 * @copyright  2026 FTM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/ftm_testsuite:manage', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ftm_testsuite/generate_labeval.php'));
$PAGE->set_title('Genera LabEval - FTM Test Suite');
$PAGE->set_heading('Genera Valutazioni Laboratorio');
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHA);
$templateid = optional_param('templateid', 0, PARAM_INT);
$mode = optional_param('mode', 'test_users', PARAM_ALPHA);

echo $OUTPUT->header();

echo '<div style="max-width: 1200px; margin: 0 auto;">';

// Header
echo '<div style="background: linear-gradient(135deg, #dc3545 0%, #e4606d 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 20px;">';
echo '<h2 style="margin: 0 0 10px 0;">🔬 Generatore LabEval</h2>';
echo '<p style="margin: 0; opacity: 0.9;">Crea sessioni di valutazione laboratorio per gli utenti test.</p>';
echo '</div>';

// Statistiche attuali LabEval
$stats = [
    'templates' => $DB->count_records('local_labeval_templates', ['status' => 'active']),
    'behaviors' => $DB->count_records('local_labeval_behaviors'),
    'sessions' => $DB->count_records('local_labeval_sessions'),
    'completed' => $DB->count_records('local_labeval_sessions', ['status' => 'completed']),
    'ratings' => $DB->count_records('local_labeval_ratings')
];

echo '<div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 25px;">';
foreach ([
    ['Template Attivi', $stats['templates'], '#dc3545'],
    ['Comportamenti', $stats['behaviors'], '#fd7e14'],
    ['Sessioni Tot.', $stats['sessions'], '#17a2b8'],
    ['Completate', $stats['completed'], '#28a745'],
    ['Rating', $stats['ratings'], '#6f42c1']
] as $s) {
    echo "<div style='background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>";
    echo "<div style='font-size: 28px; font-weight: bold; color: {$s[2]};'>{$s[1]}</div>";
    echo "<div style='color: #666; font-size: 12px;'>{$s[0]}</div>";
    echo "</div>";
}
echo '</div>';

// Elenco template disponibili
$templates = $DB->get_records_sql("
    SELECT t.id, t.name, t.sectorcode, t.status, t.timecreated,
           COUNT(DISTINCT b.id) as behavior_count,
           COUNT(DISTINCT bc.id) as comp_mapping_count
    FROM {local_labeval_templates} t
    LEFT JOIN {local_labeval_behaviors} b ON b.templateid = t.id
    LEFT JOIN {local_labeval_behavior_comp} bc ON bc.behaviorid = b.id
    GROUP BY t.id, t.name, t.sectorcode, t.status, t.timecreated
    ORDER BY t.sectorcode, t.name
");

echo '<div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;">';
echo '<h3 style="margin: 0 0 15px 0;">📋 Template Disponibili</h3>';

if (empty($templates)) {
    echo '<div style="padding: 30px; text-align: center; background: #fff3cd; border-radius: 8px;">';
    echo '<div style="font-size: 48px; margin-bottom: 15px;">⚠️</div>';
    echo '<h4>Nessun template LabEval trovato!</h4>';
    echo '<p>Per generare dati LabEval, devi prima creare almeno un template.</p>';
    echo '<a href="../labeval/templates.php" style="display: inline-block; padding: 10px 20px; background: #dc3545; color: white; border-radius: 6px; text-decoration: none;">Vai a LabEval → Template</a>';
    echo '</div>';
} else {
    echo '<table style="width: 100%; border-collapse: collapse;">';
    echo '<thead><tr style="background: #f8f9fa;">';
    echo '<th style="padding: 10px; text-align: left;">Template</th>';
    echo '<th style="padding: 10px; text-align: left;">Settore</th>';
    echo '<th style="padding: 10px; text-align: center;">Comportamenti</th>';
    echo '<th style="padding: 10px; text-align: center;">Mapping Comp.</th>';
    echo '<th style="padding: 10px; text-align: center;">Stato</th>';
    echo '<th style="padding: 10px; text-align: center;">Azione</th>';
    echo '</tr></thead><tbody>';

    foreach ($templates as $t) {
        $status_badge = $t->status === 'active'
            ? '<span style="background: #d4edda; color: #155724; padding: 3px 10px; border-radius: 12px; font-size: 11px;">Attivo</span>'
            : '<span style="background: #f8d7da; color: #721c24; padding: 3px 10px; border-radius: 12px; font-size: 11px;">Inattivo</span>';

        $can_generate = ($t->behavior_count > 0);

        echo '<tr style="border-bottom: 1px solid #eee;">';
        echo "<td style='padding: 10px;'><strong>" . s($t->name) . "</strong></td>";
        echo "<td style='padding: 10px;'><span style='background: #e3f2fd; color: #1565c0; padding: 3px 8px; border-radius: 4px; font-size: 12px;'>{$t->sectorcode}</span></td>";
        echo "<td style='padding: 10px; text-align: center;'>{$t->behavior_count}</td>";
        echo "<td style='padding: 10px; text-align: center;'>{$t->comp_mapping_count}</td>";
        echo "<td style='padding: 10px; text-align: center;'>{$status_badge}</td>";
        echo "<td style='padding: 10px; text-align: center;'>";
        if ($can_generate && $t->status === 'active') {
            echo '<form method="post" style="display: inline;">';
            echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
            echo '<input type="hidden" name="action" value="generate">';
            echo '<input type="hidden" name="templateid" value="' . $t->id . '">';
            echo '<input type="hidden" name="mode" value="' . $mode . '">';
            echo '<button type="submit" style="background: #28a745; color: white; border: none; padding: 6px 15px; border-radius: 4px; cursor: pointer; font-size: 12px;">🚀 Genera</button>';
            echo '</form>';
        } else {
            echo '<span style="color: #999; font-size: 12px;">-</span>';
        }
        echo "</td>";
        echo '</tr>';
    }

    echo '</tbody></table>';
}

echo '</div>';

// Form per generazione batch
if (!empty($templates)) {
    echo '<div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;">';
    echo '<h3 style="margin: 0 0 15px 0;">⚙️ Generazione Batch</h3>';
    echo '<form method="post">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<input type="hidden" name="action" value="generate_all">';

    echo '<div style="margin-bottom: 15px;">';
    echo '<label style="display: block; font-weight: 600; margin-bottom: 5px;">👤 Utenti:</label>';
    echo '<div style="display: flex; gap: 15px;">';
    echo "<label style='display: flex; align-items: center; gap: 8px; padding: 10px 15px; background: #f8f9fa; border-radius: 6px;'>";
    echo "<input type='radio' name='mode' value='test_users' " . ($mode == 'test_users' ? 'checked' : '') . ">";
    echo "<span>🧪 Solo utenti test FTM</span>";
    echo "</label>";
    echo "<label style='display: flex; align-items: center; gap: 8px; padding: 10px 15px; background: #f8f9fa; border-radius: 6px;'>";
    echo "<input type='radio' name='mode' value='all_enrolled' " . ($mode == 'all_enrolled' ? 'checked' : '') . ">";
    echo "<span>👥 Tutti gli iscritti ai corsi</span>";
    echo "</label>";
    echo '</div>';
    echo '</div>';

    echo '<button type="submit" style="background: #dc3545; color: white; padding: 12px 30px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer;">🚀 Genera per TUTTI i Template Attivi</button>';
    echo '</form>';
    echo '</div>';
}

// Esecuzione generazione
if (($action === 'generate' || $action === 'generate_all') && confirm_sesskey()) {
    echo '<div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">';
    echo '<h3 style="margin: 0 0 15px 0;">📋 Log Generazione</h3>';
    echo '<div style="background: #1e1e1e; color: #fff; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 13px; max-height: 500px; overflow-y: auto;">';

    $log = [];
    $created_sessions = 0;
    $created_ratings = 0;
    $skipped = 0;

    // Determina utenti
    if ($mode === 'test_users') {
        $users = $DB->get_records_sql("
            SELECT tu.userid, u.username, tu.testprofile, tu.quiz_percentage
            FROM {local_ftm_testsuite_users} tu
            JOIN {user} u ON u.id = tu.userid
        ");
        $log[] = "<span style='color: #ffc107;'>⚡ Modalità: Solo utenti test FTM (" . count($users) . " utenti)</span>";
    } else {
        // Tutti gli iscritti ai corsi
        $users = $DB->get_records_sql("
            SELECT DISTINCT u.id as userid, u.username, 'real' as testprofile, 50 as quiz_percentage
            FROM {user} u
            JOIN {user_enrolments} ue ON ue.userid = u.id
            JOIN {enrol} e ON e.id = ue.enrolid
            WHERE e.courseid > 1 AND ue.status = 0
            LIMIT 50
        ");
        $log[] = "<span style='color: #ffc107;'>⚡ Modalità: Utenti iscritti ai corsi (" . count($users) . " utenti)</span>";
    }

    // Determina template
    if ($action === 'generate' && $templateid > 0) {
        $templates_to_process = [$DB->get_record('local_labeval_templates', ['id' => $templateid])];
    } else {
        $templates_to_process = $DB->get_records('local_labeval_templates', ['status' => 'active']);
    }

    $log[] = "<span style='color: #17a2b8;'>📋 Template da processare: " . count($templates_to_process) . "</span>";
    $log[] = "";

    foreach ($templates_to_process as $template) {
        $log[] = "<span style='color: #dc3545;'>═══ Template: " . s($template->name) . " ({$template->sectorcode}) ═══</span>";

        // Ottieni comportamenti
        $behaviors = $DB->get_records('local_labeval_behaviors', ['templateid' => $template->id], 'sortorder');

        if (empty($behaviors)) {
            $log[] = "   <span style='color: #999;'>⚠️ Nessun comportamento definito - skip</span>";
            continue;
        }

        // Ottieni mapping comportamenti -> competenze
        $behavior_comps = [];
        foreach ($behaviors as $b) {
            $comps = $DB->get_records('local_labeval_behavior_comp', ['behaviorid' => $b->id]);
            $behavior_comps[$b->id] = $comps;
        }

        foreach ($users as $user) {
            // Verifica se esiste già sessione completata per questo utente/template
            $existing = $DB->get_record_sql("
                SELECT s.id FROM {local_labeval_sessions} s
                JOIN {local_labeval_assignments} a ON a.id = s.assignmentid
                WHERE a.templateid = ? AND a.studentid = ? AND s.status = 'completed'
            ", [$template->id, $user->userid]);

            if ($existing) {
                $skipped++;
                continue;
            }

            // Determina target percentage
            $target_pct = isset($user->quiz_percentage) ? $user->quiz_percentage / 100 : 0.5;

            // Trova un corso pertinente (dal settore)
            $course = $DB->get_record_sql("
                SELECT c.id FROM {course} c
                WHERE LOWER(c.fullname) LIKE LOWER(?)
                   OR LOWER(c.shortname) LIKE LOWER(?)
                LIMIT 1
            ", ['%' . strtolower($template->sectorcode) . '%', '%' . strtolower($template->sectorcode) . '%']);

            $courseid = $course ? $course->id : 2; // Default al primo corso

            // Crea assignment
            $assignment = new stdClass();
            $assignment->templateid = $template->id;
            $assignment->studentid = $user->userid;
            $assignment->assignedby = $USER->id;
            $assignment->courseid = $courseid;
            $assignment->status = 'completed';
            $assignment->timecreated = time();
            $assignment->timemodified = time();
            $assignmentid = $DB->insert_record('local_labeval_assignments', $assignment);

            // Crea session
            $session = new stdClass();
            $session->assignmentid = $assignmentid;
            $session->assessorid = $USER->id;
            $session->status = 'completed';
            $session->notes = 'Sessione generata automaticamente da FTM Test Suite';
            $session->timecreated = time();
            $session->timecompleted = time();
            $sessionid = $DB->insert_record('local_labeval_sessions', $session);

            $total_score = 0;
            $max_score = 0;
            $rating_count = 0;

            // Genera ratings
            foreach ($behaviors as $b) {
                $comps = $behavior_comps[$b->id] ?? [];

                if (empty($comps)) {
                    // Rating legacy senza competencycode
                    $rand = mt_rand(1, 100) / 100;
                    if ($rand <= $target_pct * 0.8) {
                        $rating = 3;
                    } elseif ($rand <= $target_pct * 1.2) {
                        $rating = 1;
                    } else {
                        $rating = 0;
                    }

                    $r = new stdClass();
                    $r->sessionid = $sessionid;
                    $r->behaviorid = $b->id;
                    $r->competencycode = null;
                    $r->rating = $rating;
                    $r->notes = '';
                    $DB->insert_record('local_labeval_ratings', $r);
                    $created_ratings++;
                    $rating_count++;

                    $total_score += $rating;
                    $max_score += 3;
                } else {
                    // Rating per ogni competenza associata
                    foreach ($comps as $comp) {
                        $rand = mt_rand(1, 100) / 100;
                        $weight_bonus = ($comp->weight == 3) ? 0.1 : 0;

                        if ($rand <= ($target_pct * 0.8) + $weight_bonus) {
                            $rating = 3;
                        } elseif ($rand <= ($target_pct * 1.2) + $weight_bonus) {
                            $rating = 1;
                        } else {
                            $rating = 0;
                        }

                        $r = new stdClass();
                        $r->sessionid = $sessionid;
                        $r->behaviorid = $b->id;
                        $r->competencycode = $comp->competencycode;
                        $r->rating = $rating;
                        $r->notes = '';
                        $DB->insert_record('local_labeval_ratings', $r);
                        $created_ratings++;
                        $rating_count++;

                        $total_score += ($rating * $comp->weight);
                        $max_score += (3 * $comp->weight);
                    }
                }
            }

            // Aggiorna session con punteggi
            $percentage = $max_score > 0 ? round(($total_score / $max_score) * 100, 2) : 0;
            $DB->update_record('local_labeval_sessions', (object)[
                'id' => $sessionid,
                'totalscore' => $total_score,
                'maxscore' => $max_score,
                'percentage' => $percentage
            ]);

            // Aggiorna comp_scores cache
            update_labeval_comp_scores($sessionid);

            $created_sessions++;
            $log[] = "   <span style='color: #28a745;'>✅ {$user->username}: {$rating_count} ratings, {$percentage}%</span>";
        }
    }

    $log[] = "";
    $log[] = "<span style='color: #ffc107;'>═══════════════════════════════════════</span>";
    $log[] = "<span style='color: #28a745;'>✅ RIEPILOGO:</span>";
    $log[] = "   • Sessioni create: <strong>{$created_sessions}</strong>";
    $log[] = "   • Rating creati: <strong>{$created_ratings}</strong>";
    $log[] = "   • Saltate (già esistenti): <strong>{$skipped}</strong>";

    foreach ($log as $l) {
        echo $l . "<br>";
    }

    echo '</div>';
    echo '</div>';
}

/**
 * Aggiorna la cache comp_scores per una sessione
 */
function update_labeval_comp_scores($sessionid) {
    global $DB;

    // Raggruppa per competencycode
    $scores = $DB->get_records_sql("
        SELECT r.competencycode,
               SUM(r.rating) as score,
               SUM(3) as maxscore,
               COUNT(*) as rating_count
        FROM {local_labeval_ratings} r
        WHERE r.sessionid = ?
          AND r.competencycode IS NOT NULL
          AND r.competencycode != ''
        GROUP BY r.competencycode
    ", [$sessionid]);

    foreach ($scores as $s) {
        $percentage = $s->maxscore > 0 ? round(($s->score / $s->maxscore) * 100, 2) : 0;

        // Trova competencyid (usa LIMIT 1 per evitare errori con duplicati)
        $comp = $DB->get_record_sql("SELECT id FROM {competency} WHERE idnumber = ? LIMIT 1", [$s->competencycode]);
        $competencyid = $comp ? $comp->id : null;

        $existing = $DB->get_record('local_labeval_comp_scores', [
            'sessionid' => $sessionid,
            'competencycode' => $s->competencycode
        ]);

        if ($existing) {
            $existing->score = $s->score;
            $existing->maxscore = $s->maxscore;
            $existing->percentage = $percentage;
            $existing->competencyid = $competencyid;
            $DB->update_record('local_labeval_comp_scores', $existing);
        } else {
            $cs = new stdClass();
            $cs->sessionid = $sessionid;
            $cs->competencyid = $competencyid;
            $cs->competencycode = $s->competencycode;
            $cs->score = $s->score;
            $cs->maxscore = $s->maxscore;
            $cs->percentage = $percentage;
            $DB->insert_record('local_labeval_comp_scores', $cs);
        }
    }
}

echo '</div>';

// Link ritorno
echo '<p style="margin-top: 20px;">';
echo '<a href="index.php" style="color: #1e3c72;">← Torna alla Dashboard</a> | ';
echo '<a href="run.php" style="color: #1e3c72;">Esegui Test Suite</a>';
echo '</p>';

echo $OUTPUT->footer();
