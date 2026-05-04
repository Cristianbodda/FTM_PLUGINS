<?php
/**
 * AJAX endpoint for Text-to-Speech.
 * Supports OpenAI TTS and ElevenLabs.
 *
 * @package    local_jobaida
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

try {
    $text = required_param('text', PARAM_RAW);

    if (empty(trim($text))) {
        throw new Exception('Nessun testo da convertire.');
    }

    // Limit text length.
    $text = mb_substr(trim($text), 0, 2000);

    $provider = get_config('local_jobaida', 'tts_provider') ?: 'openai';

    switch ($provider) {

        case 'elevenlabs':
            $apikey = get_config('local_jobaida', 'elevenlabs_apikey');
            if (empty($apikey)) {
                throw new Exception('API key ElevenLabs non configurata. Vai in Amministrazione > Plugin > JobAIDA.');
            }

            $voiceid = get_config('local_jobaida', 'elevenlabs_voice_id') ?: 'pNInz6obpgDQGcFmaJgB'; // Adam default.

            $payload = [
                'text' => $text,
                'model_id' => 'eleven_multilingual_v2',
                'voice_settings' => [
                    'stability' => 0.5,
                    'similarity_boost' => 0.75,
                    'style' => 0.3,
                ],
            ];

            $ch = curl_init('https://api.elevenlabs.io/v1/text-to-speech/' . $voiceid);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'xi-api-key: ' . $apikey,
                    'Accept: audio/mpeg',
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 30,
            ]);

            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new Exception('Errore connessione ElevenLabs: ' . $error);
            }
            if ($httpcode !== 200) {
                $errordata = json_decode($response, true);
                if ($errordata && isset($errordata['detail'])) {
                    $detail = is_array($errordata['detail']) ? json_encode($errordata['detail']) : $errordata['detail'];
                    throw new Exception('ElevenLabs: ' . $detail);
                }
                throw new Exception('ElevenLabs: HTTP ' . $httpcode);
            }

            header('Content-Type: audio/mpeg');
            header('Content-Length: ' . strlen($response));
            header('Cache-Control: no-cache');
            echo $response;
            break;

        case 'openai':
        default:
            $apikey = get_config('local_jobaida', 'openai_apikey');
            if (empty($apikey)) {
                throw new Exception('API key OpenAI non configurata.');
            }

            $voice = get_config('local_jobaida', 'tts_openai_voice') ?: 'onyx';
            $validvoices = ['alloy', 'echo', 'fable', 'nova', 'onyx', 'shimmer'];
            if (!in_array($voice, $validvoices)) {
                $voice = 'onyx';
            }

            $payload = [
                'model' => 'tts-1',
                'input' => $text,
                'voice' => $voice,
                'response_format' => 'mp3',
            ];

            $ch = curl_init('https://api.openai.com/v1/audio/speech');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apikey,
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 30,
            ]);

            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new Exception('Errore connessione OpenAI: ' . $error);
            }
            if ($httpcode !== 200) {
                $errordata = json_decode($response, true);
                if ($errordata && isset($errordata['error'])) {
                    throw new Exception('OpenAI TTS: ' . $errordata['error']['message']);
                }
                throw new Exception('OpenAI TTS: HTTP ' . $httpcode);
            }

            header('Content-Type: audio/mpeg');
            header('Content-Length: ' . strlen($response));
            header('Cache-Control: no-cache');
            echo $response;
            break;
    }

} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

die();
