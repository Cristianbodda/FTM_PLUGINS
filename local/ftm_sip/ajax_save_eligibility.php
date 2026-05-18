<?php
/**
 * AJAX endpoint for saving SIP eligibility assessment.
 *
 * @package    local_ftm_sip
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/ftm_sip:manage', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);
    $now = time();

    switch ($action) {

        case 'save':
            // Required fields.
            $userid = required_param('userid', PARAM_INT);

            // 3 criteri attivi (1-6). Gli altri vengono passati come 0 per compatibilità DB.
            $autonomia = required_param('autonomia', PARAM_INT);
            $occupabilita = required_param('occupabilita', PARAM_INT);
            $motivazione = required_param('motivazione', PARAM_INT);
            $chiarezza_obiettivo = optional_param('chiarezza_obiettivo', 0, PARAM_INT);
            $bisogno_coaching = optional_param('bisogno_coaching', 0, PARAM_INT);
            $comportamento = optional_param('comportamento', 0, PARAM_INT);

            // Decision field.
            $decisione = required_param('decisione', PARAM_ALPHANUMEXT);

            // Optional fields.
            $coach_recommendation = optional_param('coach_recommendation', '', PARAM_ALPHANUMEXT);
            $referral_detail = optional_param('referral_detail', '', PARAM_TEXT);
            $note = optional_param('note', '', PARAM_TEXT);

            // Validate user exists.
            $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id', MUST_EXIST);

            // Validate i 3 criteri attivi (1-6).
            $criteria_active = [
                'autonomia'   => $autonomia,
                'occupabilita' => $occupabilita,
                'motivazione' => $motivazione,
            ];
            foreach ($criteria_active as $name => $value) {
                if ($value < 1 || $value > 6) {
                    throw new invalid_parameter_exception("Invalid {$name} value: must be between 1 and 6");
                }
            }
            $criteria = array_merge($criteria_active, [
                'chiarezza_obiettivo' => $chiarezza_obiettivo,
                'bisogno_coaching'    => $bisogno_coaching,
                'comportamento'       => $comportamento,
            ]);

            // Calcola totale solo sui 3 criteri attivi (max 18).
            $totale = array_sum($criteria_active);

            // Auto-determine decisione based on totale (semaphore 3-18).
            if ($totale >= 15) {
                $decisione = 'idoneo_prioritario';
            } else if ($totale >= 11) {
                $decisione = 'idoneo';
            } else {
                $decisione = 'non_idoneo';
            }

            // Validate decisione.
            $valid_decisioni = ['idoneo', 'idoneo_prioritario', 'non_idoneo', 'pending'];
            if (!in_array($decisione, $valid_decisioni)) {
                throw new invalid_parameter_exception('Invalid decisione value');
            }

            // Validate coach_recommendation (optional).
            if (!empty($coach_recommendation)) {
                $valid_recommendations = ['activate', 'not_activate', 'refer_other'];
                if (!in_array($coach_recommendation, $valid_recommendations)) {
                    throw new invalid_parameter_exception('Invalid coach_recommendation value');
                }
            }

            // Check if existing record (update) or new (insert).
            $existing = $DB->get_record('local_ftm_sip_eligibility', ['userid' => $userid]);

            $record = new stdClass();
            $record->userid = $userid;
            $record->assessedby = $USER->id;
            $record->motivazione = $motivazione;
            $record->chiarezza_obiettivo = $chiarezza_obiettivo;
            $record->occupabilita = $occupabilita;
            $record->autonomia = $autonomia;
            $record->bisogno_coaching = $bisogno_coaching;
            $record->comportamento = $comportamento;
            $record->totale = $totale;
            $record->decisione = $decisione;
            $record->coach_recommendation = $coach_recommendation ?: null;
            $record->referral_detail = $referral_detail ?: null;
            $record->note = $note ?: null;
            $record->timemodified = $now;

            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('local_ftm_sip_eligibility', $record);
                $eligibilityid = $existing->id;
            } else {
                $record->timecreated = $now;
                $record->approved = 0;
                $eligibilityid = $DB->insert_record('local_ftm_sip_eligibility', $record);
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $eligibilityid,
                    'userid' => $userid,
                    'totale' => $totale,
                    'decisione' => $decisione,
                ],
                'message' => get_string('eligibility_saved', 'local_ftm_sip'),
            ]);
            break;

        case 'approve':
            $eligibilityid = required_param('eligibilityid', PARAM_INT);

            $eligibility = $DB->get_record('local_ftm_sip_eligibility', ['id' => $eligibilityid], '*', MUST_EXIST);

            $eligibility->approved = 1;
            $eligibility->approvedby = $USER->id;
            $eligibility->approved_date = $now;
            $eligibility->timemodified = $now;

            $DB->update_record('local_ftm_sip_eligibility', $eligibility);

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $eligibilityid,
                    'userid' => $eligibility->userid,
                    'approved' => 1,
                ],
                'message' => get_string('eligibility_approved_msg', 'local_ftm_sip'),
            ]);
            break;

        default:
            throw new invalid_parameter_exception('Invalid action: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

die();
