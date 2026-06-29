<?php
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/Database.php';
loadEnv();

$database = new Database();
$database->connect();
$db = $database->getConn();

$categoria = trim($_GET['categoria'] ?? '');
$pagina    = max(1, (int)($_GET['p'] ?? 1));
$perPagina = 12;
$offset    = ($pagina - 1) * $perPagina;

$where  = "stato = 'pubblicato'";
$params = [];
$types  = '';

if ($categoria) {
    $where  .= ' AND categoria = ?';
    $params[] = $categoria;
    $types   .= 's';
}

// Conteggio
$countStmt = $db->prepare("SELECT COUNT(*) FROM articoli WHERE $where");
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$countStmt->bind_result($totale);
$countStmt->fetch();
$countStmt->close();
$totalePagine = (int) ceil($totale / $perPagina);

// Articoli
$sql = "SELECT id, titolo_finale, slug, excerpt, categoria, data_pubblicazione, tempo_lettura, immagine_url
        FROM articoli WHERE $where ORDER BY data_pubblicazione DESC LIMIT ? OFFSET ?";

$stmt = $db->prepare($sql);
if ($params) {
    $params[] = $perPagina;
    $params[] = $offset;
    $stmt->bind_param($types . 'ii', ...$params);
} else {
    $stmt->bind_param('ii', $perPagina, $offset);
}
$stmt->execute();
$articoli = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Categorie disponibili
$cats = $db->query("SELECT DISTINCT categoria FROM articoli WHERE stato = 'pubblicato' ORDER BY categoria ASC")->fetch_all(MYSQLI_ASSOC);

$siteUrl = $_ENV['SITE_URL'] ?? '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Money - Notizie e Consigli Finanziari</title>
    <meta name="description" content="Articoli di finanza, investimenti, criptovalute e risparmio aggiornati ogni giorno.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/public.css">
</head>
<body class="bg-gray-50">

    <header class="bg-white shadow-sm sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2 text-xl font-bold text-gray-800">
                <i class="fas fa-chart-line text-blue-600"></i> Blog Money
            </a>
            <nav class="hidden sm:flex gap-4 text-sm text-gray-600">
                <a href="index.php" class="hover:text-blue-600">Home</a>
                <?php foreach ($cats as $c): ?>
                <a href="?categoria=<?= urlencode($c['categoria']) ?>" class="hover:text-blue-600 <?= $categoria === $c['categoria'] ? 'text-blue-600 font-semibold' : '' ?>">
                    <?= htmlspecialchars($c['categoria']) ?>
                </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-10">

        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">
                <?= $categoria ? htmlspecialchars($categoria) : 'Ultime Notizie Finanziarie' ?>
            </h1>
            <p class="text-gray-500 mt-1">Articoli generati con AI · Aggiornati ogni giorno</p>
        </div>

        <?php if (empty($articoli)): ?>
        <div class="text-center py-20 text-gray-400">
            <i class="fas fa-newspaper text-5xl mb-4 opacity-30"></i>
            <p class="text-xl">Nessun articolo pubblicato ancora.</p>
        </div>
        <?php else: ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($articoli as $a): ?>
            <article class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition group">
                <?php if ($a['immagine_url']): ?>
                <img src="<?= htmlspecialchars($a['immagine_url']) ?>" alt="<?= htmlspecialchars($a['titolo_finale']) ?>"
                    class="w-full h-48 object-cover group-hover:scale-105 transition duration-300" loading="lazy">
                <?php else: ?>
                <div class="w-full h-48 bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center">
                    <i class="fas fa-chart-bar text-4xl text-blue-200"></i>
                </div>
                <?php endif; ?>

                <div class="p-5">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full font-medium">
                            <?= htmlspecialchars($a['categoria']) ?>
                        </span>
                        <span class="text-xs text-gray-400">
                            <i class="fas fa-clock mr-1"></i><?= $a['tempo_lettura'] ?> min
                        </span>
                    </div>

                    <h2 class="font-bold text-gray-900 text-lg mb-2 line-clamp-2 group-hover:text-blue-600 transition">
                        <a href="articolo.php?slug=<?= urlencode($a['slug']) ?>">
                            <?= htmlspecialchars($a['titolo_finale']) ?>
                        </a>
                    </h2>

                    <p class="text-gray-500 text-sm line-clamp-3 mb-4">
                        <?= htmlspecialchars($a['excerpt'] ?? '') ?>
                    </p>

                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-400">
                            <i class="fas fa-calendar mr-1"></i>
                            <?= $a['data_pubblicazione'] ? date('d M Y', strtotime($a['data_pubblicazione'])) : '' ?>
                        </span>
                        <a href="articolo.php?slug=<?= urlencode($a['slug']) ?>"
                            class="text-sm text-blue-600 font-medium hover:underline">
                            Leggi <i class="fas fa-arrow-right ml-1 text-xs"></i>
                        </a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- PAGINAZIONE -->
        <?php if ($totalePagine > 1): ?>
        <div class="flex justify-center gap-2 mt-10">
            <?php for ($i = 1; $i <= $totalePagine; $i++): ?>
            <a href="?p=<?= $i ?><?= $categoria ? '&categoria=' . urlencode($categoria) : '' ?>"
                class="w-10 h-10 flex items-center justify-center rounded-lg text-sm font-medium transition
                <?= $i === $pagina ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100 border' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </main>

    <footer class="mt-16 border-t bg-white py-8">
        <div class="max-w-6xl mx-auto px-4 text-center text-gray-400 text-sm">
            &copy; <?= date('Y') ?> Blog Money · Contenuti generati con AI per scopi informativi
        </div>
    </footer>

</body>
</html>
