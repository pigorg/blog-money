<?php
class SocialSharer {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function condividi(array $articolo): array {
        $url    = rtrim(SITE_URL, '/') . '/articolo/' . $articolo['slug'];
        $titolo = $articolo['titolo_finale'];
        $risultati = [];

        if (defined('X_API_KEY') && X_API_KEY) {
            $risultati['x'] = $this->postX($titolo, $url);
        }

        if (defined('FB_PAGE_TOKEN') && FB_PAGE_TOKEN) {
            $risultati['facebook'] = $this->postFacebook($titolo, $articolo['excerpt'] ?? '', $url);
        }

        return $risultati;
    }

    // ── TWITTER / X ─────────────────────────────────────────────────────────

    private function postX(string $titolo, string $url): bool|string {
        // X conta ogni URL come 23 caratteri; teniamo il testo entro 280
        $testo = $this->troncaTesto($titolo, 230) . "\n\n" . $url;

        $endpoint = 'https://api.twitter.com/2/tweets';
        $payload  = json_encode(['text' => $testo]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $this->xOauthHeader('POST', $endpoint),
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 201) return true;
        return "X HTTP $code: $response";
    }

    private function xOauthHeader(string $method, string $url): string {
        $nonce     = bin2hex(random_bytes(16));
        $timestamp = (string) time();

        $oauth = [
            'oauth_consumer_key'     => X_API_KEY,
            'oauth_nonce'            => $nonce,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => $timestamp,
            'oauth_token'            => X_ACCESS_TOKEN,
            'oauth_version'          => '1.0',
        ];

        ksort($oauth);
        $paramStr   = http_build_query($oauth, '', '&', PHP_QUERY_RFC3986);
        $baseStr    = 'POST&' . rawurlencode($url) . '&' . rawurlencode($paramStr);
        $signingKey = rawurlencode(X_API_SECRET) . '&' . rawurlencode(X_ACCESS_SECRET);
        $signature  = base64_encode(hash_hmac('sha1', $baseStr, $signingKey, true));

        $oauth['oauth_signature'] = $signature;
        ksort($oauth);

        $parts = [];
        foreach ($oauth as $k => $v) {
            $parts[] = rawurlencode($k) . '="' . rawurlencode($v) . '"';
        }
        return 'OAuth ' . implode(', ', $parts);
    }

    // ── FACEBOOK ─────────────────────────────────────────────────────────────

    private function postFacebook(string $titolo, string $excerpt, string $url): bool|string {
        $messaggio = $titolo;
        if ($excerpt) {
            $messaggio .= "\n\n" . $this->troncaTesto($excerpt, 200);
        }
        $messaggio .= "\n\n" . $url;

        $endpoint = 'https://graph.facebook.com/v19.0/' . FB_PAGE_ID . '/feed';

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'message'      => $messaggio,
                'link'         => $url,
                'access_token' => FB_PAGE_TOKEN,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if (isset($data['id'])) return true;
        return "Facebook HTTP $code: $response";
    }

    // ── UTILITY ──────────────────────────────────────────────────────────────

    private function troncaTesto(string $testo, int $max): string {
        if (mb_strlen($testo) <= $max) return $testo;
        return mb_substr($testo, 0, $max - 1) . '…';
    }
}
