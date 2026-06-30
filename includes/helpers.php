<?php
function generaSlug($testo) {
    $slug = mb_strtolower(trim($testo), 'UTF-8');

    $map = [
        'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a',
        'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
        'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
        'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
    ];
    $slug = strtr($slug, $map);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return substr($slug, 0, 80);
}

function calcolaTempoLettura($testo) {
    $parole = str_word_count(strip_tags($testo));
    return max(1, (int) round($parole / 200));
}

function rispondiJSON($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function urlArticolo($slug) {
    return '/articolo/' . $slug;
}

function logDB($db, $tipo, $messaggio, $status = 'success', $articolo_id = null) {
    $stmt = $db->prepare(
        'INSERT INTO log_elaborazioni (tipo, articolo_id, messaggio, status) VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('siss', $tipo, $articolo_id, $messaggio, $status);
    $stmt->execute();
}
