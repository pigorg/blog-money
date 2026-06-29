<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/Database.php';

loadEnv();

try {
    $database = new Database();
    $database->connect();
    $db = $database->getConn();
} catch (Exception $e) {
    rispondiJSON(['error' => $e->getMessage()], 500);
}
