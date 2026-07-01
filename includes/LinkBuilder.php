<?php
class LinkBuilder {
    private $db;
    private $keywordMap = [];
    private const MAX_LINK_PER_ARTICOLO = 6;
    private const MIN_KEYWORD_LENGTH    = 5;

    public function __construct($db) {
        $this->db = $db;
    }

    public function arricchisci(string $contenuto, int $articoloId): string {
        $this->caricaKeywords($articoloId);
        if (empty($this->keywordMap)) return $contenuto;
        return $this->sostituisci($contenuto);
    }

    private function caricaKeywords(int $escludiId): void {
        $stmt = $this->db->prepare(
            "SELECT slug, titolo_finale, keywords FROM articoli
             WHERE stato = 'pubblicato' AND id != ? AND keywords IS NOT NULL AND keywords != ''
             ORDER BY data_pubblicazione DESC"
        );
        $stmt->bind_param('i', $escludiId);
        $stmt->execute();
        $articoli = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($articoli as $a) {
            foreach (explode(',', $a['keywords']) as $kw) {
                $kw    = trim($kw);
                $lower = mb_strtolower($kw, 'UTF-8');
                if (mb_strlen($lower) < self::MIN_KEYWORD_LENGTH) continue;
                if (!isset($this->keywordMap[$lower])) {
                    $this->keywordMap[$lower] = [
                        'url'    => SITE_URL . '/articolo/' . $a['slug'],
                        'titolo' => $a['titolo_finale'],
                        'testo'  => $kw,
                    ];
                }
            }
        }

        // Prima le keyword più lunghe: evita sostituzioni parziali
        uksort($this->keywordMap, fn($a, $b) => mb_strlen($b) - mb_strlen($a));
    }

    private function sostituisci(string $contenuto): string {
        // Divide il contenuto in segmenti: tag HTML e testo puro
        $parti = preg_split('/(<[^>]+>)/s', $contenuto, -1, PREG_SPLIT_DELIM_CAPTURE);

        $inLink    = false;
        $inHeading = false;
        $linkUsati = 0;
        $kwUsate   = [];
        $output    = '';

        foreach ($parti as $parte) {
            if (preg_match('/^<[^>]+>$/s', $parte)) {
                // Aggiorna lo stato in base al tag
                if (preg_match('/^<a[\s>]/i', $parte))   $inLink    = true;
                if (preg_match('/^<\/a>/i', $parte))      $inLink    = false;
                if (preg_match('/^<h[1-3][\s>]/i', $parte)) $inHeading = true;
                if (preg_match('/^<\/h[1-3]>/i', $parte))   $inHeading = false;
                $output .= $parte;
            } else {
                // Testo: inserisci link solo fuori da <a> e <h1-3>
                if (!$inLink && !$inHeading && $linkUsati < self::MAX_LINK_PER_ARTICOLO) {
                    $parte = $this->sostituisciNelTesto($parte, $kwUsate, $linkUsati);
                }
                $output .= $parte;
            }
        }

        return $output;
    }

    private function sostituisciNelTesto(string $testo, array &$kwUsate, int &$linkUsati): string {
        foreach ($this->keywordMap as $lower => $info) {
            if ($linkUsati >= self::MAX_LINK_PER_ARTICOLO) break;
            if (isset($kwUsate[$lower])) continue;

            $pattern = '/(?<![a-zA-ZÀ-ÖØ-öø-ÿ])(' . preg_quote($info['testo'], '/') . ')(?![a-zA-ZÀ-ÖØ-öø-ÿ])/iu';

            if (preg_match($pattern, $testo)) {
                $url   = htmlspecialchars($info['url'], ENT_QUOTES);
                $title = htmlspecialchars($info['titolo'], ENT_QUOTES);
                $testo = preg_replace(
                    $pattern,
                    '<a href="' . $url . '" title="' . $title . '" class="link-interno">$1</a>',
                    $testo,
                    1
                );
                $kwUsate[$lower] = true;
                $linkUsati++;
            }
        }
        return $testo;
    }
}
