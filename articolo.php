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
    header('Location: /');
    exit;
}

$stmt->execute();
$articolo = $stmt->get_result()->fetch_assoc();

if (!$articolo) {
    http_response_code(404);
    require_once __DIR__ . '/includes/site_header.php';
    echo '<div class="max-w-3xl mx-auto px-4 py-32 text-center"><h1 class="text-2xl font-bold text-gray-400">Articolo non trovato</h1><a href="/" class="text-blue-600 mt-4 inline-block">← Torna alla home</a></div>';
    require_once __DIR__ . '/includes/site_footer.php';
    exit;
}

// Incrementa visite
$vs = $db->prepare('UPDATE articoli SET visite = visite + 1 WHERE id = ?');
$vs->bind_param('i', $articolo['id']);
$vs->execute();

$template      = new Template($database);
$rendered      = $template->renderArticolo($articolo);
$dataLeggibile = $articolo['data_pubblicazione'] ? date('d M Y', strtotime($articolo['data_pubblicazione'])) : '';
$dataISO       = $articolo['data_pubblicazione'] ? date('c', strtotime($articolo['data_pubblicazione'])) : '';
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
    <link rel="canonical" href="<?= SITE_URL ?>/articolo.php?slug=<?= urlencode($articolo['slug']) ?>">

    <meta property="og:title" content="<?= htmlspecialchars($articolo['titolo_finale']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($articolo['excerpt'] ?? '') ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= SITE_URL ?>/articolo.php?slug=<?= urlencode($articolo['slug']) ?>">
    <meta property="og:site_name" content="Blog Money">
    <meta property="article:published_time" content="<?= $dataISO ?>">
    <meta property="article:section" content="<?= htmlspecialchars($articolo['categoria']) ?>">
    <?php if ($articolo['immagine_url']): ?>
    <meta property="og:image" content="<?= htmlspecialchars($articolo['immagine_url']) ?>">
    <meta property="og:image:width" content="1280">
    <meta property="og:image:height" content="720">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="<?= htmlspecialchars($articolo['immagine_url']) ?>">
    <?php else: ?>
    <meta name="twitter:card" content="summary">
    <?php endif; ?>
    <meta name="twitter:title" content="<?= htmlspecialchars($articolo['titolo_finale']) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($articolo['excerpt'] ?? '') ?>">

    <script type="application/ld+json">
    <?= json_encode([
        '@context'        => 'https://schema.org',
        '@type'           => 'Article',
        'headline'        => $articolo['titolo_finale'],
        'description'     => $articolo['excerpt'] ?? '',
        'image'           => $articolo['immagine_url'] ? [$articolo['immagine_url']] : [],
        'keywords'        => $articolo['keywords'] ?? '',
        'articleSection'  => $articolo['categoria'],
        'inLanguage'      => 'it-IT',
        'datePublished'   => $dataISO,
        'dateModified'    => $dataISO,
        'author'          => ['@type' => 'Organization', 'name' => 'Blog Money', 'url' => SITE_URL],
        'publisher'       => ['@type' => 'Organization', 'name' => 'Blog Money', 'url' => SITE_URL],
        'mainEntityOfPage'=> ['@type' => 'WebPage', '@id' => SITE_URL . '/articolo.php?slug=' . urlencode($articolo['slug'])],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    </script>
    <script type="application/ld+json">
    <?= json_encode([
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home',                    'item' => SITE_URL . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $articolo['categoria'],    'item' => SITE_URL . '/?categoria=' . urlencode($articolo['categoria'])],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $articolo['titolo_finale']],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/public.css">
    <style>
        body { font-family: 'Georgia', serif; }
        .sans { font-family: system-ui, -apple-system, sans-serif; }
        .hero-gradient { background: linear-gradient(to top, rgba(0,0,0,.88) 0%, rgba(0,0,0,.3) 55%, transparent 100%); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        <?php if ($rendered['css']): echo $rendered['css']; endif; ?>
    </style>
</head>
<body class="bg-white text-gray-900">

<?php require_once __DIR__ . '/includes/site_header.php'; ?>

<!-- HERO IMMAGINE -->
<div class="w-full relative" style="height: 420px;">
    <?php if ($articolo['immagine_url']): ?>
    <img src="<?= htmlspecialchars($articolo['immagine_url']) ?>"
         alt="<?= htmlspecialchars($articolo['immagine_alt'] ?? $articolo['titolo_finale']) ?>"
         class="w-full h-full object-cover">
    <?php else: ?>
    <div class="w-full h-full bg-gradient-to-br from-slate-800 via-blue-900 to-slate-900 flex items-center justify-center">
        <i class="fas fa-chart-line text-9xl text-blue-400/20"></i>
    </div>
    <?php endif; ?>
    <div class="hero-gradient absolute inset-0"></div>

    <!-- Breadcrumb sopra il titolo -->
    <div class="absolute bottom-0 left-0 right-0 max-w-3xl mx-auto px-4 pb-8">
        <nav class="sans text-xs text-white/50 mb-3 flex items-center gap-1.5">
            <a href="/" class="hover:text-white transition-colors">Home</a>
            <span>/</span>
            <a href="/?categoria=<?= urlencode($articolo['categoria']) ?>" class="hover:text-white transition-colors">
                <?= htmlspecialchars($articolo['categoria']) ?>
            </a>
        </nav>
        <div class="flex flex-wrap items-center gap-3 mb-3 sans">
            <span class="bg-blue-600 text-white text-xs font-bold px-3 py-1 rounded uppercase tracking-wide">
                <?= htmlspecialchars($articolo['categoria']) ?>
            </span>
            <?php if ($dataLeggibile): ?>
            <span class="text-white/70 text-sm"><?= $dataLeggibile ?></span>
            <?php endif; ?>
            <span class="text-white/70 text-sm">
                <i class="fas fa-clock mr-1"></i><?= $articolo['tempo_lettura'] ?> min di lettura
            </span>
        </div>
        <h1 class="text-2xl md:text-4xl font-black text-white leading-tight">
            <?= htmlspecialchars($articolo['titolo_finale']) ?>
        </h1>
    </div>
</div>

<!-- CONTENUTO ARTICOLO -->
<main class="max-w-3xl mx-auto px-4 py-10">

    <?php if ($articolo['excerpt']): ?>
    <p class="text-lg text-gray-500 italic mb-8 pb-6 border-b leading-relaxed">
        <?= htmlspecialchars($articolo['excerpt']) ?>
    </p>
    <?php endif; ?>

    <div class="prose-content">
        <?php
        if ($articolo['immagine_piccola_url']) {
            $contenuto = $articolo['contenuto'];
            $pos = strpos($contenuto, '</p>');
            if ($pos !== false) {
                $imgHtml = '<figure class="float-right ml-6 mb-4 w-48 sm:w-56 clear-right">
                    <img src="' . htmlspecialchars($articolo['immagine_piccola_url']) . '"
                         alt="' . htmlspecialchars($articolo['immagine_piccola_alt'] ?? '') . '"
                         class="rounded-xl shadow-md w-full object-cover" loading="lazy">
                </figure>';
                $contenuto = substr($contenuto, 0, $pos + 4) . $imgHtml . substr($contenuto, $pos + 4);
            }
            echo $contenuto;
        } else {
            echo $articolo['contenuto'];
        }
        ?>
    </div>

    <?php if ($articolo['fonte_url']): ?>
    <div class="mt-10 pt-6 border-t">
        <p class="sans text-sm text-gray-500">
            <i class="fas fa-link mr-2 text-blue-400"></i>
            <strong>Fonte originale:</strong>
            <a href="<?= htmlspecialchars($articolo['fonte_url']) ?>" target="_blank" rel="noopener noreferrer"
               class="text-blue-600 hover:underline ml-1">
                <?= htmlspecialchars(parse_url($articolo['fonte_url'], PHP_URL_HOST) ?? $articolo['fonte_url']) ?>
            </a>
        </p>
    </div>
    <?php endif; ?>

    <div class="mt-10 text-center">
        <a href="/" class="sans inline-flex items-center gap-2 bg-slate-900 text-white px-6 py-3 rounded-xl font-semibold hover:bg-slate-700 transition-colors">
            <i class="fas fa-arrow-left"></i> Torna alla home
        </a>
    </div>
</main>

<?php require_once __DIR__ . '/includes/site_footer.php'; ?>

</body>
</html>
