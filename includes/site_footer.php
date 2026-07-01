<footer class="bg-slate-950 text-slate-400 mt-12">
    <div class="max-w-7xl mx-auto px-4 py-10 sans">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6 border-b border-slate-800 pb-8 mb-6">
            <div class="text-center md:text-left">
                <div class="flex items-center gap-2 justify-center md:justify-start">
                    <i class="fas fa-chart-line text-blue-400 text-xl"></i>
                    <span class="text-white text-xl font-black uppercase tracking-tighter">Finanza Facile</span>
                </div>
                <p class="text-slate-600 text-xs mt-1 tracking-widest uppercase">Finanza · Investimenti · Mercati</p>
            </div>
            <?php if (!empty($navCats)): ?>
            <nav class="flex flex-wrap gap-x-6 gap-y-2 justify-center text-sm">
                <a href="/" class="hover:text-white transition-colors">Home</a>
                <?php foreach ($navCats as $c): ?>
                <a href="/?categoria=<?= urlencode($c['categoria']) ?>" class="hover:text-white transition-colors">
                    <?= htmlspecialchars($c['categoria']) ?>
                </a>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>
        </div>

        <div class="text-xs text-slate-500 leading-relaxed max-w-3xl mx-auto text-center space-y-2">
            <p>Questo sito non rappresenta una testata giornalistica in quanto viene aggiornato senza alcuna periodicità. Non può pertanto considerarsi un prodotto editoriale ai sensi della legge n.62 del 2001.</p>
            <p>Gli autori, inoltre, non hanno alcuna responsabilità per quanto riguarda i siti ai quali è possibile accedere tramite eventuali collegamenti, posti all'interno del sito stesso, forniti come semplice servizio a coloro che visitano il sito.</p>
            <p>Gli autori non si assumono alcuna responsabilità in merito alla verifica e alla correttezza dei contenuti pubblicati. Le informazioni presenti nel sito hanno scopo puramente informativo e non costituiscono in alcun modo consulenza finanziaria, legale o professionale.</p>
            <p class="text-slate-600 pt-2">&copy; <?= date('Y') ?> Finanza Facile</p>
        </div>
    </div>
</footer>
