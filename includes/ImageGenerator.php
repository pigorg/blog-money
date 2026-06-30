<?php
class ImageGenerator {
    private $apiKey;
    private $uploadDir;
    private $uploadUrl;
    private $endpoint = 'https://api.together.xyz/v1/images/generations';

    public function __construct($apiKey, $uploadDir, $uploadUrl) {
        $this->apiKey   = $apiKey;
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        $this->uploadUrl = rtrim($uploadUrl, '/') . '/';

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public function genera($prompt, $larghezza = 1280, $altezza = 720) {
        $payload = json_encode([
            'model'  => 'black-forest-labs/FLUX.1-schnell',
            'prompt' => $prompt,
            'width'  => $larghezza,
            'height' => $altezza,
            'steps'  => 4,
            'n'      => 1,
        ]);

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpCode !== 200) {
            throw new Exception("Image API error ($httpCode): $response");
        }

        $data     = json_decode($response, true);
        $imageUrl = $data['data'][0]['url'] ?? null;

        if (!$imageUrl) {
            throw new Exception('Nessun URL immagine nella risposta API');
        }

        return $this->scaricaESalva($imageUrl);
    }

    private function scaricaESalva($url) {
        $contenuto = file_get_contents($url);
        if ($contenuto === false) {
            throw new Exception('Impossibile scaricare immagine: ' . $url);
        }

        $nome     = uniqid('img_') . '.jpg';
        $percorso = $this->uploadDir . $nome;
        file_put_contents($percorso, $contenuto);

        return $this->uploadUrl . $nome;
    }
}
