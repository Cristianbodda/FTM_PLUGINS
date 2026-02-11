<?php
/**
 * Script diagnostico per verificare valutazione coach
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/coach_evaluation_manager.php');
require_once(__DIR__ . '/area_mapping.php');

use local_competencymanager\coach_evaluation_manager;

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/competencymanager/diagnose_coach_eval.php'));
$PAGE->set_title('Diagnosi Valutazione Coach');

$userid = optional_param('userid', 0, PARAM_INT);
$sector = optional_param('sector', 'MECCANICA', PARAM_TEXT);

echo $OUTPUT->header();
echo '<h2>🔍 Diagnosi Valutazione Coach</h2>';

// Form per selezionare utente
echo '<form method="get" class="mb-4">';
echo '<div class="row">';
echo '<div class="col-md-4">';
echo '<label>User ID:</label>';
echo '<input type="number" name="userid" value="' . $userid . '" class="form-control">';
echo '</div>';
echo '<div class="col-md-4">';
echo '<label>Settore:</label>';
echo '<input type="text" name="sector" value="' . s($sector) . '" class="form-control">';
echo '</div>';
echo '<div class="col-md-4 pt-4">';
echo '<button type="submit" class="btn btn-primary mt-2">Analizza</button>';
echo '</div>';
echo '</div>';
echo '</form>';

if ($userid > 0) {
    $user = $DB->get_record('user', ['id' => $userid]);
    if (!$user) {
        echo '<div class="alert alert-danger">Utente non trovato</div>';
        echo $OUTPUT->footer();
        die();
    }

    echo '<h3>Utente: ' . fullname($user) . ' (ID: ' . $userid . ')</h3>';
    echo '<h4>Settore: ' . s($sector) . '</h4>';

    // =============================================
    // 1. Valutazioni in local_coach_evaluations
    // =============================================
    echo '<h3 style="background: #007bff; color: white; padding: 10px; margin-top: 20px;">1. Valutazioni in local_coach_evaluations</h3>';

    $evaluations = $DB->get_records('local_coach_evaluations', [
        'studentid' => $userid,
        'sector' => $sector
    ], 'timecreated DESC');

    if (empty($evaluations)) {
        echo '<div class="alert alert-warning">Nessuna valutazione trovata per questo utente/settore</div>';

        // Prova senza filtro settore
        $allEvaluations = $DB->get_records('local_coach_evaluations', ['studentid' => $userid], 'timecreated DESC');
        if (!empty($allEvaluations)) {
            echo '<p>Ma ci sono valutazioni per altri settori:</p>';
            echo '<ul>';
            foreach ($allEvaluations as $eval) {
                echo '<li>ID: ' . $eval->id . ' | Settore: <strong>' . s($eval->sector) . '</strong> | Status: ' . $eval->status . '</li>';
            }
            echo '</ul>';
        }
    } else {
        echo '<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">';
        echo '<tr style="background: #f0f0f0;"><th>ID</th><th>Coach</th><th>Settore</th><th>Status</th><th>Data</th></tr>';
        foreach ($evaluations as $eval) {
            $coach = $DB->get_record('user', ['id' => $eval->coachid]);
            echo '<tr>';
            echo '<td><strong>' . $eval->id . '</strong></td>';
            echo '<td>' . ($coach ? fullname($coach) : 'N/A') . '</td>';
            echo '<td>' . s($eval->sector) . '</td>';
            echo '<td>' . $eval->status . '</td>';
            echo '<td>' . userdate($eval->timecreated) . '</td>';
            echo '</tr>';
        }
        echo '</table>';

        // =============================================
        // 2. Ratings per ogni valutazione
        // =============================================
        echo '<h3 style="background: #28a745; color: white; padding: 10px; margin-top: 20px;">2. Ratings in local_coach_eval_ratings</h3>';

        foreach ($evaluations as $eval) {
            echo '<h4>Valutazione ID: ' . $eval->id . '</h4>';

            $ratings = $DB->get_records('local_coach_eval_ratings', ['evaluationid' => $eval->id]);

            if (empty($ratings)) {
                echo '<div class="alert alert-danger">❌ NESSUN RATING TROVATO per evaluationid=' . $eval->id . '</div>';
            } else {
                echo '<p>✅ Trovati <strong>' . count($ratings) . '</strong> ratings</p>';

                // Mostra primi 10
                echo '<table border="1" cellpadding="5" style="border-collapse: collapse; font-size: 12px;">';
                echo '<tr style="background: #f0f0f0;"><th>ID</th><th>Competency ID</th><th>Rating</th><th>Notes</th></tr>';
                $count = 0;
                foreach ($ratings as $r) {
                    if ($count++ >= 15) {
                        echo '<tr><td colspan="4">... e altri ' . (count($ratings) - 15) . ' ratings</td></tr>';
                        break;
                    }
                    echo '<tr>';
                    echo '<td>' . $r->id . '</td>';
                    echo '<td>' . $r->competencyid . '</td>';
                    echo '<td><strong>' . $r->rating . '</strong></td>';
                    echo '<td>' . substr($r->notes ?? '', 0, 50) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }

            // Test get_rating_stats
            $stats = coach_evaluation_manager::get_rating_stats($eval->id);
            echo '<p><strong>get_rating_stats(' . $eval->id . '):</strong> ';
            echo 'total=' . $stats['total'] . ', rated=' . $stats['rated'] . ', not_observed=' . $stats['not_observed'] . '</p>';

            // Test calculate_average
            $avg = coach_evaluation_manager::calculate_average($eval->id);
            echo '<p><strong>calculate_average(' . $eval->id . '):</strong> ' . ($avg ?? 'NULL') . '</p>';
        }
    }

    // =============================================
    // 3. Test get_radar_data
    // =============================================
    echo '<h3 style="background: #ffc107; color: black; padding: 10px; margin-top: 20px;">3. get_radar_data(' . $userid . ', \'' . s($sector) . '\')</h3>';

    $radarData = coach_evaluation_manager::get_radar_data($userid, $sector);

    if (empty($radarData)) {
        echo '<div class="alert alert-warning">Nessun dato radar restituito</div>';
    } else {
        echo '<p>✅ Trovati <strong>' . count($radarData) . '</strong> aree nel radar</p>';
        echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
        echo '<tr style="background: #f0f0f0;"><th>Area</th><th>Bloom Avg</th><th>Value (normalized)</th><th>Count</th></tr>';
        foreach ($radarData as $area) {
            echo '<tr>';
            echo '<td>' . $area['area'] . '</td>';
            echo '<td>' . $area['bloom_avg'] . '</td>';
            echo '<td>' . $area['value'] . '%</td>';
            echo '<td>' . ($area['count'] ?? '-') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    // =============================================
    // 4. Verifica competenze nel settore
    // =============================================
    echo '<h3 style="background: #6c757d; color: white; padding: 10px; margin-top: 20px;">4. Competenze del settore ' . s($sector) . '</h3>';

    $competencies = $DB->get_records_sql(
        "SELECT c.id, c.idnumber, c.shortname
         FROM {competency} c
         JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
         WHERE cf.idnumber = 'FTM-01'
           AND c.idnumber LIKE ?
         ORDER BY c.idnumber",
        [$sector . '_%']
    );

    echo '<p>Trovate <strong>' . count($competencies) . '</strong> competenze valutabili con pattern ' . s($sector) . '_*</p>';

    // Mostra solo primi 10
    echo '<ul>';
    $count = 0;
    foreach ($competencies as $c) {
        if ($count++ >= 10) {
            echo '<li>... e altre ' . (count($competencies) - 10) . '</li>';
            break;
        }
        echo '<li><code>' . $c->idnumber . '</code> - ' . substr($c->shortname, 0, 50) . '</li>';
    }
    echo '</ul>';
}

echo $OUTPUT->footer();
