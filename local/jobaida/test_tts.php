<?php
/**
 * Test TTS endpoint - diagnoses ElevenLabs/OpenAI configuration.
 */
require_once(__DIR__ . '/../../config.php');
require_login();
if (!is_siteadmin()) { die('Solo admin'); }

echo '<h2>Test Text-to-Speech</h2>';
echo '<pre style="background:#f5f5f5; padding:20px; font-size:14px;">';

$provider = get_config('local_jobaida', 'tts_provider');
echo "1. Provider configurato: " . ($provider ?: 'NON IMPOSTATO (default: openai)') . "\n";

if ($provider === 'elevenlabs') {
    $apikey = get_config('local_jobaida', 'elevenlabs_apikey');
    $voiceid = get_config('local_jobaida', 'elevenlabs_voice_id');
    echo "2. ElevenLabs API Key: " . ($apikey ? substr($apikey, 0, 8) . '***' : 'MANCANTE') . "\n";
    echo "3. ElevenLabs Voice ID: " . ($voiceid ?: 'MANCANTE (default: pNInz6obpgDQGcFmaJgB)') . "\n";

    if ($apikey) {
        // Test API call.
        $voiceid = $voiceid ?: 'pNInz6obpgDQGcFmaJgB';
        $payload = [
            'text' => 'Buongiorno, questo e un test della voce ElevenLabs.',
            'model_id' => 'eleven_multilingual_v2',
            'voice_settings' => ['stability' => 0.5, 'similarity_boost' => 0.75, 'style' => 0.3],
        ];

        echo "\n4. Test chiamata API...\n";
        echo "   URL: https://api.elevenlabs.io/v1/text-to-speech/{$voiceid}\n";

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
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $contenttype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        echo "   HTTP Code: {$httpcode}\n";
        echo "   Content-Type: {$contenttype}\n";
        echo "   Response size: " . strlen($response) . " bytes\n";

        if ($error) {
            echo "   ERRORE cURL: {$error}\n";
        } else if ($httpcode === 200 && strpos($contenttype, 'audio') !== false) {
            echo "   RISULTATO: OK - Audio ricevuto!\n";
            echo '</pre>';
            echo '<h3 style="color:green;">ElevenLabs funziona! Ascolta il test:</h3>';
            echo '<audio controls autoplay src="data:audio/mpeg;base64,' . base64_encode($response) . '"></audio>';
            die();
        } else {
            echo "   ERRORE risposta:\n";
            $errordata = json_decode($response, true);
            if ($errordata) {
                echo "   " . json_encode($errordata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo "   " . substr($response, 0, 500) . "\n";
            }
        }
    }
} else if ($provider === 'openai' || empty($provider)) {
    $apikey = get_config('local_jobaida', 'openai_apikey');
    $voice = get_config('local_jobaida', 'tts_openai_voice') ?: 'onyx';
    echo "2. OpenAI API Key: " . ($apikey ? substr($apikey, 0, 8) . '***' : 'MANCANTE') . "\n";
    echo "3. Voce: {$voice}\n";
} else {
    echo "2. Provider 'browser' selezionato - usa voce sintetizzata del browser.\n";
}

echo '</pre>';
