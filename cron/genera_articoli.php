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
require_once BASE_DIR . '/includes/SocialSharer.php';

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

    // STEP 2: Genera 1 notizia + 1 approfondimento evergreen al giorno
    $model = $db->query("SELECT valore FROM configurazioni WHERE chiave = 'claude_model'")->fetch_row()[0] ?? CLAUDE_MODEL;
    $generator = new ArticleGenerator($database, CLAUDE_API_KEY, $model);

    $evergreenCats = ['Investimenti', 'Risparmio', 'Pensione & Previdenza', 'Fiscalità', 'ETF & Fondi', 'Previdenza', 'Guide'];
    $placeholders  = implode(',', array_fill(0, count($evergreenCats), '"' . implode('","', $evergreenCats) . '"'));
    // Costruzione sicura per IN clause (valori hardcoded, nessun input utente)
    $evergreenIn = "'" . implode("','", array_map(fn($c) => $db->real_escape_string($c), $evergreenCats)) . "'";

    // Ratio 2:1 — 2 notizie per ogni 1 evergreen
    $da_generare = [
        'notizia_1'        => "SELECT id FROM titoli_estratti WHERE stato = 'nuovo' AND categoria NOT IN ($evergreenIn) ORDER BY data_estrazione ASC LIMIT 1",
        'notizia_2'        => "SELECT id FROM titoli_estratti WHERE stato = 'nuovo' AND categoria NOT IN ($evergreenIn) ORDER BY data_estrazione ASC LIMIT 1 OFFSET 1",
        'approfondimento'  => "SELECT id FROM titoli_estratti WHERE stato = 'nuovo' AND categoria IN ($evergreenIn) ORDER BY data_estrazione ASC LIMIT 1",
    ];

    cronLog('--- Generazione (2 notizie + 1 approfondimento) ---');
    $generati = 0;

    foreach ($da_generare as $tipo => $sql) {
        $row = $db->query($sql)->fetch_assoc();
        if (!$row) {
            // Fallback: prendi qualsiasi titolo disponibile
            $row = $db->query("SELECT id FROM titoli_estratti WHERE stato = 'nuovo' ORDER BY data_estrazione ASC LIMIT 1")->fetch_assoc();
            if (!$row) {
                cronLog("  [$tipo] Nessun titolo disponibile in coda.");
                continue;
            }
            cronLog("  [$tipo] Nessun titolo specifico, uso il primo disponibile.");
        }
        try {
            $articolo_id = $generator->generaArticolo($row['id']);
            cronLog("  [$tipo] Articolo #$articolo_id generato con successo.");
            $generati++;
        } catch (Exception $e) {
            cronLog("  [$tipo] ERRORE: " . $e->getMessage());
        }
    }

    // STEP 3: Pubblica schedulati
    cronLog('--- Pubblicazione schedulati ---');
    $pubblicati = $generator->pubblicaSchedulati();
    cronLog("  Articoli pubblicati: " . count($pubblicati));

    // STEP 4: Condividi sui social
    if (!empty($pubblicati)) {
        cronLog('--- Condivisione social ---');
        $sharer = new SocialSharer($db);
        foreach ($pubblicati as $art) {
            $risultati = $sharer->condividi($art);
            foreach ($risultati as $social => $esito) {
                if ($esito === true) {
                    cronLog("  [$social] ✓ Pubblicato: {$art['titolo_finale']}");
                } else {
                    cronLog("  [$social] ERRORE: $esito");
                }
            }
        }
    }

    cronLog("=== CRON COMPLETATO (generati: $generati, pubblicati: " . count($pubblicati) . ") ===");
    cronLog('');

} catch (Exception $e) {
    cronLog('ERRORE FATALE: ' . $e->getMessage());
    exit(1);
}
