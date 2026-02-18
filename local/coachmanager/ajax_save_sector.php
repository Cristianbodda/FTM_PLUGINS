<?php
// AJAX endpoint to save student sectors (multi-select, max 3) from coach dashboard.
// Accepts: userid, sectors (comma-separated ordered list, e.g. "MECCANICA,AUTOMOBILE,GEN")
// First sector = primary, second = secondary, third = tertiary.
require_once(__DIR__ . '/../../config.php');
require_login();
require_sesskey();

$userid = required_param('userid', PARAM_INT);
$sectors_raw = required_param('sectors', PARAM_TEXT);

header('Content-Type: application/json');

try {
    // Parse and validate sectors (max 3).
    $sectors = array_filter(array_map('trim', explode(',', $sectors_raw)));
    $sectors = array_map('strtoupper', $sectors);
    $sectors = array_unique($sectors);
    $sectors = array_slice($sectors, 0, 3); // Max 3

    if (empty($sectors)) {
        throw new Exception('Nessun settore selezionato');
    }

    // Verify the coach has this student assigned.
    $coaching = $DB->get_record('local_student_coaching', [
        'userid' => $userid,
        'coachid' => $USER->id,
        'status' => 'active'
    ]);
    if (!$coaching) {
        throw new Exception('Studente non assegnato a te');
    }

    $now = time();
    $dbman = $DB->get_manager();
    $primary = $sectors[0]; // First = primary

    // Update local_student_sectors table.
    if ($dbman->table_exists('local_student_sectors')) {
        // Remove all manual entries for this user.
        $DB->delete_records_select(
            'local_student_sectors',
            "userid = ? AND source = 'manual'",
            [$userid]
        );

        // Also clear is_primary from any remaining quiz-detected entries.
        $DB->execute(
            "UPDATE {local_student_sectors} SET is_primary = 0, timemodified = ? WHERE userid = ?",
            [$now, $userid]
        );

        // Insert each sector in order.
        foreach ($sectors as $idx => $sector) {
            // Check if this sector already exists (from quiz detection).
            $existing = $DB->get_record('local_student_sectors', [
                'userid' => $userid,
                'sector' => $sector,
                'courseid' => 0
            ]);

            if ($existing) {
                // Update existing: set primary flag and source.
                $existing->is_primary = ($idx === 0) ? 1 : 0;
                $existing->source = 'manual';
                $existing->timemodified = $now;
                $DB->update_record('local_student_sectors', $existing);
            } else {
                // Insert new.
                $record = new stdClass();
                $record->userid = $userid;
                $record->courseid = 0;
                $record->sector = $sector;
                $record->is_primary = ($idx === 0) ? 1 : 0;
                $record->source = 'manual';
                $record->quiz_count = 0;
                $record->first_detected = $now;
                $record->last_detected = $now;
                $record->timecreated = $now;
                $record->timemodified = $now;
                $DB->insert_record('local_student_sectors', $record);
            }
        }
    }

    // Sync primary to local_student_coaching.
    $coaching->sector = $primary;
    $coaching->timemodified = $now;
    $DB->update_record('local_student_coaching', $coaching);

    // Sync to cpurc if table exists.
    if ($dbman->table_exists('local_ftm_cpurc_students')) {
        $cpurc = $DB->get_record('local_ftm_cpurc_students', ['userid' => $userid]);
        if ($cpurc) {
            $cpurc->sector_detected = $primary;
            $cpurc->timemodified = $now;
            $DB->update_record('local_ftm_cpurc_students', $cpurc);
        }
    }

    echo json_encode([
        'success' => true,
        'sectors' => array_values($sectors),
        'primary' => $primary
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
