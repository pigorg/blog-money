<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/Database.php';

$database = new Database();
$database->connect();
$db = $database->getConn();

// Categorie a bassa rotazione (evergreen)
$evergreenCats = ['Investimenti', 'Risparmio', 'Pensione & Previdenza', 'Fiscalità', 'ETF & Fondi', 'Previdenza', 'Guide'];

// 1. Hero: articolo più recente
$hero = $db->query(
    "SELECT id, titolo_finale, slug, excerpt, categoria, data_pubblicazione, tempo_lettura, immagine_url, immagine_alt
     FROM articoli WHERE stato = 'pubblicato' ORDER BY data_pubblicazione DESC LIMIT 1"
)->fetch_assoc();

$heroId = $hero['id'] ?? 0;

// 2. Ultime notizie (alta rotazione): i 7 successivi all'hero
$stmtNews = $db->prepare(
    "SELECT id, titolo_finale, slug, excerpt, categoria, data_pubblicazione, tempo_lettura, immagine_url
     FROM articoli WHERE stato = 'pubblicato' AND id != ?
     ORDER BY data_pubblicazione DESC LIMIT 7"
);
$stmtNews->bind_param('i', $heroId);
$stmtNews->execute();
$notizie = $stmtNews->get_result()->fetch_all(MYSQLI_ASSOC);

// 3. Approfondimenti evergreen (bassa rotazione): categorie specifiche
$placeholders = implode(',', array_fill(0, count($evergreenCats), '?'));
$types        = str_repeat('s', count($evergreenCats));
$stmtEvg      = $db->prepare(
    "SELECT id, titolo_finale, slug, excerpt, categoria, data_pubblicazione, tempo_lettura, immagine_url
     FROM articoli WHERE stato = 'pubblicato' AND categoria IN ($placeholders)
     ORDER BY data_pubblicazione DESC LIMIT 6"
);
$stmtEvg->bind_param($types, ...$evergreenCats);
$stmtEvg->execute();
$evergreen = $stmtEvg->get_result()->fetch_all(MYSQLI_ASSOC);

// Data in italiano
$giorniIta = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
$mesiIta   = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
$dataIta   = $giorniIta[(int)date('w')] . ' ' . date('d') . ' ' . $mesiIta[(int)date('n')] . ' ' . date('Y');

// Navigazione categorie
$cats = $db->query(
    "SELECT DISTINCT categoria FROM articoli WHERE stato = 'pubblicato' ORDER BY categoria ASC"
)->fetch_all(MYSQLI_ASSOC);

