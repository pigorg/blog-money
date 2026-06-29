<?php
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/includes/Scraper.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$azione = $_GET['azione'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// GET /api/sorgenti.php?azione=lista
if ($metodo === 'GET' && $azione === 'lista') {
    $rows = $db->query('SELECT * FROM sorgenti ORDER BY nome ASC')->fetch_all(MYSQLI_ASSOC);
    rispondiJSON($rows);
}

// POST /api/sorgenti.php { azione: 'aggiungi', nome, url, tipo }
if ($metodo === 'POST' && ($body['azione'] ?? '') === 'aggiungi') {
    $nome = trim($body['nome'] ?? '');
    $url  = trim($body['url'] ?? '');
    $tipo = $body['tipo'] ?? 'rss';
    if (!$nome || !$url) rispondiJSON(['error' => 'nome e url richiesti'], 400);

    $stmt = $db->prepare('INSERT INTO sorgenti (nome, url, tipo) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $nome, $url, $tipo);
    $stmt->execute();
    rispondiJSON(['ok' => true, 'id' => $db->insert_id]);
}

// POST /api/sorgenti.php { azione: 'sincronizza', id: X }
if ($metodo === 'POST' && ($body['azione'] ?? '') === 'sincronizza') {
    $id = (int)($body['id'] ?? 0);
    $stmt = $db->prepare('SELECT * FROM sorgenti WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $sorgente = $stmt->get_result()->fetch_assoc();
    if (!$sorgente) rispondiJSON(['error' => 'Sorgente non trovata'], 404);

    try {
        $scraper = new Scraper($database);
        $titoli = $scraper->estraiDaRSS($sorgente['url']);
        $nuovi  = $scraper->salvaTitoli($id, $titoli);
        logDB($db, 'estrazione', "Sorgente {$sorgente['nome']}: $nuovi nuovi titoli estratti");
        rispondiJSON(['ok' => true, 'nuovi' => $nuovi, 'totale' => count($titoli)]);
    } catch (Exception $e) {
        logDB($db, 'errore', "Estrazione fallita ({$sorgente['nome']}): " . $e->getMessage(), 'error');
        rispondiJSON(['error' => $e->getMessage()], 500);
    }
}

// DELETE /api/sorgenti.php?id=XX
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare('DELETE FROM sorgenti WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    rispondiJSON(['ok' => true]);
}

rispondiJSON(['error' => 'Azione non valida'], 400);
