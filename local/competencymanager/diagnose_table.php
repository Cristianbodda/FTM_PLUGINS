<?php
/**
 * Diagnostic script for final_ratings table issues.
 */
require_once(__DIR__ . '/../../config.php');
require_login();
if (!is_siteadmin()) { die('Solo admin'); }

echo '<h2>Diagnosi Tabella Comparativa</h2>';
echo '<pre style="background:#f5f5f5; padding:20px; font-size:14px;">';

// 1. Check table exists.
$dbman = $DB->get_manager();
$exists = $dbman->table_exists('local_compman_final_ratings');
echo "1. Tabella local_compman_final_ratings: " . ($exists ? "ESISTE" : "NON ESISTE") . "\n";

$exists2 = $dbman->table_exists('local_compman_final_history');
echo "2. Tabella local_compman_final_history: " . ($exists2 ? "ESISTE" : "NON ESISTE") . "\n";

// 2. Check plugin version.
$pluginversion = $DB->get_field('config_plugins', 'value', ['plugin' => 'local_competencymanager', 'name' => 'version']);
echo "3. Versione plugin installata: " . ($pluginversion ?: 'NON TROVATA') . "\n";
echo "   Versione richiesta per fix: 2026040701\n";
echo "   Stato: " . ($pluginversion >= 2026040701 ? "OK" : "AGGIORNAMENTO NECESSARIO - vai su /admin/index.php") . "\n";

// 3. Check version.php on disk.
$versionfile = __DIR__ . '/version.php';
if (file_exists($versionfile)) {
    $plugin = new stdClass();
    include($versionfile);
    echo "4. Versione file version.php su disco: " . ($plugin->version ?? 'N/D') . "\n";
} else {
    echo "4. version.php NON TROVATO su disco!\n";
}

if (!$exists) {
    echo "\n*** PROBLEMA: La tabella non esiste. ***\n";
    echo "Soluzione: Vai su /admin/index.php per applicare l'aggiornamento.\n";
    echo "Se non funziona, prova a creare la tabella manualmente:\n\n";

    // Manual SQL.
    echo "CREATE TABLE {local_compman_final_ratings} (\n";
    echo "  id BIGINT AUTO_INCREMENT PRIMARY KEY,\n";
    echo "  studentid BIGINT NOT NULL,\n";
    echo "  courseid BIGINT NOT NULL DEFAULT 0,\n";
    echo "  sector VARCHAR(50) NOT NULL,\n";
    echo "  area_code VARCHAR(20) NOT NULL,\n";
    echo "  method VARCHAR(20) NOT NULL,\n";
    echo "  calculated_value DECIMAL(5,1) DEFAULT NULL,\n";
    echo "  manual_value DECIMAL(5,1) NOT NULL,\n";
    echo "  modifiedby BIGINT NOT NULL,\n";
    echo "  timecreated BIGINT NOT NULL DEFAULT 0,\n";
    echo "  timemodified BIGINT NOT NULL DEFAULT 0,\n";
    echo "  UNIQUE KEY student_sector_area_method_idx (studentid, courseid, sector, area_code, method)\n";
    echo ");\n\n";

    echo "CREATE TABLE {local_compman_final_history} (\n";
    echo "  id BIGINT AUTO_INCREMENT PRIMARY KEY,\n";
    echo "  ratingid BIGINT NOT NULL,\n";
    echo "  old_value DECIMAL(5,1) DEFAULT NULL,\n";
    echo "  new_value DECIMAL(5,1) NOT NULL,\n";
    echo "  modifiedby BIGINT NOT NULL,\n";
    echo "  timecreated BIGINT NOT NULL DEFAULT 0,\n";
    echo "  KEY ratingid_time_idx (ratingid, timecreated)\n";
    echo ");\n";

    echo '</pre>';
    die();
}

// 4. Check field types.
echo "\n5. Struttura campi:\n";
$columns = $DB->get_columns('local_compman_final_ratings');
foreach ($columns as $col) {
    if (in_array($col->name, ['manual_value', 'calculated_value'])) {
        echo "   {$col->name}: type={$col->type}, max_length={$col->max_length}\n";
    }
}

// 5. Count existing records.
$count = $DB->count_records('local_compman_final_ratings');
echo "6. Record salvati nella tabella: {$count}\n";

if ($count > 0) {
    $records = $DB->get_records('local_compman_final_ratings', null, 'timemodified DESC', '*', 0, 10);
    echo "\n7. Ultimi 10 record:\n";
    foreach ($records as $r) {
        echo "   id={$r->id} student={$r->studentid} course={$r->courseid} sector={$r->sector} "
            . "area={$r->area_code} method={$r->method} manual={$r->manual_value} "
            . "calc={$r->calculated_value} by={$r->modifiedby} "
            . "modified=" . date('d/m/Y H:i', $r->timemodified) . "\n";
    }
}

// 6. Test insert.
echo "\n8. Test inserimento/aggiornamento:\n";
try {
    $testrecord = new stdClass();
    $testrecord->studentid = 0;
    $testrecord->courseid = 0;
    $testrecord->sector = 'TEST';
    $testrecord->area_code = 'TEST';
    $testrecord->method = 'test';
    $testrecord->calculated_value = 50.5;
    $testrecord->manual_value = 75.3;
    $testrecord->modifiedby = $USER->id;
    $testrecord->timecreated = time();
    $testrecord->timemodified = time();

    // Try insert.
    $testid = $DB->insert_record('local_compman_final_ratings', $testrecord);
    echo "   INSERT OK (id={$testid})\n";

    // Read back.
    $readback = $DB->get_record('local_compman_final_ratings', ['id' => $testid]);
    echo "   READ BACK: manual_value={$readback->manual_value}, calculated_value={$readback->calculated_value}\n";

    // Delete test record.
    $DB->delete_records('local_compman_final_ratings', ['id' => $testid]);
    echo "   DELETE OK (test record rimosso)\n";
    echo "   RISULTATO: TUTTO FUNZIONA\n";
} catch (Exception $e) {
    echo "   ERRORE: " . $e->getMessage() . "\n";
}

// 7. Check capability.
echo "\n9. Capability check:\n";
$hascap = has_capability('local/competencymanager:evaluate', context_system::instance());
echo "   evaluate: " . ($hascap ? 'SI' : 'NO') . "\n";

echo '</pre>';
// 10. Fix truncated sector names.
echo "\n10. Fix settori troncati:\n";
$broken = $DB->get_records_select('local_compman_final_ratings', "sector = 'ELETTRICIT'");
$count_broken = count($broken);
echo "   Record con sector='ELETTRICIT' (troncato): {$count_broken}\n";
if ($count_broken > 0) {
    $DB->execute("UPDATE {local_compman_final_ratings} SET sector = 'ELETTRICITA' WHERE sector = 'ELETTRICIT'");
    echo "   CORRETTO: {$count_broken} record aggiornati a 'ELETTRICITA'\n";
}

echo '</pre>';
echo '<p><a href="' . $CFG->wwwroot . '/admin/index.php" style="padding:10px 20px; background:#dc3545; color:white; text-decoration:none; border-radius:6px;">Vai a Notifiche Admin</a></p>';
