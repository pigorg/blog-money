<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/Database.php';

$database = new Database();
$database->connect();
$db = $database->getConn();

$evergreenCats  = ['Investimenti', 'Risparmio', 'Pensione & Previdenza', 'Fiscalità', 'ETF & Fondi', 'Previdenza', 'Guide'];
$sezioneAttiva  = $_GET['sezione'] ?? '';
$isEducational  = $sezioneAttiva === 'educational';

$placeholders = implode(',', array_fill(0, count($evergreenCats), '?'));
$types        = str_repeat('s', count($evergreenCats));

// Ultime notizie (colonna sinistra): 9 articoli più recenti NON evergreen
$stmtNews = $db->prepare(
    "SELECT id, titolo_finale, slug, excerpt, categoria, data_pubblicazione, tempo_lettura, immagine_url, immagine_alt
     FROM articoli WHERE stato = 'pubblicato' AND categoria NOT IN ($placeholders)
     ORDER BY data_pubblicazione DESC LIMIT 9"
);
$stmtNews->bind_param($types, ...$evergreenCats);
$stmtNews->execute();
$notizie = $stmtNews->get_result()->fetch_all(MYSQLI_ASSOC);

// Evergreen: categorie specifiche
$stmtEvg = $db->prepare(
    "SELECT id, titolo_finale, slug, excerpt, categoria, data_pubblicazione, tempo_lettura, immagine_url
     FROM articoli WHERE stato = 'pubblicato' AND categoria IN ($placeholders)
     ORDER BY data_pubblicazione DESC LIMIT " . ($isEducational ? '24' : '8')
);
$stmtEvg->bind_param($types, ...$evergreenCats);
$stmtEvg->execute();
$evergreen = $stmtEvg->get_result()->fetch_all(MYSQLI_ASSOC);

