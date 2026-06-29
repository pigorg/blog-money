<?php
class Scraper {
    private $db;

    private $keywordsCrypto = ['bitcoin', 'ethereum', 'crypto', 'blockchain', 'nft', 'defi', 'web3', 'altcoin', 'btc', 'eth'];
    private $keywordsBorsa = ['borsa', 'azioni', 'ftse', 'dax', 'sp500', 's&p', 'nasdaq', 'dow', 'indice', 'titoli'];
    private $keywordsImmobiliare = ['immobiliare', 'casa', 'affitto', 'mutuo', 'real estate', 'appartamento'];
    private $keywordsRisparmio = ['risparmio', 'pensione', 'fondi', 'etf', 'obbligazioni', 'btp', 'conto deposito'];

    public function __construct($database) {
        $this->db = $database->getConn();
    }

    public function estraiDaRSS($url) {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'Mozilla/5.0 (compatible; BlogMoney/1.0)',
            ]
        ]);

        $xml = @file_get_contents($url, false, $ctx);
        if (!$xml) {
            throw new Exception("Impossibile leggere RSS: $url");
        }

        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml);
        if (!$feed) {
            throw new Exception("RSS non valido: $url");
        }

        $titoli = [];
        $items = $feed->channel->item ?? $feed->entry ?? [];

        foreach ($items as $item) {
            $titolo = trim((string)($item->title ?? ''));
            $link   = trim((string)($item->link ?? $item->guid ?? ''));
            if ($titolo) {
                $titoli[] = ['titolo' => $titolo, 'url' => $link];
            }
        }

        return $titoli;
    }

    public function salvaTitoli($sorgente_id, $titoli) {
        $check = $this->db->prepare(
            'SELECT id FROM titoli_estratti WHERE titolo_originale = ? AND sorgente_id = ?'
        );
        $ins = $this->db->prepare(
            'INSERT INTO titoli_estratti (sorgente_id, titolo_originale, url_originale, categoria) VALUES (?, ?, ?, ?)'
        );

        $nuovi = 0;
        foreach ($titoli as $t) {
            $check->bind_param('si', $t['titolo'], $sorgente_id);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                continue;
            }
            $categoria = $this->assegnaCategoria($t['titolo']);
            $ins->bind_param('isss', $sorgente_id, $t['titolo'], $t['url'], $categoria);
            $ins->execute();
            $nuovi++;
        }

        $this->db->prepare(
            'UPDATE sorgenti SET ultima_sincronizzazione = NOW() WHERE id = ?'
        )->execute() || null;

        $stmt = $this->db->prepare('UPDATE sorgenti SET ultima_sincronizzazione = NOW() WHERE id = ?');
        $stmt->bind_param('i', $sorgente_id);
        $stmt->execute();

        return $nuovi;
    }

    private function assegnaCategoria($titolo) {
        $t = mb_strtolower($titolo, 'UTF-8');
        foreach ($this->keywordsCrypto as $k) {
            if (strpos($t, $k) !== false) return 'Criptovalute';
        }
        foreach ($this->keywordsBorsa as $k) {
            if (strpos($t, $k) !== false) return 'Borsa';
        }
        foreach ($this->keywordsImmobiliare as $k) {
            if (strpos($t, $k) !== false) return 'Immobiliare';
        }
        foreach ($this->keywordsRisparmio as $k) {
            if (strpos($t, $k) !== false) return 'Risparmio';
        }
        return 'Finanza';
    }
}
