<?php
/**
 * Plugin settings.
 *
 * @package    local_jobaida
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_jobaida', get_string('pluginname', 'local_jobaida'));

    // OpenAI API Key.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_jobaida/openai_apikey',
        get_string('openai_apikey', 'local_jobaida'),
        get_string('openai_apikey_desc', 'local_jobaida'),
        ''
    ));

    // OpenAI Model.
    $settings->add(new admin_setting_configselect(
        'local_jobaida/openai_model',
        get_string('openai_model', 'local_jobaida'),
        get_string('openai_model_desc', 'local_jobaida'),
        'gpt-4o',
        [
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-4-turbo' => 'GPT-4 Turbo',
        ]
    ));

    // Language for generated letters.
    $settings->add(new admin_setting_configselect(
        'local_jobaida/letter_language',
        get_string('letter_language', 'local_jobaida'),
        get_string('letter_language_desc', 'local_jobaida'),
        'it',
        [
            'it' => 'Italiano',
            'de' => 'Deutsch',
            'fr' => 'Francais',
            'en' => 'English',
        ]
    ));

    // Max tokens.
    $settings->add(new admin_setting_configtext(
        'local_jobaida/max_tokens',
        get_string('max_tokens', 'local_jobaida'),
        get_string('max_tokens_desc', 'local_jobaida'),
        '4000',
        PARAM_INT
    ));

    // ========== TTS Settings (Text-to-Speech for Interview Simulation) ==========

    $settings->add(new admin_setting_heading(
        'local_jobaida/tts_heading',
        'Text-to-Speech (Colloquio Simulato)',
        'Configura il motore vocale per la simulazione colloquio.'
    ));

    // TTS Provider.
    $settings->add(new admin_setting_configselect(
        'local_jobaida/tts_provider',
        'Motore TTS',
        'Scegli il motore Text-to-Speech: OpenAI (buona qualita, usa stessa API key) o ElevenLabs (qualita premium, richiede API key separata).',
        'openai',
        [
            'openai' => 'OpenAI TTS (~$0.06/colloquio)',
            'elevenlabs' => 'ElevenLabs (qualita premium)',
            'browser' => 'Browser (gratuito, qualita base)',
        ]
    ));

    // OpenAI TTS Voice.
    $settings->add(new admin_setting_configselect(
        'local_jobaida/tts_openai_voice',
        'Voce OpenAI',
        'Voce per OpenAI TTS. Onyx = maschile professionale, Nova = femminile.',
        'onyx',
        [
            'onyx' => 'Onyx (maschile, professionale)',
            'nova' => 'Nova (femminile)',
            'alloy' => 'Alloy (neutrale)',
            'echo' => 'Echo (maschile)',
            'fable' => 'Fable (narratore)',
            'shimmer' => 'Shimmer (femminile morbida)',
        ]
    ));

    // ElevenLabs API Key.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_jobaida/elevenlabs_apikey',
        'ElevenLabs API Key',
        'API key da elevenlabs.io (Profile > API Keys). Necessaria solo se usi ElevenLabs come motore TTS.',
        ''
    ));

    // ElevenLabs Voice ID.
    $settings->add(new admin_setting_configtext(
        'local_jobaida/elevenlabs_voice_id',
        'ElevenLabs Voice ID',
        'ID della voce ElevenLabs. Trova le voci disponibili su elevenlabs.io/voice-library. Esempio: pNInz6obpgDQGcFmaJgB (Adam).',
        '',
        PARAM_ALPHANUMEXT
    ));

    $ADMIN->add('localplugins', $settings);
}
