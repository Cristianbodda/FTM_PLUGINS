<?php
/**
 * One-shot script: crea local_ftm_sip_channel_usage se non esiste.
 * Cancella questo file dal server dopo l'esecuzione.
 *
 * Uso: apri nel browser come admin
 * https://tuoserver/local/ftm_sip/fix_channel_table.php
 */

require_once(__DIR__ . '/../../config.php');

require_login();
if (!is_siteadmin()) {
    die('Solo siteadmin può eseguire questo script.');
}

$dbman = $DB->get_manager();
$table = new xmldb_table('local_ftm_sip_channel_usage');

if ($dbman->table_exists($table)) {
    echo '<p style="color:green;">✅ Tabella <b>local_ftm_sip_channel_usage</b> già esistente. Nessuna azione necessaria.</p>';
} else {
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('enrollmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('channel_key', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
    $table->add_field('sip_week', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
    $table->add_field('activated_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    $table->add_field('activatedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_index('enrollment_channel_uq', XMLDB_INDEX_UNIQUE, ['enrollmentid', 'channel_key']);
    $table->add_index('enrollmentid_week_idx', XMLDB_INDEX_NOTUNIQUE, ['enrollmentid', 'sip_week']);

    $dbman->create_table($table);

    // Aggiorna anche la versione registrata nel DB così i futuri upgrade funzionano.
    set_config('version', 2026060101, 'local_ftm_sip');

    echo '<p style="color:green;">✅ Tabella <b>local_ftm_sip_channel_usage</b> creata con successo.</p>';
    echo '<p style="color:green;">✅ Versione plugin aggiornata a 2026060101.</p>';
}

echo '<p><b>Elimina questo file dal server dopo averlo eseguito.</b></p>';
