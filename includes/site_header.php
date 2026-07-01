<?php
// Richiede $db già inizializzato
$giorniIta = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
$mesiIta   = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
$dataIta   = $giorniIta[(int)date('w')] . ' ' . date('d') . ' ' . $mesiIta[(int)date('n')] . ' ' . date('Y');

$sezioneAttiva  = $_GET['sezione'] ?? '';
$categoriaAttiva = $_GET['categoria'] ?? '';

// Categorie non-evergreen presenti nel DB (max 6)
$_evCats = ['Investimenti', 'Risparmio', 'Pensione & Previdenza', 'Fiscalità', 'ETF & Fondi', 'Previdenza', 'Guide'];
$_evIn   = "'" . implode("','", array_map(fn($c) => $db->real_escape_string($c), $_evCats)) . "'";
$_rCats  = $db->query(
    "SELECT DISTINCT categoria FROM articoli
     WHERE stato = 'pubblicato' AND categoria NOT IN ($_evIn)
     ORDER BY categoria LIMIT 6"
);
$navCategorie = $_rCats ? $_rCats->fetch_all(MYSQLI_ASSOC) : [];
?>
<!-- TOPBAR -->
<div class="bg-slate-950 text-slate-500 text-xs sans py-1.5 hidden sm:block">
    <div class="max-w-7xl mx-auto px-4">
        <span class="uppercase tracking-widest"><?= $dataIta ?></span>
    </div>
</div>

<!-- HEADER -->
<header class="bg-slate-950 text-white">
    <div class="max-w-7xl mx-auto px-4 py-5 border-b border-slate-800 text-center">
        <a href="/" class="inline-block">
            <div class="flex items-center justify-center gap-3">
                <i class="fas fa-chart-line text-blue-400 text-3xl"></i>
                <span class="text-5xl font-black tracking-tighter uppercase sans leading-none">Finanza Facile</span>
            </div>
            <p class="text-slate-500 text-xs tracking-[.25em] uppercase mt-2 sans">Finanza · Investimenti · Mercati</p>
        </a>
    </div>
    <nav class="max-w-7xl mx-auto px-4 sans">
        <div class="flex overflow-x-auto scrollbar-hide border-b border-slate-800">
            <a href="/" class="flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors
               <?= ($sezioneAttiva === '' && $categoriaAttiva === '') ? 'text-blue-400 border-blue-400' : 'text-slate-400 hover:text-white border-transparent hover:border-slate-500' ?>">
                Home
            </a>
            <?php foreach ($navCategorie as $nc): ?>
            <a href="/?categoria=<?= urlencode($nc['categoria']) ?>" class="flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors
               <?= $categoriaAttiva === $nc['categoria'] ? 'text-blue-400 border-blue-400' : 'text-slate-400 hover:text-white border-transparent hover:border-slate-500' ?>">
                <?= htmlspecialchars($nc['categoria']) ?>
            </a>
            <?php endforeach; ?>
            <a href="/?sezione=educational" class="flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors
               <?= $sezioneAttiva === 'educational' ? 'text-amber-400 border-amber-400' : 'text-slate-400 hover:text-white border-transparent hover:border-slate-500' ?>">
                Guide
            </a>
        </div>
    </nav>
</header>
