<?php
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/includes/Scraper.php';
require_once dirname(__DIR__) . '/includes/Generator.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$azione = $_GET['azione'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// GET /api/bot.php?azione=log
if ($metodo === 'GET' && $azione === 'log') {
    $rows = $db->query(
        'SELECT * FROM log_elaborazioni ORDER BY created_at DESC LIMIT 50'
    )->fetch_all(MYSQLI_ASSOC);
    rispondiJSON($rows);
}

// GET /api/bot.php?azione=titoli_da_elaborare
if ($metodo === 'GET' && $azione === 'titoli_da_elaborare') {
    $rows = $db->query(
        "SELECT * FROM titoli_estratti WHERE stato = 'nuovo' ORDER BY data_estrazione DESC LIMIT 20"
    )->fetch_all(MYSQLI_ASSOC);
    rispondiJSON($rows);
}

// POST /api/bot.php { azione: 'genera' }
if ($metodo === 'POST' && ($body['azione'] ?? '') === 'genera') {
    $apiKey = CLAUDE_API_KEY;
    if (!$apiKey) {
        rispondiJSON(['tipo' => 'error', 'messaggio' => 'CLAUDE_API_KEY non configurata in config.php'], 500);
    }

    // Cerca titolo_id specifico o prendi il primo disponibile
    $titoloId = (int)($body['titolo_id'] ?? 0);

    if ($titoloId === 0) {
        $row = $db->query("SELECT id FROM titoli_estratti WHERE stato = 'nuovo' ORDER BY data_estrazione ASC LIMIT 1")->fetch_assoc();
        if (!$row) {
            rispondiJSON(['tipo' => 'warning', 'messaggio' => 'Nessun titolo da elaborare. Sincronizza le sorgenti prima.']);
        }
        $titoloId = $row['id'];
    }

    try {
        $generator = new Generator($database, $apiKey, CLAUDE_MODEL);
        $articolo_id = $generator->generaArticolo($titoloId);
        rispondiJSON([
            'tipo' => 'success',
            'messaggio' => "Articolo #$articolo_id generato con successo!",
            'articolo_id' => $articolo_id
        ]);
    } catch (Exception $e) {
        logDB($db, 'errore', 'Generazione fallita: ' . $e->getMessage(), 'error');
        rispondiJSON(['tipo' => 'error', 'messaggio' => $e->getMessage()], 500);
    }
}

// POST /api/bot.php { azione: 'sincronizza_tutte' }
if ($metodo === 'POST' && ($body['azione'] ?? '') === 'sincronizza_tutte') {
    $sorgenti = $db->query("SELECT * FROM sorgenti WHERE attiva = 1")->fetch_all(MYSQLI_ASSOC);
    $scraper = new Scraper($database);
    $totale = 0;

    foreach ($sorgenti as $s) {
        try {
            $titoli = $scraper->estraiDaRSS($s['url']);
            $nuovi  = $scraper->salvaTitoli($s['id'], $titoli);
            logDB($db, 'estrazione', "{$s['nome']}: $nuovi nuovi titoli");
            $totale += $nuovi;
        } catch (Exception $e) {
            logDB($db, 'errore', "Estrazione {$s['nome']} fallita: " . $e->getMessage(), 'error');
        }
    }

    rispondiJSON(['tipo' => 'success', 'messaggio' => "Sincronizzazione completata. $totale nuovi titoli.", 'nuovi' => $totale]);
}

// POST /api/bot.php { azione: 'pubblica_schedulati' }
if ($metodo === 'POST' && ($body['azione'] ?? '') === 'pubblica_schedulati') {
    $generator = new Generator($database, CLAUDE_API_KEY ?: 'placeholder', CLAUDE_MODEL);
    $n = $generator->pubblicaSchedulati();
    rispondiJSON(['tipo' => 'success', 'messaggio' => "$n articoli pubblicati.", 'pubblicati' => $n]);
}

rispondiJSON(['error' => 'Azione non valida'], 400);
