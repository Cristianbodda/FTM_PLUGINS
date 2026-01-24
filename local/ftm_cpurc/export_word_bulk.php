<?php
// This file is part of Moodle - http://moodle.org/
//
// Export all reports to Word documents in a ZIP archive.
//
// @package    local_ftm_cpurc
// @copyright  2026 Fondazione Terzo Millennio
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ftm_cpurc/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/ftm_cpurc:generatereport', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ftm_cpurc/export_word_bulk.php'));
$PAGE->set_title('Export Word Massivo');
$PAGE->set_heading('Export Word Massivo');

// Check if action is requested.
$action = optional_param('action', '', PARAM_ALPHA);
$reportstatus = optional_param('reportstatus', 'all', PARAM_ALPHA);

if ($action === 'download') {
    require_sesskey();

    // Get students with reports.
    $students = \local_ftm_cpurc\cpurc_manager::get_students_with_reports();

    if (empty($students)) {
        redirect(
            new moodle_url('/local/ftm_cpurc/export_word_bulk.php'),
            'Nessun report da esportare.',
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }

    // Create temp directory.
    $tempdir = make_temp_directory('cpurc_export_' . time());
    $generatedFiles = [];
    $errors = [];

    foreach ($students as $student) {
        try {
            // Get full student data.
            $fullstudent = \local_ftm_cpurc\cpurc_manager::get_student($student->id);
            $report = \local_ftm_cpurc\cpurc_manager::get_report($student->id);

            if (!$fullstudent || !$report) {
                continue;
            }

            // Filter by report status if needed.
            if ($reportstatus === 'final' && $report->status !== 'final' && $report->status !== 'sent') {
                continue;
            }
            if ($reportstatus === 'draft' && $report->status !== 'draft') {
                continue;
            }

            // Generate Word document.
            $exporter = new \local_ftm_cpurc\word_exporter($fullstudent, $report);

            // Create safe filename.
            $name = $fullstudent->lastname . '_' . $fullstudent->firstname;
            $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
            $filename = 'Rapporto_' . $name . '.docx';
            $filepath = $tempdir . '/' . $filename;

            $exporter->saveToFile($filepath);
            $generatedFiles[] = $filepath;

        } catch (Exception $e) {
            $errors[] = $student->lastname . ' ' . $student->firstname . ': ' . $e->getMessage();
        }
    }

    if (empty($generatedFiles)) {
        redirect(
            new moodle_url('/local/ftm_cpurc/export_word_bulk.php'),
            'Nessun file generato.' . (!empty($errors) ? ' Errori: ' . implode('; ', $errors) : ''),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    // Create ZIP archive.
    $zipfilename = 'Rapporti_CPURC_' . date('Y-m-d_His') . '.zip';
    $zipfilepath = $tempdir . '/' . $zipfilename;

    $zip = new ZipArchive();
    if ($zip->open($zipfilepath, ZipArchive::CREATE) !== true) {
        redirect(
            new moodle_url('/local/ftm_cpurc/export_word_bulk.php'),
            'Impossibile creare archivio ZIP.',
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    foreach ($generatedFiles as $file) {
        $zip->addFile($file, basename($file));
    }
    $zip->close();

    // Send ZIP file.
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipfilename . '"');
    header('Content-Length: ' . filesize($zipfilepath));
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($zipfilepath);

    // Cleanup.
    foreach ($generatedFiles as $file) {
        @unlink($file);
    }
    @unlink($zipfilepath);
    @rmdir($tempdir);

    exit;
}

// Show interface.
echo $OUTPUT->header();

// Get stats.
$allWithReports = \local_ftm_cpurc\cpurc_manager::get_students_with_reports();
$draftCount = 0;
$finalCount = 0;

foreach ($allWithReports as $s) {
    $report = \local_ftm_cpurc\cpurc_manager::get_report($s->id);
    if ($report) {
        if ($report->status === 'draft') {
            $draftCount++;
        } else {
            $finalCount++;
        }
    }
}

$totalCount = count($allWithReports);
?>

<style>
.export-container {
    max-width: 800px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.export-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 20px;
}

.export-card h2 {
    margin: 0 0 20px 0;
    font-size: 22px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-box {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.stat-box.green { border-left: 4px solid #28a745; }
.stat-box.yellow { border-left: 4px solid #EAB308; }
.stat-box.blue { border-left: 4px solid #0066cc; }

.stat-number {
    font-size: 36px;
    font-weight: 700;
}

.stat-label {
    font-size: 13px;
    color: #666;
    margin-top: 5px;
}

.export-options {
    margin: 25px 0;
}

.export-options label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}

.export-options select {
    padding: 10px 15px;
    font-size: 15px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    min-width: 250px;
}

.export-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 15px 30px;
    background: #28a745;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
}

.export-btn:hover {
    background: #218838;
    color: white;
    text-decoration: none;
}

.export-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.back-link {
    display: inline-block;
    margin-top: 20px;
    color: #0066cc;
}
</style>

<div class="export-container">
    <div class="export-card">
        <h2>📦 Export Word Massivo</h2>
        <p>Genera un archivio ZIP contenente i report Word di tutti gli studenti.</p>

        <div class="stats-grid">
            <div class="stat-box green">
                <div class="stat-number"><?php echo $finalCount; ?></div>
                <div class="stat-label">🟢 Report Completi</div>
            </div>
            <div class="stat-box yellow">
                <div class="stat-number"><?php echo $draftCount; ?></div>
                <div class="stat-label">🟡 Report Bozza</div>
            </div>
            <div class="stat-box blue">
                <div class="stat-number"><?php echo $totalCount; ?></div>
                <div class="stat-label">📄 Totale Report</div>
            </div>
        </div>

        <?php if ($totalCount > 0): ?>
        <form method="get" action="">
            <input type="hidden" name="action" value="download">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

            <div class="export-options">
                <label for="reportstatus">Quali report esportare?</label>
                <select name="reportstatus" id="reportstatus">
                    <option value="all">Tutti (<?php echo $totalCount; ?> report)</option>
                    <option value="final">Solo completi (<?php echo $finalCount; ?> report)</option>
                    <option value="draft">Solo bozze (<?php echo $draftCount; ?> report)</option>
                </select>
            </div>

            <button type="submit" class="export-btn">
                📦 Genera ZIP e Scarica
            </button>
        </form>
        <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <div style="font-size: 48px; margin-bottom: 15px;">📭</div>
            <p>Nessun report disponibile per l'esportazione.</p>
            <p>I coach devono prima compilare i report degli studenti.</p>
        </div>
        <?php endif; ?>

        <a href="<?php echo new moodle_url('/local/ftm_cpurc/index.php'); ?>" class="back-link">
            ← Torna alla Dashboard
        </a>
    </div>
</div>

<?php
echo $OUTPUT->footer();