$siteUrl = SITE_URL;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Money — Finanza, Investimenti e Mercati</title>
    <meta name="description" content="Notizie finanziarie, guide agli investimenti e analisi di mercato aggiornate ogni giorno con intelligenza artificiale.">
    <link rel="canonical" href="<?= $siteUrl ?>/">
    <meta property="og:title" content="Blog Money — Finanza, Investimenti e Mercati">
    <meta property="og:description" content="Notizie finanziarie e guide agli investimenti aggiornate ogni giorno.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $siteUrl ?>/">
    <meta property="og:site_name" content="Blog Money">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Blog Money — Finanza, Investimenti e Mercati">
    <meta name="twitter:description" content="Notizie finanziarie e guide agli investimenti aggiornate ogni giorno.">
    <?php if ($hero && $hero['immagine_url']): ?>
    <meta property="og:image" content="<?= htmlspecialchars($hero['immagine_url']) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($hero['immagine_url']) ?>">
    <?php endif; ?>
    <script type="application/ld+json">
    <?= json_encode([
        '@context'    => 'https://schema.org',
        '@type'       => 'WebSite',
        'name'        => 'Blog Money',
        'url'         => $siteUrl,
        'inLanguage'  => 'it-IT',
        'description' => 'Notizie finanziarie, guide agli investimenti e analisi di mercato aggiornate ogni giorno.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/public.css">
    <style>
        body { font-family: 'Georgia', serif; }
        .sans { font-family: system-ui, -apple-system, sans-serif; }
        .hero-gradient { background: linear-gradient(to top, rgba(0,0,0,.88) 0%, rgba(0,0,0,.4) 55%, transparent 100%); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .card-lift { transition: transform .2s, box-shadow .2s; }
        .card-lift:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,.1); }
    </style>
</head>
<body class="bg-white text-gray-900">

<!-- TOPBAR -->
<div class="bg-slate-950 text-slate-400 text-xs sans py-1.5 hidden sm:block">
    <div class="max-w-7xl mx-auto px-4">
        <span class="uppercase tracking-widest"><?= $dataIta ?></span>
    </div>
</div>

<!-- HEADER -->
<header class="bg-slate-950 text-white">
    <div class="max-w-7xl mx-auto px-4 py-5 border-b border-slate-800 text-center">
        <a href="/" class="inline-block group">
            <div class="flex items-center justify-center gap-3">
                <i class="fas fa-chart-line text-blue-400 text-3xl"></i>
                <span class="text-5xl font-black tracking-tighter uppercase sans leading-none">Blog Money</span>
            </div>
            <p class="text-slate-500 text-xs tracking-[.25em] uppercase mt-2 sans">Finanza · Investimenti · Mercati</p>
        </a>
    </div>

    <!-- NAV categorie -->
    <nav class="max-w-7xl mx-auto px-4 sans">
        <div class="flex overflow-x-auto scrollbar-hide border-b border-slate-800">
            <a href="/" class="flex-shrink-0 px-4 py-3 text-sm font-semibold text-blue-400 border-b-2 border-blue-400">
                Home
            </a>
            <?php foreach ($cats as $c): ?>
            <a href="?categoria=<?= urlencode($c['categoria']) ?>"
               class="flex-shrink-0 px-4 py-3 text-sm font-medium text-slate-400 hover:text-white border-b-2 border-transparent hover:border-slate-500 transition-colors whitespace-nowrap">
                <?= htmlspecialchars($c['categoria']) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </nav>
</header>

<main class="max-w-7xl mx-auto px-4">

<?php if (!$hero): ?>
<!-- EMPTY STATE -->
<div class="py-36 text-center">
    <i class="fas fa-newspaper text-7xl text-gray-100 mb-6"></i>
    <h2 class="text-2xl font-bold text-gray-300 sans mb-2">Nessun articolo ancora</h2>
    <p class="text-gray-400 sans">Il bot sta lavorando. Torna domani.</p>
</div>

<?php else: ?>

<!-- ═══════════════════════════════════════════════
     HERO — ARTICOLO IN EVIDENZA
════════════════════════════════════════════════ -->
<section class="mt-8 mb-10">
    <a href="articolo.php?slug=<?= urlencode($hero['slug']) ?>" class="block group">
        <div class="relative rounded-2xl overflow-hidden" style="height: 520px;">
            <?php if ($hero['immagine_url']): ?>
            <img src="<?= htmlspecialchars($hero['immagine_url']) ?>"
                 alt="<?= htmlspecialchars($hero['immagine_alt'] ?? $hero['titolo_finale']) ?>"
                 class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-700">
            <?php else: ?>
            <div class="w-full h-full bg-gradient-to-br from-slate-800 via-blue-900 to-slate-900 flex items-center justify-center">
                <i class="fas fa-chart-line text-9xl text-blue-400/20"></i>
            </div>
            <?php endif; ?>
            <div class="hero-gradient absolute inset-0"></div>

            <div class="absolute bottom-0 left-0 right-0 p-8 md:p-12">
                <div class="flex flex-wrap items-center gap-3 mb-4 sans">
                    <span class="bg-blue-600 text-white text-xs font-bold px-3 py-1.5 rounded uppercase tracking-wide">
                        <?= htmlspecialchars($hero['categoria']) ?>
                    </span>
                    <span class="bg-white/20 text-white/90 text-xs px-3 py-1.5 rounded backdrop-blur-sm">
                        <i class="fas fa-clock mr-1"></i><?= $hero['tempo_lettura'] ?> min
                    </span>
                    <?php if ($hero['data_pubblicazione']): ?>
                    <span class="text-white/60 text-sm">
                        <?= date('d M Y', strtotime($hero['data_pubblicazione'])) ?>
                    </span>
                    <?php endif; ?>
                </div>

                <h1 class="text-3xl md:text-5xl font-black text-white leading-tight mb-4 max-w-4xl">
                    <?= htmlspecialchars($hero['titolo_finale']) ?>
                </h1>

                <?php if ($hero['excerpt']): ?>
                <p class="text-white/75 text-lg max-w-2xl line-clamp-2 mb-6 sans font-normal hidden sm:block">
                    <?= htmlspecialchars($hero['excerpt']) ?>
                </p>
                <?php endif; ?>

                <span class="inline-flex items-center gap-2 sans bg-white text-slate-900 text-sm font-bold px-5 py-2.5 rounded-lg group-hover:bg-blue-50 transition-colors">
                    Leggi l'articolo completo <i class="fas fa-arrow-right text-blue-600"></i>
                </span>
            </div>
        </div>
    </a>
</section>


<?php if (!empty($notizie)): ?>
<!-- ═══════════════════════════════════════════════
     SEZIONE 1 — ULTIME NOTIZIE (alta rotazione)
════════════════════════════════════════════════ -->
<section class="mb-14">
    <!-- Intestazione sezione -->
    <div class="flex items-center gap-4 mb-7 sans">
        <span class="inline-flex items-center gap-2 text-sm font-black uppercase tracking-widest text-gray-900">
            <span class="w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse"></span>
            Ultime Notizie
        </span>
        <div class="flex-1 h-px bg-gray-200"></div>
        <a href="/" class="text-xs text-blue-600 font-semibold hover:underline uppercase tracking-wide">
            Archivio →
        </a>
    </div>

    <?php
    $mainNews = array_slice($notizie, 0, 2);
    $sideNews = array_slice($notizie, 2);
    ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- 2 notizie principali -->
        <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
            <?php foreach ($mainNews as $a): ?>
            <article class="group card-lift">
                <a href="articolo.php?slug=<?= urlencode($a['slug']) ?>" class="block">
                    <div class="aspect-[16/9] rounded-xl overflow-hidden mb-4 bg-gray-100">
                        <?php if ($a['immagine_url']): ?>
                        <img src="<?= htmlspecialchars($a['immagine_url']) ?>"
                             alt="<?= htmlspecialchars($a['titolo_finale']) ?>"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                             loading="lazy">
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-50">
                            <i class="fas fa-chart-bar text-4xl text-blue-100"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <span class="sans text-xs font-bold text-blue-600 uppercase tracking-wide">
                        <?= htmlspecialchars($a['categoria']) ?>
                    </span>
                    <h2 class="text-lg font-bold leading-snug mt-1 mb-2 line-clamp-3 group-hover:text-blue-700 transition-colors">
                        <?= htmlspecialchars($a['titolo_finale']) ?>
                    </h2>
                    <p class="sans text-sm text-gray-500 line-clamp-2 mb-3">
                        <?= htmlspecialchars($a['excerpt'] ?? '') ?>
                    </p>
                    <div class="sans flex items-center gap-3 text-xs text-gray-400">
                        <?php if ($a['data_pubblicazione']): ?>
                        <span><?= date('d M Y', strtotime($a['data_pubblicazione'])) ?></span>
                        <span>·</span>
                        <?php endif; ?>
                        <span><i class="fas fa-clock mr-1"></i><?= $a['tempo_lettura'] ?> min</span>
                    </div>
                </a>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- Lista compatta a destra -->
        <div class="lg:border-l lg:border-gray-100 lg:pl-8">
            <div class="space-y-0">
                <?php foreach ($sideNews as $i => $a): ?>
                <article class="group <?= $i > 0 ? 'border-t border-gray-100' : '' ?> py-4">
                    <a href="articolo.php?slug=<?= urlencode($a['slug']) ?>" class="flex gap-3">
                        <div class="flex-shrink-0 w-20 h-16 rounded-lg overflow-hidden bg-gray-100">
                            <?php if ($a['immagine_url']): ?>
                            <img src="<?= htmlspecialchars($a['immagine_url']) ?>"
                                 alt="<?= htmlspecialchars($a['titolo_finale']) ?>"
                                 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                                 loading="lazy">
                            <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <i class="fas fa-chart-bar text-gray-200"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0 flex flex-col justify-center">
                            <span class="sans text-xs font-bold text-blue-600 uppercase tracking-wide leading-none mb-1">
                                <?= htmlspecialchars($a['categoria']) ?>
                            </span>
                            <h3 class="text-sm font-bold text-gray-900 line-clamp-2 group-hover:text-blue-700 transition-colors leading-snug">
                                <?= htmlspecialchars($a['titolo_finale']) ?>
                            </h3>
                            <?php if ($a['data_pubblicazione']): ?>
                            <span class="sans text-xs text-gray-400 mt-1">
                                <?= date('d M', strtotime($a['data_pubblicazione'])) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>


<?php if (!empty($evergreen)): ?>
<!-- ═══════════════════════════════════════════════
     SEZIONE 2 — GUIDE & APPROFONDIMENTI (bassa rotazione)
════════════════════════════════════════════════ -->
<section class="bg-amber-50 -mx-4 px-4 py-12 mb-8">
    <div class="max-w-7xl mx-auto">
        <!-- Intestazione sezione -->
        <div class="flex items-center gap-4 mb-3 sans">
            <span class="inline-flex items-center gap-2 text-sm font-black uppercase tracking-widest text-gray-900">
                <i class="fas fa-bookmark text-amber-500"></i>
                Guide & Approfondimenti
            </span>
            <div class="flex-1 h-px bg-amber-200"></div>
        </div>
        <p class="sans text-sm text-gray-500 mb-8">
            Letture senza scadenza su investimenti, risparmio e pianificazione finanziaria
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($evergreen as $a): ?>
            <article class="bg-white rounded-xl border border-amber-100 overflow-hidden group card-lift">
                <a href="articolo.php?slug=<?= urlencode($a['slug']) ?>" class="block">
                    <?php if ($a['immagine_url']): ?>
                    <div class="aspect-[16/9] overflow-hidden">
                        <img src="<?= htmlspecialchars($a['immagine_url']) ?>"
                             alt="<?= htmlspecialchars($a['titolo_finale']) ?>"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                             loading="lazy">
                    </div>
                    <?php endif; ?>
                    <div class="p-5">
                        <div class="flex items-center gap-2 mb-3 sans">
                            <span class="w-7 h-7 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-book-open text-amber-600 text-xs"></i>
                            </span>
                            <span class="text-xs font-bold text-amber-700 uppercase tracking-wide">
                                <?= htmlspecialchars($a['categoria']) ?>
                            </span>
                        </div>
                        <h3 class="text-base font-bold text-gray-900 leading-snug line-clamp-3 mb-2 group-hover:text-amber-700 transition-colors">
                            <?= htmlspecialchars($a['titolo_finale']) ?>
                        </h3>
                        <p class="sans text-sm text-gray-500 line-clamp-2 mb-4">
                            <?= htmlspecialchars($a['excerpt'] ?? '') ?>
                        </p>
                        <div class="sans flex items-center justify-between text-xs">
                            <span class="text-gray-400">
                                <i class="fas fa-clock mr-1"></i><?= $a['tempo_lettura'] ?> min di lettura
                            </span>
                            <span class="font-semibold text-amber-600 group-hover:underline">
                                Leggi →
                            </span>
                        </div>
                    </div>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php endif; // fine if $hero ?>
</main>

<!-- FOOTER -->
<footer class="bg-slate-950 text-slate-400 mt-8">
    <div class="max-w-7xl mx-auto px-4 py-10 sans">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6 border-b border-slate-800 pb-8 mb-6">
            <div class="text-center md:text-left">
                <div class="flex items-center gap-2 justify-center md:justify-start">
                    <i class="fas fa-chart-line text-blue-400 text-xl"></i>
                    <span class="text-white text-xl font-black uppercase tracking-tighter">Blog Money</span>
                </div>
                <p class="text-slate-600 text-xs mt-1 tracking-widest uppercase">Finanza · Investimenti · Mercati</p>
            </div>
            <?php if (!empty($cats)): ?>
            <nav class="flex flex-wrap gap-x-6 gap-y-2 justify-center text-sm">
                <a href="/" class="hover:text-white transition-colors">Home</a>
                <?php foreach ($cats as $c): ?>
                <a href="?categoria=<?= urlencode($c['categoria']) ?>" class="hover:text-white transition-colors">
                    <?= htmlspecialchars($c['categoria']) ?>
                </a>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>
        </div>
        <p class="text-center text-xs text-slate-600 leading-relaxed">
            &copy; <?= date('Y') ?> Blog Money &nbsp;·&nbsp;
            I contenuti sono generati con intelligenza artificiale a scopo esclusivamente informativo e non costituiscono consulenza finanziaria.
        </p>
    </div>
</footer>

</body>
</html>
