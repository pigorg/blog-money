<?php
/**
 * Cron job principale - esegui ogni giorno alle 06:00
 * Setup su cPanel: 0 6 * * * /usr/bin/php /home/user/public_html/cron/genera_articoli.php
 */

define('BASE_DIR', dirname(__DIR__));
define('LOG_FILE', __DIR__ . '/log.txt');

function cronLog($msg) {
    $riga = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents(LOG_FILE, $riga, FILE_APPEND);
    echo $riga;
}

cronLog('=== AVVIO CRON genera_articoli.php ===');

require_once BASE_DIR . '/config.php';
require_once BASE_DIR . '/includes/helpers.php';
require_once BASE_DIR . '/includes/Database.php';
require_once BASE_DIR . '/includes/Scraper.php';
require_once BASE_DIR . '/includes/Generator.php';

if (!CLAUDE_API_KEY) {
    cronLog('ERRORE: CLAUDE_API_KEY non configurata in config.php');
    exit(1);
}

try {
    $database = new Database();
    $database->connect();
    $db = $database->getConn();
    cronLog('Database connesso.');

    // STEP 1: Sincronizza sorgenti
    cronLog('--- Sincronizzazione sorgenti ---');
    $scraper  = new Scraper($database);
    $sorgenti = $db->query("SELECT * FROM sorgenti WHERE attiva = 1")->fetch_all(MYSQLI_ASSOC);

    $totaleNuovi = 0;
    foreach ($sorgenti as $s) {
        try {
            $titoli = $scraper->estraiDaRSS($s['url']);
            $nuovi  = $scraper->salvaTitoli($s['id'], $titoli);
            cronLog("  {$s['nome']}: {$nuovi} nuovi titoli (totale estratti: " . count($titoli) . ")");
            $totaleNuovi += $nuovi;
        } catch (Exception $e) {
            cronLog("  ERRORE {$s['nome']}: " . $e->getMessage());
        }
    }
    cronLog("Totale nuovi titoli: $totaleNuovi");

    // STEP 2: Genera articoli (quanti configurati)
    $articoliAlGiorno = (int)($db->query("SELECT valore FROM configurazioni WHERE chiave = 'articoli_al_giorno'")->fetch_row()[0] ?? 1);
    $model = $db->query("SELECT valore FROM configurazioni WHERE chiave = 'claude_model'")->fetch_row()[0] ?? CLAUDE_MODEL;

    cronLog("--- Generazione ($articoliAlGiorno articolo/i) ---");
    $generator = new ArticleGenerator($database, CLAUDE_API_KEY, $model);

    $generati = 0;
    for ($i = 0; $i < $articoliAlGiorno; $i++) {
        $row = $db->query("SELECT id FROM titoli_estratti WHERE stato = 'nuovo' ORDER BY data_estrazione ASC LIMIT 1")->fetch_assoc();
        if (!$row) {
            cronLog("  Nessun titolo disponibile in coda.");
            break;
        }
        try {
            $articolo_id = $generator->generaArticolo($row['id']);
            cronLog("  Articolo #$articolo_id generato con successo.");
            $generati++;
        } catch (Exception $e) {
            cronLog('  ERRORE generazione: ' . $e->getMessage());
        }
    }

    // STEP 3: Pubblica schedulati
    cronLog('--- Pubblicazione schedulati ---');
    $pubblicati = $generator->pubblicaSchedulati();
    cronLog("  Articoli pubblicati: $pubblicati");

    cronLog("=== CRON COMPLETATO (generati: $generati, pubblicati: $pubblicati) ===");
    cronLog('');

} catch (Exception $e) {
    cronLog('ERRORE FATALE: ' . $e->getMessage());
    exit(1);
}
