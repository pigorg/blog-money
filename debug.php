<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Debug Approfondito - Blog Money</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 1.5rem; font-size: 14px; }
h1 { color: #60a5fa; margin-bottom: 1.5rem; font-size: 1.4rem; }
h2 { color: #93c5fd; margin: 1.5rem 0 .5rem; font-size: 1rem; border-bottom: 1px solid #1e293b; padding-bottom: .3rem; }
table { border-collapse: collapse; width: 100%; margin-bottom: 1rem; }
tr { border-bottom: 1px solid #1e293b; }
td { padding: .4rem .6rem; vertical-align: top; }
td:first-child { color: #94a3b8; width: 35%; }
.ok  { color: #4ade80; }
.err { color: #f87171; font-weight: bold; }
.war { color: #facc15; }
pre { background: #1e293b; padding: .75rem; border-radius: 6px; overflow-x: auto; white-space: pre-wrap; word-break: break-all; font-size: 12px; margin-top: .3rem; }
.badge { display: inline-block; padding: .1rem .4rem; border-radius: 4px; font-size: 11px; }
.note { color: #64748b; font-size: 12px; margin-top: .5rem; }
</style>
</head>
<body>
<h1>🔍 Debug Approfondito - Blog Money</h1>

<?php

// ── helpers ──────────────────────────────────────────────────────────────────
function row($label, $classe, $valore, $extra = '') {
    echo "<tr><td>$label</td><td class='$classe'>$valore" . ($extra ? "<pre>$extra</pre>" : '') . "</td></tr>";
}
function ok($l, $v, $e='')  { row($l, 'ok',  '✅ '.$v, $e); }
function err($l, $v, $e='') { row($l, 'err', '❌ '.$v, $e); }
function war($l, $v, $e='') { row($l, 'war', '⚠️ '.$v,  $e); }

// ── 1. AMBIENTE PHP ───────────────────────────────────────────────────────────
echo '<h2>1. Ambiente PHP</h2><table>';
ok('PHP Version', PHP_VERSION . (version_compare(PHP_VERSION,'7.4','<') ? ' (troppo vecchio!)' : ''));
ok('Server Software', $_SERVER['SERVER_SOFTWARE'] ?? 'n/d');
ok('Document Root', $_SERVER['DOCUMENT_ROOT'] ?? 'n/d');
ok('Script path', __FILE__);
ok('HTTPS', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'attivo' : 'non attivo (rilevato da PHP)');
ok('HTTP_X_FORWARDED_PROTO', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'non presente');

$logFile = ini_get('error_log');
echo "<tr><td>Error log</td><td>$logFile</td></tr>";

$lastErrors = '';
if ($logFile && file_exists($logFile)) {
    $lines = array_slice(file($logFile), -20);
    $lastErrors = htmlspecialchars(implode('', $lines));
}
if ($lastErrors) war('Ultimi errori PHP', 'vedi sotto', $lastErrors);
else ok('Ultimi errori PHP', 'nessuno nel log');
echo '</table>';

// ── 2. .HTACCESS ─────────────────────────────────────────────────────────────
echo '<h2>2. .htaccess attuale sul server</h2>';
$htFile = __DIR__ . '/.htaccess';
if (file_exists($htFile)) {
    echo '<pre>' . htmlspecialchars(file_get_contents($htFile)) . '</pre>';
} else {
    echo '<p class="err">❌ .htaccess non trovato!</p>';
}

// ── 3. API ENDPOINTS ─────────────────────────────────────────────────────────
echo '<h2>3. Test API endpoints (chiamate interne)</h2><table>';

$siteUrl = '';
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
}

$endpoints = [
    'GET /api/articoli.php?azione=lista'          => "$siteUrl/api/articoli.php?azione=lista",
    'GET /api/sorgenti.php?azione=lista'           => "$siteUrl/api/sorgenti.php?azione=lista",
    'GET /api/bot.php?azione=log'                  => "$siteUrl/api/bot.php?azione=log",
    'GET /api/bot.php?azione=titoli_da_elaborare'  => "$siteUrl/api/bot.php?azione=titoli_da_elaborare",
];

foreach ($endpoints as $label => $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER         => true,
    ]);
    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    $body    = substr($raw, $headerSize);
    $preview = htmlspecialchars(substr(trim($body), 0, 300));

    if ($curlErr) {
        err($label, "cURL error", $curlErr);
    } elseif ($httpCode === 200) {
        $json = json_decode($body, true);
        $jsonOk = json_last_error() === JSON_ERROR_NONE;
        ok($label, "HTTP $httpCode" . ($jsonOk ? " · JSON valido" : " · NON è JSON!"), $preview);
    } elseif ($httpCode >= 300 && $httpCode < 400) {
        err($label, "HTTP $httpCode REDIRECT — .htaccess problem!", $preview);
    } else {
        err($label, "HTTP $httpCode", $preview);
    }
}
echo '</table>';

// ── 4. CDN RAGGIUNGIBILI ──────────────────────────────────────────────────────
echo '<h2>4. CDN usate dall\'admin (raggiungibili dal server?)</h2><table>';
$cdns = [
    'Vue 3 (unpkg)'       => 'https://unpkg.com/vue@3/dist/vue.global.prod.js',
    'Axios (unpkg)'       => 'https://unpkg.com/axios/dist/axios.min.js',
    'Tailwind CSS (CDN)'  => 'https://cdn.tailwindcss.com',
    'FontAwesome (cdnjs)' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
];
foreach ($cdns as $nome => $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_NOBODY => true]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    if ($cerr)       err($nome, "cURL: $cerr");
    elseif ($code == 200) ok($nome, "HTTP $code");
    else             war($nome, "HTTP $code");
}
echo '</table>';

// ── 5. FILE ADMIN ────────────────────────────────────────────────────────────
echo '<h2>5. File admin</h2><table>';
$adminFiles = [
    'admin/index.html' => __DIR__ . '/admin/index.html',
    'admin/js/app.js'  => __DIR__ . '/admin/js/app.js',
];
foreach ($adminFiles as $nome => $path) {
    if (file_exists($path)) {
        $size = filesize($path);
        ok($nome, "esiste · $size bytes");
    } else {
        err($nome, 'FILE MANCANTE');
    }
}

// Test HTTP diretto sull'admin
if ($siteUrl) {
    $ch = curl_init("$siteUrl/admin/");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADER => true]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hSz  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $body = substr($raw, $hSz);
    if ($code === 200) ok("GET /admin/ (HTTP)", "HTTP $code · " . strlen($body) . " bytes");
    elseif ($code >= 300 && $code < 400) {
        $loc = '';
        preg_match('/Location:\s*(.+)/i', substr($raw, 0, $hSz), $m);
        $loc = trim($m[1] ?? '');
        err("GET /admin/ (HTTP)", "HTTP $code REDIRECT → $loc — è il redirect HTTPS!");
    }
    else err("GET /admin/ (HTTP)", "HTTP $code", htmlspecialchars(substr($body, 0, 200)));
}
echo '</table>';

// ── 6. SIMULAZIONE ADMIN (mini Vue test) ─────────────────────────────────────
echo '<h2>6. Mini-test admin in questa pagina</h2>';
echo '<div id="vue-test" style="background:#1e293b;padding:.75rem;border-radius:6px;">Caricamento Vue...</div>';
?>

<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js" onerror="document.getElementById('vue-test').innerHTML='<span style=color:#f87171>❌ Vue.js CDN NON caricato — problema di rete o CSP!</span>'"></script>
<script>
if (typeof Vue !== 'undefined') {
    const { createApp } = Vue;
    createApp({
        data() { return { msg: '✅ Vue 3 carica e monta correttamente!' }; },
        template: '<span style="color:#4ade80">{{ msg }}</span>'
    }).mount('#vue-test');
}
</script>

<h2 style="margin-top:1.5rem">7. Errori JavaScript (apri console F12 per dettagli)</h2>
<div id="js-errors" style="background:#1e293b;padding:.75rem;border-radius:6px;color:#4ade80">Nessun errore JS rilevato da questo script.</div>
<script>
window.onerror = function(msg, src, line, col, err) {
    document.getElementById('js-errors').innerHTML =
        '<span style="color:#f87171">❌ JS Error: ' + msg + ' (' + src + ':' + line + ')</span>';
};
</script>

<p style="margin-top:2rem;color:#475569;font-size:12px">⚠️ Elimina questo file dal server appena hai finito il debug.</p>
</body>
</html>
