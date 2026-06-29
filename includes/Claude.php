<?php
class Claude {
    private $apiKey;
    private $model;
    private $apiUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct($apiKey, $model = 'claude-sonnet-4-6') {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    public function approfondisci($titolo) {
        $prompt = "Sei un esperto di finanza e investimenti. Approfondisci questo argomento finanziario: \"$titolo\"

Rispondi SEMPRE in italiano, anche se il titolo è in inglese.

Fornisci SOLO un JSON valido con questa struttura:
{
  \"contesto\": \"Spiegazione del contesto e background (2-3 paragrafi)\",
  \"dati_chiave\": [\"dato 1\", \"dato 2\", \"dato 3\"],
  \"punti_principali\": [\"punto 1\", \"punto 2\", \"punto 3\"],
  \"implicazioni\": \"Cosa significa per gli investitori italiani\",
  \"keywords_seo\": [\"keyword1\", \"keyword2\", \"keyword3\", \"keyword4\", \"keyword5\"]
}";

        $testo = $this->call($prompt, 1000);
        $json = $this->estraiJSON($testo);
        return $json ?? ['contesto' => $testo, 'dati_chiave' => [], 'punti_principali' => [], 'implicazioni' => '', 'keywords_seo' => []];
    }

    public function generaArticolo($titolo, $categoria, $approfondimento) {
        $contesto = json_encode($approfondimento, JSON_UNESCAPED_UNICODE);

        $prompt = "Sei un giornalista finanziario esperto. Scrivi un articolo di blog professionale in italiano.

ARGOMENTO: $titolo
CATEGORIA: $categoria
CONTESTO E DATI: $contesto

ISTRUZIONI:
- Lunghezza: 1500-2000 parole
- Formato: HTML con tag h2, h3, p, ul, li, strong
- Tono: informativo, chiaro, autorevole ma accessibile
- Struttura: introduzione coinvolgente → sviluppo con dati → conclusione con call to action
- Integra naturalmente le keywords SEO
- NON usare markdown, solo HTML puro

Per le immagini crea prompt in inglese adatti a un modello text-to-image (FLUX):
- immagine_grande_prompt: scena finanziaria fotorealistica, orizzontale 16:9, cinematic lighting
- immagine_piccola_prompt: dettaglio o elemento correlato all'articolo, quadrata, close-up

Rispondi SOLO con questo JSON valido (niente testo prima o dopo):
{
  \"titolo\": \"Titolo SEO ottimizzato (max 70 caratteri)\",
  \"excerpt\": \"Introduzione breve e coinvolgente (max 160 caratteri)\",
  \"meta_description\": \"Meta description SEO (max 160 caratteri)\",
  \"keywords\": \"keyword1, keyword2, keyword3, keyword4, keyword5\",
  \"contenuto\": \"<h2>...</h2><p>...</p> (HTML completo articolo 1500+ parole)\",
  \"tempo_lettura\": 7,
  \"immagine_grande_prompt\": \"photorealistic financial scene, ..., cinematic lighting, 16:9\",
  \"immagine_piccola_prompt\": \"close-up of ..., square format, sharp focus\",
  \"immagine_alt\": \"Descrizione immagine principale in italiano\",
  \"immagine_piccola_alt\": \"Descrizione immagine secondaria in italiano\"
}";

        $testo = $this->call($prompt, 4000);
        $json = $this->estraiJSON($testo);

        if (!$json || empty($json['contenuto'])) {
            throw new Exception('Claude non ha restituito un articolo valido.');
        }

        return $json;
    }

    private function call($prompt, $maxTokens = 2000) {
        $payload = json_encode([
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ]);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $errore = $data['error']['message'] ?? 'Errore API sconosciuto';
            throw new Exception("Claude API ($httpCode): $errore");
        }

        return $data['content'][0]['text'] ?? '';
    }

    private function estraiJSON($testo) {
        // Cerca JSON tra ``` o direttamente
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/i', $testo, $m)) {
            return json_decode($m[1], true);
        }
        // Prova parsing diretto
        $parsed = json_decode($testo, true);
        if ($parsed) {
            return $parsed;
        }
        // Cerca prima { e ultima }
        $start = strpos($testo, '{');
        $end = strrpos($testo, '}');
        if ($start !== false && $end !== false) {
            return json_decode(substr($testo, $start, $end - $start + 1), true);
        }
        return null;
    }
}
