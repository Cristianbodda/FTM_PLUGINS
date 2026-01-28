<?php
/**
 * Impostazioni plugin FTM AI
 *
 * @package    local_ftm_ai
 * @copyright  2026 Fondazione Terzo Millennio
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_ftm_ai', get_string('pluginname', 'local_ftm_ai'));

    // ========================================
    // SEZIONE: Azure OpenAI / Copilot
    // ========================================
    $settings->add(new admin_setting_heading(
        'local_ftm_ai/azure_heading',
        'Configurazione Azure OpenAI (Copilot)',
        'Inserisci le credenziali del tuo deployment Azure OpenAI. Questi dati si trovano nel portale Azure.'
    ));

    // Endpoint
    $settings->add(new admin_setting_configtext(
        'local_ftm_ai/azure_endpoint',
        'Azure Endpoint',
        'URL del tuo resource Azure OpenAI (es. https://myresource.openai.azure.com)',
        '',
        PARAM_URL
    ));

    // API Key
    $settings->add(new admin_setting_configpasswordunmask(
        'local_ftm_ai/azure_api_key',
        'API Key',
        'Chiave API del tuo deployment Azure OpenAI',
        ''
    ));

    // Deployment Name
    $settings->add(new admin_setting_configtext(
        'local_ftm_ai/azure_deployment',
        'Deployment Name',
        'Nome del deployment (es. gpt-4, gpt-35-turbo)',
        'gpt-4',
        PARAM_ALPHANUMEXT
    ));

    // ========================================
    // SEZIONE: Privacy e Sicurezza
    // ========================================
    $settings->add(new admin_setting_heading(
        'local_ftm_ai/privacy_heading',
        'Privacy e Sicurezza',
        'Impostazioni per la protezione dei dati personali.'
    ));

    // Salt per anonimizzazione
    $settings->add(new admin_setting_configtext(
        'local_ftm_ai/salt',
        'Salt Anonimizzazione',
        'Stringa casuale per hash degli ID studente. NON modificare dopo l\'attivazione.',
        bin2hex(random_bytes(16)),
        PARAM_ALPHANUMEXT
    ));

    // Abilita logging dettagliato
    $settings->add(new admin_setting_configcheckbox(
        'local_ftm_ai/enable_logging',
        'Abilita Logging',
        'Registra le chiamate API per debug (ATTENZIONE: non logga mai dati personali)',
        0
    ));

    // ========================================
    // SEZIONE: Funzionalità
    // ========================================
    $settings->add(new admin_setting_heading(
        'local_ftm_ai/features_heading',
        'Funzionalità AI',
        'Abilita/disabilita le singole funzionalità AI.'
    ));

    // Suggerimenti linguistici variati
    $settings->add(new admin_setting_configcheckbox(
        'local_ftm_ai/enable_linguistic_variants',
        'Varianti Linguistiche',
        'Genera varianti diverse dei suggerimenti per evitare ripetizioni',
        1
    ));

    // Analisi predittiva
    $settings->add(new admin_setting_configcheckbox(
        'local_ftm_ai/enable_risk_analysis',
        'Analisi Predittiva Rischi',
        'Analizza i dati per prevedere potenziali rischi nel percorso formativo',
        1
    ));

    // Suggerimenti personalizzati
    $settings->add(new admin_setting_configcheckbox(
        'local_ftm_ai/enable_personalized_suggestions',
        'Suggerimenti Personalizzati',
        'Genera suggerimenti basati sullo storico completo dello studente',
        1
    ));

    // ========================================
    // SEZIONE: Limiti e Costi
    // ========================================
    $settings->add(new admin_setting_heading(
        'local_ftm_ai/limits_heading',
        'Limiti e Controllo Costi',
        'Imposta limiti per controllare i costi delle chiamate API.'
    ));

    // Max token per richiesta
    $settings->add(new admin_setting_configtext(
        'local_ftm_ai/max_tokens',
        'Max Token per Richiesta',
        'Limite massimo di token per singola richiesta (influenza costi)',
        '1500',
        PARAM_INT
    ));

    // Max richieste giornaliere
    $settings->add(new admin_setting_configtext(
        'local_ftm_ai/daily_limit',
        'Limite Richieste Giornaliere',
        'Numero massimo di richieste API al giorno (0 = illimitato)',
        '1000',
        PARAM_INT
    ));

    // Aggiungi pagina settings
    $ADMIN->add('localplugins', $settings);
}