$siteUrl = SITE_URL;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Money | Notizie di Finanza, Investimenti e Mercati Finanziari</title>
    <meta name="description" content="Scopri articoli approfonditi su investimenti, risparmio, ETF, criptovalute e mercati finanziari. Guide pratiche e notizie aggiornate ogni giorno per risparmiatori italiani.">
    <link rel="canonical" href="<?= $siteUrl ?>/">
    <meta property="og:title" content="Blog Money | Finanza, Investimenti e Mercati per Risparmiatori Italiani">
    <meta property="og:description" content="Articoli su investimenti, risparmio, ETF, criptovalute e mercati finanziari aggiornati ogni giorno.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $siteUrl ?>/">
    <meta name="twitter:card" content="summary_large_image">
    <?php if (!empty($notizie) && $notizie[0]['immagine_url']): ?>
    <meta property="og:image" content="<?= htmlspecialchars($notizie[0]['immagine_url']) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($notizie[0]['immagine_url']) ?>">
    <?php endif; ?>
    <script type="application/ld+json">
    <?= json_encode(['@context'=>'https://schema.org','@type'=>'WebSite','name'=>'Blog Money','url'=>$siteUrl,'inLanguage'=>'it-IT'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/public.css">
    <style>
        body { font-family: 'Georgia', serif; }
        .sans { font-family: system-ui, -apple-system, sans-serif; }
        .hero-gradient { background: linear-gradient(to top, rgba(0,0,0,.88) 0%, rgba(0,0,0,.35) 55%, transparent 100%); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .col-divider { border-left: 1px solid #e2e8f0; }
    </style>
</head>
<body class="bg-white text-gray-900">

<?php require_once __DIR__ . '/includes/site_header.php'; ?>

<main class="max-w-7xl mx-auto px-4 py-8">

<?php if ($isEducational): ?>

    <div class="mb-8">
        <div class="flex items-center gap-3 mb-8 sans">
            <i class="fas fa-bookmark text-amber-500 text-lg"></i>
            <h1 class="text-xl font-black uppercase tracking-widest text-gray-900">Guide & Approfondimenti</h1>
            <div class="flex-1 h-px bg-gray-200"></div>
        </div>

        <?php if (empty($evergreen)): ?>
        <div class="py-24 text-center">
            <i class="fas fa-book-open text-6xl text-gray-100 mb-4"></i>
            <p class="text-gray-400 sans">Le guide arriveranno presto.</p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($evergreen as $a): ?>
            <article class="group bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <a href="<?= urlArticolo($a['slug']) ?>" class="block">
                    <div class="relative h-48 bg-amber-50">
                        <?php if ($a['immagine_url']): ?>
                        <img src="<?= htmlspecialchars($a['immagine_url']) ?>"
                             alt="<?= htmlspecialchars($a['titolo_finale']) ?>"
                             class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500"
                             loading="lazy">
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center">
                            <i class="fas fa-book-open text-4xl text-amber-200"></i>
                        </div>
                        <?php endif; ?>
                        <div class="absolute top-3 left-3">
                            <span class="bg-amber-500 text-white text-xs font-bold px-2.5 py-1 rounded uppercase tracking-wide sans">
                                <?= htmlspecialchars($a['categoria']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="p-4">
                        <h2 class="font-bold text-gray-900 leading-snug group-hover:text-amber-700 transition-colors line-clamp-2">
                            <?= htmlspecialchars($a['titolo_finale']) ?>
                        </h2>
                        <p class="sans text-xs text-gray-400 mt-2">
                            <i class="fas fa-clock mr-1"></i><?= $a['tempo_lettura'] ?> min
                        </p>
                    </div>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

<?php elseif (empty($notizie) && empty($evergreen)): ?>
<div class="py-36 text-center">
    <i class="fas fa-newspaper text-7xl text-gray-100 mb-6"></i>
    <h2 class="text-2xl font-bold text-gray-300 sans mb-2">Nessun articolo ancora</h2>
    <p class="text-gray-400 sans">Il bot sta lavorando. Torna domani.</p>
</div>

<?php else: ?>

<div class="grid grid-cols-1 lg:grid-cols-5 gap-0 lg:gap-8">

    <!-- ════════════════════════════════════════
         COLONNA SINISTRA — ULTIME NOTIZIE
    ════════════════════════════════════════ -->
    <div class="lg:col-span-3">

        <!-- Intestazione -->
        <div class="flex items-center gap-3 mb-6 sans">
            <span class="inline-flex items-center gap-2 text-sm font-black uppercase tracking-widest text-gray-900">
                <span class="w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse"></span>
                Ultime Notizie
            </span>
            <div class="flex-1 h-px bg-gray-200"></div>
        </div>

        <?php if (empty($notizie)): ?>
        <p class="text-gray-400 sans text-sm">Nessuna notizia disponibile.</p>

        <?php else:
            $featured = $notizie[0];
            $rest     = array_slice($notizie, 1);
        ?>

        <!-- Articolo in evidenza -->
        <article class="group mb-6">
            <a href="<?= urlArticolo($featured['slug']) ?>" class="block">
                <div class="relative rounded-2xl overflow-hidden" style="height:320px;">
                    <?php if ($featured['immagine_url']): ?>
                    <img src="<?= htmlspecialchars($featured['immagine_url']) ?>"
                         alt="<?= htmlspecialchars($featured['immagine_alt'] ?? $featured['titolo_finale']) ?>"
                         class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-700">
                    <?php else: ?>
                    <div class="w-full h-full bg-gradient-to-br from-slate-700 via-blue-900 to-slate-900 flex flex-col items-center justify-center gap-3">
                        <i class="fas fa-chart-line text-6xl text-blue-400/30"></i>
                        <span class="sans text-blue-300/50 text-sm uppercase tracking-widest"><?= htmlspecialchars($featured['categoria']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="hero-gradient absolute inset-0"></div>
                    <div class="absolute bottom-0 left-0 right-0 p-6">
                        <div class="flex items-center gap-2 mb-2 sans">
                            <span class="bg-blue-600 text-white text-xs font-bold px-2.5 py-1 rounded uppercase tracking-wide">
                                <?= htmlspecialchars($featured['categoria']) ?>
                            </span>
                            <span class="text-white/60 text-xs">
                                <?= $featured['data_pubblicazione'] ? date('d M Y', strtotime($featured['data_pubblicazione'])) : '' ?>
                            </span>
                        </div>
                        <h2 class="text-xl md:text-2xl font-black text-white leading-tight">
                            <?= htmlspecialchars($featured['titolo_finale']) ?>
                        </h2>
                    </div>
                </div>
            </a>
        </article>

        <!-- Griglia articoli minori -->
        <?php if (!empty($rest)): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <?php foreach ($rest as $a): ?>
            <article class="group flex gap-3 border-b border-gray-100 pb-5">
                <a href="<?= urlArticolo($a['slug']) ?>" class="flex gap-3 w-full">
                    <div class="flex-shrink-0 w-20 h-16 rounded-lg overflow-hidden bg-gray-100">
                        <?php if ($a['immagine_url']): ?>
                        <img src="<?= htmlspecialchars($a['immagine_url']) ?>"
                             alt="<?= htmlspecialchars($a['titolo_finale']) ?>"
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                             loading="lazy">
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-slate-100 to-blue-50">
                            <i class="fas fa-chart-bar text-2xl text-blue-200"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0">
                        <span class="sans text-xs font-bold text-blue-600 uppercase tracking-wide">
                            <?= htmlspecialchars($a['categoria']) ?>
                        </span>
                        <h3 class="text-sm font-bold text-gray-900 line-clamp-2 group-hover:text-blue-700 transition-colors leading-snug mt-0.5">
                            <?= htmlspecialchars($a['titolo_finale']) ?>
                        </h3>
                        <span class="sans text-xs text-gray-400">
                            <?= $a['data_pubblicazione'] ? date('d M', strtotime($a['data_pubblicazione'])) : '' ?>
                            · <?= $a['tempo_lettura'] ?> min
                        </span>
                    </div>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; // fine notizie ?>
    </div>

    <!-- ════════════════════════════════════════
         COLONNA DESTRA — GUIDE & EVERGREEN
    ════════════════════════════════════════ -->
    <div class="lg:col-span-2 col-divider lg:pl-8 mt-10 lg:mt-0">

        <!-- Intestazione -->
        <div class="flex items-center gap-3 mb-6 sans">
            <span class="inline-flex items-center gap-2 text-sm font-black uppercase tracking-widest text-gray-900">
                <i class="fas fa-bookmark text-amber-500"></i>
                Guide & Approfondimenti
            </span>
            <div class="flex-1 h-px bg-gray-200"></div>
        </div>

        <?php if (empty($evergreen)): ?>
        <div class="bg-amber-50 rounded-xl p-6 text-center">
            <i class="fas fa-book-open text-3xl text-amber-200 mb-3"></i>
            <p class="sans text-sm text-gray-500">Le guide arriveranno presto.</p>
            <p class="sans text-xs text-gray-400 mt-1">Categorie: Investimenti, Risparmio, ETF, Fiscalità...</p>
        </div>

        <?php else: ?>
        <div class="space-y-0">
            <?php foreach ($evergreen as $i => $a): ?>
            <article class="group <?= $i > 0 ? 'border-t border-gray-100' : '' ?> py-4">
                <a href="<?= urlArticolo($a['slug']) ?>" class="flex gap-3">
                    <div class="flex-shrink-0 w-16 h-16 rounded-xl overflow-hidden bg-amber-50 flex items-center justify-center">
                        <?php if ($a['immagine_url']): ?>
                        <img src="<?= htmlspecialchars($a['immagine_url']) ?>"
                             alt="<?= htmlspecialchars($a['titolo_finale']) ?>"
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                             loading="lazy">
                        <?php else: ?>
                        <i class="fas fa-book-open text-amber-300 text-lg"></i>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0 flex flex-col justify-center">
                        <span class="sans text-xs font-bold text-amber-700 uppercase tracking-wide leading-none mb-1">
                            <?= htmlspecialchars($a['categoria']) ?>
                        </span>
                        <h3 class="text-sm font-bold text-gray-900 line-clamp-2 group-hover:text-amber-700 transition-colors leading-snug">
                            <?= htmlspecialchars($a['titolo_finale']) ?>
                        </h3>
                        <span class="sans text-xs text-gray-400 mt-1">
                            <i class="fas fa-clock mr-1"></i><?= $a['tempo_lettura'] ?> min
                        </span>
                    </div>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div><!-- fine colonna destra -->

</div><!-- fine grid -->

<?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/site_footer.php'; ?>

</body>
</html>
