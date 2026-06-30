<?php
/**
 * Test generazione singolo articolo via CLI.
 * Eseguire da cPanel Terminal:
 *   php /home2/istorela/public_html/cron/test_genera.php
 */
define('BASE_DIR', dirname(__DIR__));

require_once BASE_DIR . '/config.php';
require_once BASE_DIR . '/includes/helpers.php';
require_once BASE_DIR . '/includes/Database.php';
require_once BASE_DIR . '/includes/Generator.php';

$t = function($msg) { echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL; };

$t('=== TEST GENERAZIONE ===');
$t('PHP ' . PHP_VERSION . ' · max_execution_time=' . ini_get('max_execution_time'));

$database = new Database();
$database->connect();
$db = $database->getConn();
$t('DB connesso: ' . DB_NAME);

$row = $db->query("SELECT id, titolo_originale FROM titoli_estratti WHERE stato = 'nuovo' ORDER BY id ASC LIMIT 1")->fetch_assoc();
if (!$row) {
    $t('NESSUN titolo in coda. Sincronizza prima le sorgenti.');
    exit(1);
}
$t("Titolo selezionato #{$row['id']}: {$row['titolo_originale']}");

try {
    $gen = new ArticleGenerator($database, CLAUDE_API_KEY, CLAUDE_MODEL);
    $t('Avvio generazione...');
    $id = $gen->generaArticolo($row['id']);
    $t("✅ ARTICOLO #$id CREATO CON SUCCESSO!");
} catch (Exception $e) {
    $t('❌ ERRORE: ' . $e->getMessage());
    exit(1);
}
