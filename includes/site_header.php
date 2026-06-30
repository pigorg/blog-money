<?php
// Richiede $db già inizializzato
$giorniIta = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
$mesiIta   = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
$dataIta   = $giorniIta[(int)date('w')] . ' ' . date('d') . ' ' . $mesiIta[(int)date('n')] . ' ' . date('Y');

$navCats = $db->query(
    "SELECT DISTINCT categoria FROM articoli WHERE stato = 'pubblicato' ORDER BY categoria ASC"
)->fetch_all(MYSQLI_ASSOC);

$categoriaAttiva = $_GET['categoria'] ?? '';
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
                <span class="text-5xl font-black tracking-tighter uppercase sans leading-none">Blog Money</span>
            </div>
            <p class="text-slate-500 text-xs tracking-[.25em] uppercase mt-2 sans">Finanza · Investimenti · Mercati</p>
        </a>
    </div>
    <nav class="max-w-7xl mx-auto px-4 sans">
        <div class="flex overflow-x-auto scrollbar-hide border-b border-slate-800">
            <a href="/" class="flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 <?= !$categoriaAttiva ? 'text-blue-400 border-blue-400' : 'text-slate-400 hover:text-white border-transparent hover:border-slate-500' ?> transition-colors">
                Home
            </a>
            <?php foreach ($navCats as $c): ?>
            <a href="/?categoria=<?= urlencode($c['categoria']) ?>"
               class="flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors
               <?= $categoriaAttiva === $c['categoria'] ? 'text-blue-400 border-blue-400' : 'text-slate-400 hover:text-white border-transparent hover:border-slate-500' ?>">
                <?= htmlspecialchars($c['categoria']) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </nav>
</header>
