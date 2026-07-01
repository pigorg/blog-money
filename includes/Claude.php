<?php
class Claude {
    private $apiKey;
    private $model;
    private $apiUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct($apiKey, $model = 'claude-sonnet-4-6') {
        $this->apiKey = $apiKey;
        $this->model  = $model;
    }

    public function approfondisci($titolo) {
        $prompt = "Sei un esperto di finanza e investimenti. Approfondisci questo argomento finanziario: \"$titolo\"

Rispondi SEMPRE in italiano, anche se il titolo è in inglese.

Fornisci SOLO un JSON valido con questa struttura (nessun testo prima o dopo):
{
  \"contesto\": \"spiegazione del contesto (2-3 paragrafi)\",
  \"dati_chiave\": [\"dato 1\", \"dato 2\", \"dato 3\"],
  \"punti_principali\": [\"punto 1\", \"punto 2\", \"punto 3\"],
  \"implicazioni\": \"cosa significa per gli investitori italiani\",
  \"keywords_seo\": [\"keyword1\", \"keyword2\", \"keyword3\", \"keyword4\", \"keyword5\"]
}";

        $testo = $this->call($prompt, 600);
        $json  = $this->estraiJSON($testo);
        return $json ?? [
            'contesto'          => $testo,
            'dati_chiave'       => [],
            'punti_principali'  => [],
            'implicazioni'      => '',
            'keywords_seo'      => [],
        ];
    }

    public function generaArticolo($titolo, $categoria, $approfondimento, $note = '') {
        $contesto = json_encode($approfondimento, JSON_UNESCAPED_UNICODE);

        // Step A: metadati (JSON leggero, senza HTML)
        $promptMeta = "Sei un giornalista finanziario. Devi preparare i metadati per un articolo in italiano.

ARGOMENTO: $titolo
CATEGORIA: $categoria
CONTESTO: $contesto

Rispondi SOLO con questo JSON (nessun testo prima o dopo, usa SOLO virgolette doppie, non usare virgolette all'interno dei valori):
{
  \"titolo\": \"Titolo SEO ottimizzato max 70 caratteri\",
  \"excerpt\": \"Introduzione coinvolgente max 160 caratteri\",
  \"meta_description\": \"Meta description SEO max 160 caratteri\",
  \"keywords\": \"keyword1, keyword2, keyword3, keyword4, keyword5\",
  \"tempo_lettura\": 7,
  \"immagine_grande_prompt\": \"photorealistic financial scene cinematic lighting 16:9\",
  \"immagine_piccola_prompt\": \"close-up financial detail square format sharp focus\",
  \"immagine_alt\": \"Descrizione immagine principale in italiano\",
  \"immagine_piccola_alt\": \"Descrizione immagine secondaria in italiano\"
}";

        $testoMeta = $this->call($promptMeta, 600);
        $meta      = $this->estraiJSON($testoMeta);

        if (!$meta || empty($meta['titolo'])) {
            $this->debugLog('META', $testoMeta);
            throw new Exception('Claude non ha restituito metadati validi. json_error: ' . json_last_error_msg());
        }

        // Step B: contenuto HTML come testo puro (non in JSON)
        $noteSection = $note ? "\nISTRUZIONI SPECIFICHE DELL'AUTORE:\n$note\n" : '';
        $promptContent = "Sei un giornalista finanziario esperto. Scrivi un articolo di blog professionale in italiano.

TITOLO: {$meta['titolo']}
CATEGORIA: $categoria
CONTESTO E DATI: $contesto{$noteSection}

ISTRUZIONI STRUTTURA:
- Il titolo principale (H1) è già presente nella pagina: NON aggiungerlo nel corpo
- Usa <h2> per i titoli delle sezioni principali (minimo 3, massimo 5 sezioni)
- Usa <h3> per i sotto-argomenti all'interno di ogni sezione H2
- Ogni sezione H2 deve contenere almeno 2-3 paragrafi <p>
- Ogni paragrafo deve essere separato dagli altri con un paragrafo vuoto: <p>&nbsp;</p>
- Struttura: breve introduzione (<p>) → sezioni H2 con H3 annidati → conclusione con call to action

ISTRUZIONI FORMATO:
- Lunghezza: 900-1200 parole
- Ogni paragrafo di testo va racchiuso in tag <p>...</p>
- Usa <strong> per evidenziare dati e termini chiave
- Usa <ul><li> per elenchi puntati (massimo uno per sezione)
- Tono: informativo, chiaro, autorevole ma accessibile
- NON usare markdown, solo HTML puro
- NON scrivere nulla prima del primo tag HTML e nulla dopo l'ultimo tag HTML

Scrivi SOLO il corpo dell'articolo in HTML (inizia con <h2>):";

        $contenuto = trim($this->call($promptContent, 3000));

        if (empty($contenuto) || strlen($contenuto) < 200) {
            $this->debugLog('CONTENUTO', $contenuto);
            throw new Exception('Claude non ha generato contenuto sufficiente (' . strlen($contenuto) . ' caratteri).');
        }

        return array_merge($meta, ['contenuto' => $contenuto]);
    }

    private function debugLog($tipo, $testo) {
        $file = dirname(__DIR__) . '/cron/claude_debug.log';
        $riga = "=== $tipo @ " . date('Y-m-d H:i:s') . " ===\n"
              . "json_error: " . json_last_error_msg() . "\n"
              . "lunghezza: " . strlen($testo) . " char\n"
              . "--- RISPOSTA ---\n"
              . $testo . "\n\n";
        file_put_contents($file, $riga, FILE_APPEND);
    }

    private function call($prompt, $maxTokens = 2000) {
        $payload = json_encode([
            'model'      => $this->model,
            'max_tokens' => $maxTokens,
            'messages'   => [
                ['role' => 'user', 'content' => $prompt]
            ],
        ]);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('cURL error: ' . $curlError);
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $errore = $data['error']['message'] ?? 'Errore API sconosciuto';
            throw new Exception("Claude API ($httpCode): $errore");
        }

        return $data['content'][0]['text'] ?? '';
    }

    private function estraiJSON($testo) {
        // 1. Parse diretto
        $parsed = json_decode($testo, true);
        if ($parsed !== null) return $parsed;

        // 2. Rimuovi code fences e riprova
        $pulito = preg_replace('/^```(?:json)?\s*/i', '', trim($testo));
        $pulito = preg_replace('/\s*```$/i', '', $pulito);
        $parsed = json_decode(trim($pulito), true);
        if ($parsed !== null) return $parsed;

        // 3. Estrai dal primo { all'ultima }
        $start = strpos($testo, '{');
        $end   = strrpos($testo, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $parsed = json_decode(substr($testo, $start, $end - $start + 1), true);
            if ($parsed !== null) return $parsed;
        }

        return null;
    }
}
