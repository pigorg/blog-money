<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Template.php';

$database = new Database();
$database->connect();
$db = $database->getConn();

$slug = trim($_GET['slug'] ?? '');
$id   = (int)($_GET['id'] ?? 0);

if ($slug) {
    $stmt = $db->prepare("SELECT * FROM articoli WHERE slug = ? AND stato = 'pubblicato'");
    $stmt->bind_param('s', $slug);
} elseif ($id) {
    $stmt = $db->prepare("SELECT * FROM articoli WHERE id = ?");
    $stmt->bind_param('i', $id);
} else {
    header('Location: index.php');
    exit;
}

$stmt->execute();
$articolo = $stmt->get_result()->fetch_assoc();

if (!$articolo) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body><h1>Articolo non trovato</h1><a href="index.php">Torna alla home</a></body></html>';
    exit;
}

// Incrementa visite
$vs = $db->prepare('UPDATE articoli SET visite = visite + 1 WHERE id = ?');
$vs->bind_param('i', $articolo['id']);
$vs->execute();

$template = new Template($database);
$rendered = $template->renderArticolo($articolo);

$data = $articolo['data_pubblicazione'] ? date('Y-m-d', strtotime($articolo['data_pubblicazione'])) : '';
$dataLeggibile = $articolo['data_pubblicazione'] ? date('d M Y', strtotime($articolo['data_pubblicazione'])) : '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($articolo['titolo_finale']) ?> - Blog Money</title>
    <meta name="description" content="<?= htmlspecialchars($articolo['meta_description'] ?? $articolo['excerpt'] ?? '') ?>">
    <?php if ($articolo['keywords']): ?>
    <meta name="keywords" content="<?= htmlspecialchars($articolo['keywords']) ?>">
    <?php endif; ?>

    <!-- Open Graph -->
    <meta property="og:title" content="<?= htmlspecialchars($articolo['titolo_finale']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($articolo['excerpt'] ?? '') ?>">
    <meta property="og:type" content="article">
    <?php if ($articolo['immagine_url']): ?>
    <meta property="og:image" content="<?= htmlspecialchars($articolo['immagine_url']) ?>">
    <?php endif; ?>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/public.css">
    <?php if ($rendered['css']): ?>
    <style><?= $rendered['css'] ?></style>
    <?php endif; ?>
</head>
<body class="bg-gray-50">

    <header class="bg-white shadow-sm sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2 text-xl font-bold text-gray-800">
                <i class="fas fa-chart-line text-blue-600"></i> Blog Money
            </a>
            <a href="index.php" class="text-sm text-gray-500 hover:text-blue-600">
                <i class="fas fa-arrow-left mr-1"></i> Tutti gli articoli
            </a>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-10">

        <!-- Breadcrumb -->
        <nav class="text-sm text-gray-400 mb-6">
            <a href="index.php" class="hover:text-blue-600">Home</a>
            <span class="mx-2">/</span>
            <a href="index.php?categoria=<?= urlencode($articolo['categoria']) ?>" class="hover:text-blue-600">
                <?= htmlspecialchars($articolo['categoria']) ?>
            </a>
            <span class="mx-2">/</span>
            <span class="text-gray-600"><?= htmlspecialchars(mb_substr($articolo['titolo_finale'], 0, 40)) ?>...</span>
        </nav>

        <article class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <?php if ($articolo['immagine_url']): ?>
            <img src="<?= htmlspecialchars($articolo['immagine_url']) ?>"
                 alt="<?= htmlspecialchars($articolo['immagine_alt'] ?? $articolo['titolo_finale']) ?>"
                 class="w-full h-64 object-cover" loading="lazy">
            <?php endif; ?>

            <div class="p-6 md:p-10">
                <div class="flex flex-wrap gap-3 items-center mb-4">
                    <span class="bg-blue-100 text-blue-700 text-xs px-3 py-1 rounded-full font-semibold">
                        <?= htmlspecialchars($articolo['categoria']) ?>
                    </span>
                    <?php if ($data): ?>
                    <span class="text-gray-400 text-sm flex items-center gap-1">
                        <i class="fas fa-calendar"></i> <?= $dataLeggibile ?>
                    </span>
                    <?php endif; ?>
                    <span class="text-gray-400 text-sm flex items-center gap-1">
                        <i class="fas fa-clock"></i> <?= $articolo['tempo_lettura'] ?> min di lettura
                    </span>
                </div>

                <h1 class="text-3xl font-bold text-gray-900 mb-4 leading-tight">
                    <?= htmlspecialchars($articolo['titolo_finale']) ?>
                </h1>

                <?php if ($articolo['excerpt']): ?>
                <p class="text-lg text-gray-500 italic mb-8 pb-6 border-b">
                    <?= htmlspecialchars($articolo['excerpt']) ?>
                </p>
                <?php endif; ?>

                <div class="prose-content">
                    <?= $articolo['contenuto'] ?>
                </div>

                <?php if ($articolo['fonte_url']): ?>
                <div class="mt-10 pt-6 border-t">
                    <p class="text-sm text-gray-500">
                        <i class="fas fa-link mr-2 text-blue-400"></i>
                        <strong>Fonte originale:</strong>
                        <a href="<?= htmlspecialchars($articolo['fonte_url']) ?>" target="_blank" rel="noopener noreferrer"
                            class="text-blue-600 hover:underline ml-1">
                            <?= htmlspecialchars(parse_url($articolo['fonte_url'], PHP_URL_HOST) ?? $articolo['fonte_url']) ?>
                        </a>
                    </p>
                    <p class="text-xs text-gray-400 mt-2">
                        Questo articolo è stato generato con intelligenza artificiale a partire da fonti verificate, a scopo puramente informativo.
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </article>

        <!-- Bottone back -->
        <div class="mt-8 text-center">
            <a href="index.php" class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-xl font-semibold hover:bg-blue-700 transition">
                <i class="fas fa-arrow-left"></i> Tutti gli articoli
            </a>
        </div>
    </main>

    <footer class="mt-16 border-t bg-white py-8">
        <div class="max-w-6xl mx-auto px-4 text-center text-gray-400 text-sm">
            &copy; <?= date('Y') ?> Blog Money · Contenuti generati con AI per scopi informativi
        </div>
    </footer>

</body>
</html>
