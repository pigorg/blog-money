<?php
/**
 * Sincronizza solo le sorgenti RSS (senza generare articoli)
 * Utile se vuoi eseguirlo più frequentemente
 * Setup cPanel: 0 */6 * * * /usr/bin/php /home/user/public_html/cron/sincronizza_sorgenti.php
 */

define('BASE_DIR', dirname(__DIR__));

require_once BASE_DIR . '/includes/helpers.php';
require_once BASE_DIR . '/includes/Database.php';
require_once BASE_DIR . '/includes/Scraper.php';

loadEnv();

$log = function($msg) {
    echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
};

try {
    $database = new Database();
    $database->connect();
    $db = $database->getConn();

    $scraper  = new Scraper($database);
    $sorgenti = $db->query("SELECT * FROM sorgenti WHERE attiva = 1")->fetch_all(MYSQLI_ASSOC);

    foreach ($sorgenti as $s) {
        try {
            $titoli = $scraper->estraiDaRSS($s['url']);
            $nuovi  = $scraper->salvaTitoli($s['id'], $titoli);
            $log("{$s['nome']}: $nuovi nuovi titoli");
        } catch (Exception $e) {
            $log("ERRORE {$s['nome']}: " . $e->getMessage());
        }
    }

    $log('Sincronizzazione completata.');

} catch (Exception $e) {
    echo 'ERRORE: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
