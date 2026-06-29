<?php
require_once __DIR__ . '/config.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$azione = $_GET['azione'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// GET /api/articoli.php?azione=lista
if ($metodo === 'GET' && $azione === 'lista') {
    $where = '1=1';
    $params = [];
    $types = '';

    if (!empty($_GET['stato'])) {
        $where .= ' AND stato = ?';
        $params[] = $_GET['stato'];
        $types .= 's';
    }
    if (!empty($_GET['categoria'])) {
        $where .= ' AND categoria = ?';
        $params[] = $_GET['categoria'];
        $types .= 's';
    }

    $sql = "SELECT id, titolo_finale, slug, excerpt, categoria, stato, data_creazione, data_pubblicazione, tempo_lettura, visite FROM articoli WHERE $where ORDER BY data_creazione DESC LIMIT 100";
    $stmt = $db->prepare($sql);

    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    rispondiJSON($rows);
}

// GET /api/articoli.php?azione=dettaglio&id=XX
if ($metodo === 'GET' && $azione === 'dettaglio') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare('SELECT * FROM articoli WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) rispondiJSON(['error' => 'Articolo non trovato'], 404);
    rispondiJSON($row);
}

// POST /api/articoli.php  { azione: 'pubblica', id: X }
if ($metodo === 'POST' && ($body['azione'] ?? '') === 'pubblica') {
    $id = (int)($body['id'] ?? 0);
    $stmt = $db->prepare("UPDATE articoli SET stato = 'pubblicato', data_pubblicazione = NOW() WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    logDB($db, 'pubblicazione', "Articolo #$id pubblicato manualmente", 'success', $id);
    rispondiJSON(['ok' => true, 'id' => $id]);
}

// POST /api/articoli.php  { azione: 'draft', id: X }
if ($metodo === 'POST' && ($body['azione'] ?? '') === 'draft') {
    $id = (int)($body['id'] ?? 0);
    $stmt = $db->prepare("UPDATE articoli SET stato = 'draft', data_pubblicazione = NULL WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    rispondiJSON(['ok' => true]);
}

// DELETE /api/articoli.php?id=XX
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare('DELETE FROM articoli WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    rispondiJSON(['ok' => true, 'eliminato' => $id]);
}

rispondiJSON(['error' => 'Azione non valida'], 400);
