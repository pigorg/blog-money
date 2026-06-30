<?php
require_once __DIR__ . '/Claude.php';
require_once __DIR__ . '/ImageGenerator.php';
require_once __DIR__ . '/helpers.php';

class ArticleGenerator {
    private $db;
    private $claude;

    public function __construct($database, $claudeApiKey, $claudeModel = 'claude-sonnet-4-6') {
        $this->db = $database->getConn();
        $this->claude = new Claude($claudeApiKey, $claudeModel);
    }

    public function generaArticolo($titolo_estratto_id) {
        $stmt = $this->db->prepare('SELECT * FROM titoli_estratti WHERE id = ?');
        $stmt->bind_param('i', $titolo_estratto_id);
        $stmt->execute();
        $titolo = $stmt->get_result()->fetch_assoc();

        if (!$titolo) {
            throw new Exception("Titolo ID $titolo_estratto_id non trovato.");
        }

        @set_time_limit(300);
        @ini_set('max_execution_time', 300);

        logDB($this->db, 'generazione', "Avvio generazione per: {$titolo['titolo_originale']}");

        // Fase 1: approfondimento
        logDB($this->db, 'generazione', '[1/3] Approfondimento in corso...', 'info');
        $approfondimento = $this->claude->approfondisci($titolo['titolo_originale']);
        logDB($this->db, 'generazione', '[1/3] Approfondimento completato.', 'info');

        // Fase 2: metadati + contenuto HTML (2 chiamate Claude separate)
        logDB($this->db, 'generazione', '[2/3] Generazione metadati e contenuto...', 'info');
        $dati = $this->claude->generaArticolo(
            $titolo['titolo_originale'],
            $titolo['categoria'],
            $approfondimento
        );
        logDB($this->db, 'generazione', '[2/3] Contenuto generato (' . strlen($dati['contenuto']) . ' char).', 'info');

        // Fase 3: generazione immagini AI
        $immagineGrandeUrl  = null;
        $immaginePiccolaUrl = null;
        $immagineAlt        = $dati['immagine_alt'] ?? $dati['titolo'];
        $immaginePiccolaAlt = $dati['immagine_piccola_alt'] ?? $dati['titolo'];

        if (!empty($dati['immagine_grande_prompt']) && defined('TOGETHER_API_KEY') && TOGETHER_API_KEY) {
            try {
                $imageGen = new ImageGenerator(
                    TOGETHER_API_KEY,
                    dirname(__DIR__) . '/uploads/immagini/',
                    SITE_URL . '/uploads/immagini/'
                );
                $immagineGrandeUrl  = $imageGen->genera($dati['immagine_grande_prompt'], 1280, 720);
                $immaginePiccolaUrl = $imageGen->genera($dati['immagine_piccola_prompt'], 512, 512);
            } catch (Exception $e) {
                logDB($this->db, 'generazione', '[3/3] Immagini non generate: ' . $e->getMessage(), 'warning');
            }
        }

        logDB($this->db, 'generazione', '[DB] Salvataggio articolo...', 'info');
        // Genera slug unico
        $slug = $this->slugUnico($dati['titolo']);

        // Calcola tempo lettura
        $tempoLettura = $dati['tempo_lettura'] ?? calcolaTempoLettura($dati['contenuto']);

        $stmt = $this->db->prepare(
            'INSERT INTO articoli
            (titolo_estratto_id, titolo_finale, slug, contenuto, excerpt, meta_description, keywords, categoria, fonte_url, tempo_lettura, immagine_url, immagine_alt, immagine_piccola_url, immagine_piccola_alt, stato)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "draft")'
        );

        $stmt->bind_param(
            'issssssssissss',
            $titolo_estratto_id,
            $dati['titolo'],
            $slug,
            $dati['contenuto'],
            $dati['excerpt'],
            $dati['meta_description'],
            $dati['keywords'],
            $titolo['categoria'],
            $titolo['url_originale'],
            $tempoLettura,
            $immagineGrandeUrl,
            $immagineAlt,
            $immaginePiccolaUrl,
            $immaginePiccolaAlt
        );
        if (!$stmt->execute()) {
            throw new Exception("INSERT articolo fallita: " . $stmt->error);
        }
        $articolo_id = $this->db->insert_id;

        // Segna titolo come elaborato
        $s2 = $this->db->prepare("UPDATE titoli_estratti SET stato = 'elaborato' WHERE id = ?");
        $s2->bind_param('i', $titolo_estratto_id);
        $s2->execute();

        // Schedula pubblicazione
        $this->pianificaPubblicazione($articolo_id);

        logDB($this->db, 'generazione', "Articolo #$articolo_id creato: {$dati['titolo']}", 'success', $articolo_id);

        return $articolo_id;
    }

    private function slugUnico($titolo) {
        $base = generaSlug($titolo);
        $slug = $base;
        $i = 1;
        $check = $this->db->prepare('SELECT id FROM articoli WHERE slug = ?');
        while (true) {
            $check->bind_param('s', $slug);
            $check->execute();
            $check->store_result();
            if ($check->num_rows === 0) break;
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function pianificaPubblicazione($articolo_id) {
        $ora = ORARIO_PUBBLICAZIONE;
        $dataPub = date('Y-m-d') . ' ' . $ora . ':00';

        // Se l'orario è già passato, schedula per domani
        if (strtotime($dataPub) <= time()) {
            $dataPub = date('Y-m-d', strtotime('+1 day')) . ' ' . $ora . ':00';
        }

        $stmt = $this->db->prepare(
            "UPDATE articoli SET data_scheduling = ?, stato = 'schedulato' WHERE id = ?"
        );
        $stmt->bind_param('si', $dataPub, $articolo_id);
        $stmt->execute();
    }

    public function pubblicaSchedulati() {
        $result = $this->db->query(
            "SELECT id FROM articoli WHERE stato = 'schedulato' AND data_scheduling <= NOW()"
        );
        $pubblicati = 0;
        while ($row = $result->fetch_assoc()) {
            $stmt = $this->db->prepare(
                "UPDATE articoli SET stato = 'pubblicato', data_pubblicazione = NOW() WHERE id = ?"
            );
            $stmt->bind_param('i', $row['id']);
            $stmt->execute();
            logDB($this->db, 'pubblicazione', "Articolo #{$row['id']} pubblicato", 'success', $row['id']);
            $pubblicati++;
        }
        return $pubblicati;
    }
}
