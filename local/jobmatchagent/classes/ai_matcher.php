<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AI matcher specifico per jobmatchagent — valuta CV vs offerta usando
 * descrizione completa (non solo titolo come jobsearch's match_cv_to_offers).
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_jobmatchagent;

defined('MOODLE_INTERNAL') || die();

class ai_matcher {

    /**
     * Match a CV vs a list of offers using richer context (title + description).
     * Returns: [offer_id => ['pct' => 0-100, 'reason' => string, 'is_cook_match' => bool]]
     *
     * @param string $cvtext
     * @param array $offers Array of objects with: id, title, company, location, work_schedule, parsed_text
     * @param string $desiredactivity Optional: what the user is searching for (e.g. "aiuto cuoco")
     * @return array
     */
    public static function match_cv_to_offers($cvtext, $offers, $desiredactivity = '') {
        $apikey = self::get_api_key();
        if (empty($apikey)) {
            throw new \Exception('API key OpenAI non configurata');
        }

        $model = get_config('local_jobmatchagent', 'openai_model') ?: 'gpt-4o-mini';

        // Truncate CV to control cost.
        $cvshort = mb_substr(trim($cvtext), 0, 4000);

        // Build offer list with descriptions.
        $offerlist = '';
        $count = 0;
        foreach ($offers as $o) {
            if ($count >= 20) {
                break; // Smaller batch (vs jobsearch's 30) so each offer gets enough description chars.
            }
            $offerlist .= "=== OFFERTA ID {$o->id} ===\n";
            $offerlist .= "Titolo: " . trim($o->title) . "\n";
            if (!empty($o->company)) {
                $offerlist .= "Azienda: " . trim($o->company) . "\n";
            }
            if (!empty($o->location)) {
                $offerlist .= "Localita: " . trim($o->location) . "\n";
            }
            if (!empty($o->work_schedule) && $o->work_schedule !== 'unknown') {
                $offerlist .= "Orario: " . $o->work_schedule . "\n";
            }
            // KEY DIFFERENCE vs jobsearch: include description (truncated).
            if (!empty($o->parsed_text)) {
                $desc = mb_substr(trim(strip_tags($o->parsed_text)), 0, 800);
                $offerlist .= "Descrizione: " . $desc . "\n";
            }
            $offerlist .= "\n";
            $count++;
        }

        $context = '';
        if (!empty($desiredactivity)) {
            $context = "L'operatore cerca per il candidato: \"{$desiredactivity}\"\n\n";
        }

        $systemmsg = "Sei un consulente di carriera senior con 15+ anni di esperienza nel mercato del lavoro SVIZZERO, "
            . "specializzato in CANTON TICINO. Conosci bene il sistema svizzero di formazione (AFC, CFC, attestati, "
            . "patente professionale) e il contesto linguistico ticinese.\n\n"
            . "TUO RUOLO: valuti la compatibilita CV-offerta in modo serio e realistico. "
            . "Devi PROTEGGERE lo studente da match inutili (offerte per cui sicuramente verra scartato) "
            . "MA SENZA penalizzarlo per requisiti che l'annuncio non chiede esplicitamente.\n\n"

            . "=== SCALA PUNTEGGIO (0-100) ===\n"
            . "- 80-95: esperienza diretta + tutti i requisiti espliciti soddisfatti\n"
            . "- 60-79: esperienza affine, requisiti chiave soddisfatti\n"
            . "- 50-59: competenze trasferibili o profilo affine, qualche requisito mancante\n"
            . "- 30-49: competenze trasferibili limitate o requisito chiave mancante\n"
            . "- 10-29: settore diverso o requisito esplicito BLOCCANTE non soddisfatto\n"
            . "- 0-9: profili totalmente disallineati\n\n"

            . "=== REGOLE SVIZZERA / TICINO (applicare SOLO se l'annuncio le richiede ESPLICITAMENTE) ===\n\n"

            . "1) FORMAZIONE REGOLAMENTATA (AFC/CFC/diploma/attestato):\n"
            . "   - SE l'annuncio richiede esplicitamente AFC/CFC/diploma/attestato professionale "
            . "(parole tipo: 'AFC obbligatorio', 'titolo richiesto', 'CFC indispensabile', 'diploma cantonale', "
            . "'patente professionale', 'iscrizione albo', 'attestato federale')\n"
            . "   E il CV NON mostra esplicitamente quel titolo → MAX 35%.\n"
            . "   - SE l'annuncio NON menziona requisiti formativi specifici → NON penalizzare il CV per assenza di AFC.\n"
            . "   - Professioni tipicamente regolamentate in CH/TI (per riconoscere il contesto): "
            . "elettricista (USIE), idraulico/sanitario, lattoniere, riscaldamenti, infermiere, OSS, "
            . "fisioterapista, autista CE/D, parrucchiere, estetista, cuoco AFC (vs aiuto cuoco non regolamentato), "
            . "meccanico auto AFC, carrozziere, costruttore, gruista.\n\n"

            . "2) LINGUE (Ticino e italofono → italiano e' fondamentale):\n"
            . "   - L'italiano e la lingua principale del Ticino: gli annunci sono tipicamente in italiano. "
            . "Lo studente parla italiano (madrelingua o equivalente).\n"
            . "   - SE l'annuncio richiede TEDESCO o FRANCESE a livello specifico (es. 'tedesco C1', "
            . "'francese fluente obbligatorio', 'Deutsch erforderlich'):\n"
            . "     * Se il CV mostra quella lingua → considera positivamente.\n"
            . "     * Se il CV NON mostra quella lingua → MAX 40% (e' un requisito chiave non rispettato).\n"
            . "   - SE l'annuncio e scritto INTERAMENTE in tedesco/francese (azienda svizzero-tedesca/romanda) "
            . "e il CV non mostra quella lingua → MAX 35% (probabilmente lavorera in quella lingua).\n"
            . "   - Inglese: importante per ruoli internazionali/IT/finance.\n\n"

            . "3) PERMESSO DI LAVORO: ignoralo (non noto dal CV).\n\n"

            . "4) ANNI ESPERIENZA: NON usare gli anni di esperienza minimi come fattore escludente. "
            . "Se l'annuncio chiede '5 anni' e il CV ne ha 1, riduci il punteggio MA NON sotto il 50%, "
            . "perche lo studente puo essere considerato comunque per il ruolo (formazione/crescita).\n\n"

            . "=== APPROCCIO ===\n"
            . "- Leggi i requisiti dell'annuncio con attenzione (descrizione completa).\n"
            . "- Identifica quali sono ESPLICITI (parole 'obbligatorio', 'richiesto', 'requisito', 'must have', 'erforderlich').\n"
            . "- Confronta col CV: la formazione, le esperienze, le lingue.\n"
            . "- NON inventare requisiti che l'annuncio non menziona (es: non penalizzare un'offerta per 'mancanza AFC' "
            . "se l'annuncio dice solo 'cerchiamo persona motivata').\n"
            . "- Sii REALISTICO ma non distruttivo: meglio segnalare un requisito mancante con score 35-50% "
            . "che dare 5% sproporzionato.\n\n"

            . "Rispondi ESCLUSIVAMENTE con JSON puro. NO markdown, NO testo extra.";

        $userprompt = $context
            . "=== CV CANDIDATO ===\n{$cvshort}\n\n"
            . "=== OFFERTE DI LAVORO ===\n{$offerlist}\n"
            . "Per OGNI offerta restituisci un oggetto con:\n"
            . "- id: l'ID dell'offerta (numero)\n"
            . "- pct: percentuale compatibilita 0-100 (segui SCRUPOLOSAMENTE le regole svizzere/TI nel system prompt)\n"
            . "- reason: una frase breve (max 25 parole) che cita ESPLICITAMENTE il punto chiave: "
            . "esperienza CV pertinente OPPURE requisito specifico dell'annuncio mancante (es: "
            . "'AFC elettricista richiesto, CV non lo mostra' / 'tedesco C1 obbligatorio, CV solo italiano' / "
            . "'esperienza pizzaiolo affine al ruolo cuoco').\n\n"
            . "Esempio output:\n"
            . '[{"id":1,"pct":85,"reason":"Esperienza diretta come aiuto cuoco perfettamente allineata"},'
            . '{"id":2,"pct":30,"reason":"AFC elettricista richiesto esplicitamente, CV non lo mostra"},'
            . '{"id":3,"pct":12,"reason":"Profilo culinario, nessuna competenza meccanica richiesta"}]';

        $messages = [
            ['role' => 'system', 'content' => $systemmsg],
            ['role' => 'user', 'content' => $userprompt],
        ];

        $response = self::call_openai($apikey, $model, $messages, 2500, 0.0);

        // Parse JSON.
        $response = preg_replace('/^```(?:json)?\s*/i', '', trim($response));
        $response = preg_replace('/\s*```$/', '', $response);
        $matches = json_decode(trim($response), true);

        if (!is_array($matches)) {
            throw new \Exception('AI non ha risposto JSON valido. Risposta: ' . substr($response, 0, 200));
        }

        $result = [];
        foreach ($matches as $m) {
            if (!isset($m['id'])) {
                continue;
            }
            $result[(int) $m['id']] = [
                'pct' => max(0, min(100, (int) ($m['pct'] ?? 0))),
                'reason' => trim((string) ($m['reason'] ?? '')),
            ];
        }
        return $result;
    }

    /**
     * Get API key — own first, then jobsearch, then jobaida.
     *
     * @return string
     */
    private static function get_api_key() {
        $key = get_config('local_jobmatchagent', 'openai_apikey');
        if (!empty($key)) {
            return $key;
        }
        $key = get_config('local_ftm_jobsearch', 'openai_apikey');
        if (!empty($key)) {
            return $key;
        }
        return get_config('local_jobaida', 'openai_apikey') ?: '';
    }

    /**
     * Call OpenAI chat completion.
     *
     * @param string $apikey
     * @param string $model
     * @param array $messages
     * @param int $maxtokens
     * @param float $temperature
     * @return string
     * @throws \Exception
     */
    private static function call_openai($apikey, $model, $messages, $maxtokens = 2500, $temperature = 0.0) {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxtokens,
            'temperature' => $temperature,
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apikey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 90,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('Errore connessione OpenAI: ' . $error);
        }
        if ($httpcode !== 200) {
            $data = json_decode($response, true);
            throw new \Exception('OpenAI HTTP ' . $httpcode . ': ' . ($data['error']['message'] ?? 'unknown'));
        }

        $data = json_decode($response, true);
        return trim($data['choices'][0]['message']['content'] ?? '');
    }
}
