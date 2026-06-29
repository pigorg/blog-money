<?php
class Template {
    private $db;

    public function __construct($database) {
        $this->db = $database->getConn();
    }

    public function caricaTemplate($nome = 'default') {
        $stmt = $this->db->prepare("SELECT html, css FROM template WHERE nome = ? AND attivo = 1 LIMIT 1");
        $stmt->bind_param('s', $nome);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function renderArticolo($articolo) {
        $template = $this->caricaTemplate();
        if (!$template) {
            return ['html' => $articolo['contenuto'], 'css' => ''];
        }

        $data = $articolo['data_pubblicazione'] ?? $articolo['data_creazione'];
        $dataFormattata = $data ? date('d M Y', strtotime($data)) : '';

        $html = $template['html'];
        $cerca = [
            '{{titolo}}', '{{data}}', '{{categoria}}', '{{tempo_lettura}}',
            '{{contenuto}}', '{{excerpt}}', '{{immagine_url}}', '{{immagine_alt}}',
            '{{fonte_url}}', '{{slug}}'
        ];
        $sostituisci = [
            htmlspecialchars($articolo['titolo_finale'] ?? ''),
            $dataFormattata,
            htmlspecialchars($articolo['categoria'] ?? ''),
            $articolo['tempo_lettura'] ?? 5,
            $articolo['contenuto'] ?? '',
            htmlspecialchars($articolo['excerpt'] ?? ''),
            htmlspecialchars($articolo['immagine_url'] ?? ''),
            htmlspecialchars($articolo['immagine_alt'] ?? $articolo['titolo_finale'] ?? ''),
            htmlspecialchars($articolo['fonte_url'] ?? '#'),
            htmlspecialchars($articolo['slug'] ?? '')
        ];

        $html = str_replace($cerca, $sostituisci, $html);

        // Blocco immagine condizionale
        if (empty($articolo['immagine_url'])) {
            $html = preg_replace('/{{#immagine}}.*?{{\/immagine}}/s', '', $html);
        } else {
            $html = str_replace(['{{#immagine}}', '{{/immagine}}'], '', $html);
        }

        return ['html' => $html, 'css' => $template['css']];
    }
}
