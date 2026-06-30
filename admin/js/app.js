const { createApp } = Vue;

const API = '../api';

createApp({
    data() {
        return {
            tab: 'articoli',
            menuAperto: false,
            tabs: [
                { id: 'articoli', label: 'Articoli',  icon: 'fas fa-file-alt' },
                { id: 'sorgenti', label: 'Sorgenti',  icon: 'fas fa-link' },
                { id: 'bot',      label: 'Bot',        icon: 'fas fa-robot' },
            ],
            articoli: [],
            sorgenti: [],
            log: [],
            titoliCoda: [],
            filtroStato: '',
            caricamento: { articoli: false },
            botOccupato: false,
            notifica: null,
            nuovaSorgente: { nome: '', url: '', tipo: 'rss' },
            suggerimento: { titolo: '', categoria: 'Investimenti' },
        };
    },

    computed: {
        articoliFiltrati() {
            return this.articoli.filter(a =>
                !this.filtroStato || a.stato === this.filtroStato
            );
        },
        stats() {
            return {
                totale:     this.articoli.length,
                pubblicati: this.articoli.filter(a => a.stato === 'pubblicato').length,
                draft:      this.articoli.filter(a => a.stato === 'draft').length,
                titoli:     this.titoliCoda.length,
            };
        },
    },

    mounted() {
        this.caricaArticoli();
        this.caricaSorgenti();
        this.caricaLog();
        this.caricaTitoliCoda();
        setInterval(() => {
            this.caricaLog();
            this.caricaTitoliCoda();
        }, 10000);
    },

    methods: {
        async caricaArticoli() {
            this.caricamento.articoli = true;
            try {
                const r = await axios.get(`${API}/articoli.php?azione=lista`);
                this.articoli = r.data;
            } catch(e) {
                this.mostraNotifica('error', 'Errore caricamento articoli: ' + e.message);
            } finally {
                this.caricamento.articoli = false;
            }
        },

        async caricaSorgenti() {
            try {
                const r = await axios.get(`${API}/sorgenti.php?azione=lista`);
                this.sorgenti = r.data;
            } catch(e) { console.error(e); }
        },

        async caricaLog() {
            try {
                const r = await axios.get(`${API}/bot.php?azione=log`);
                this.log = r.data;
            } catch(e) { console.error(e); }
        },

        async caricaTitoliCoda() {
            try {
                const r = await axios.get(`${API}/bot.php?azione=titoli_da_elaborare`);
                this.titoliCoda = r.data;
            } catch(e) { console.error(e); }
        },

        async pubblicaArticolo(id) {
            try {
                await axios.post(`${API}/articoli.php`, { azione: 'pubblica', id });
                this.caricaArticoli();
            } catch(e) {
                this.mostraNotifica('error', 'Errore pubblicazione: ' + e.message);
            }
        },

        async eliminaArticolo(id) {
            if (!confirm('Eliminare definitivamente questo articolo?')) return;
            try {
                await axios.delete(`${API}/articoli.php?id=${id}`);
                this.caricaArticoli();
            } catch(e) {
                this.mostraNotifica('error', 'Errore eliminazione: ' + e.message);
            }
        },

        async aggiungiSorgente() {
            const { nome, url } = this.nuovaSorgente;
            if (!nome || !url) {
                this.mostraNotifica('error', 'Nome e URL sono obbligatori.');
                return;
            }
            try {
                await axios.post(`${API}/sorgenti.php`, { azione: 'aggiungi', ...this.nuovaSorgente });
                this.nuovaSorgente = { nome: '', url: '', tipo: 'rss' };
                this.caricaSorgenti();
                this.mostraNotifica('success', 'Sorgente aggiunta!');
            } catch(e) {
                this.mostraNotifica('error', 'Errore: ' + e.message);
            }
        },

        async sincronizzaSorgente(id) {
            try {
                const r = await axios.post(`${API}/sorgenti.php`, { azione: 'sincronizza', id });
                this.mostraNotifica('success', `Sincronizzato! ${r.data.nuovi} nuovi titoli estratti.`);
                this.caricaTitoliCoda();
                this.caricaSorgenti();
            } catch(e) {
                this.mostraNotifica('error', 'Errore sincronizzazione: ' + e.message);
            }
        },

        async eliminaSorgente(id) {
            if (!confirm('Eliminare questa sorgente?')) return;
            try {
                await axios.delete(`${API}/sorgenti.php?id=${id}`);
                this.caricaSorgenti();
            } catch(e) {
                this.mostraNotifica('error', 'Errore: ' + e.message);
            }
        },

        async botAzione(azione) {
            this.botOccupato = true;
            this.notifica = null;
            try {
                const r = await axios.post(`${API}/bot.php`, { azione });
                this.mostraNotifica(r.data.tipo, r.data.messaggio);
                await this.caricaArticoli();
                await this.caricaTitoliCoda();
                await this.caricaLog();
            } catch(e) {
                const msg = e.response?.data?.messaggio || e.message;
                this.mostraNotifica('error', 'Errore: ' + msg);
            } finally {
                this.botOccupato = false;
            }
        },

        async generaDaIdSpecifico(titolo_id) {
            this.botOccupato = true;
            this.notifica = null;
            try {
                const r = await axios.post(`${API}/bot.php`, { azione: 'genera', titolo_id });
                this.mostraNotifica(r.data.tipo, r.data.messaggio);
                await this.caricaArticoli();
                await this.caricaTitoliCoda();
                await this.caricaLog();
            } catch(e) {
                const msg = e.response?.data?.messaggio || e.message;
                this.mostraNotifica('error', 'Errore: ' + msg);
            } finally {
                this.botOccupato = false;
            }
        },

        async suggerisciArticolo() {
            if (!this.suggerimento.titolo.trim()) return;
            try {
                const r = await axios.post(`${API}/bot.php`, {
                    azione: 'suggerisci_titolo',
                    titolo: this.suggerimento.titolo,
                    categoria: this.suggerimento.categoria,
                });
                this.mostraNotifica(r.data.tipo, r.data.messaggio);
                this.suggerimento.titolo = '';
                await this.caricaTitoliCoda();
            } catch(e) {
                this.mostraNotifica('error', e.response?.data?.messaggio || e.message);
            }
        },

        async suggerisciEGenera() {
            if (!this.suggerimento.titolo.trim()) return;
            this.botOccupato = true;
            this.notifica = null;
            try {
                // 1. Aggiungi alla coda
                const r = await axios.post(`${API}/bot.php`, {
                    azione: 'suggerisci_titolo',
                    titolo: this.suggerimento.titolo,
                    categoria: this.suggerimento.categoria,
                });
                const titoloId = r.data.id;
                this.suggerimento.titolo = '';
                await this.caricaTitoliCoda();
                // 2. Genera subito
                const r2 = await axios.post(`${API}/bot.php`, { azione: 'genera', titolo_id: titoloId });
                this.mostraNotifica(r2.data.tipo, r2.data.messaggio);
                await this.caricaArticoli();
                await this.caricaTitoliCoda();
                await this.caricaLog();
            } catch(e) {
                this.mostraNotifica('error', e.response?.data?.messaggio || e.message);
            } finally {
                this.botOccupato = false;
            }
        },

        apriArticolo(slug) {
            window.open(`../articolo/${slug}`, '_blank');
        },

        mostraNotifica(tipo, messaggio) {
            this.notifica = { tipo, messaggio };
            setTimeout(() => { this.notifica = null; }, 6000);
        },

        badgeClasse(stato) {
            return {
                'draft':       'bg-yellow-100 text-yellow-800',
                'pubblicato':  'bg-green-100 text-green-800',
                'schedulato':  'bg-blue-100 text-blue-800',
            }[stato] || 'bg-gray-100 text-gray-700';
        },

        formatData(d) {
            if (!d) return '';
            return new Date(d).toLocaleDateString('it-IT', { day: '2-digit', month: 'short', year: 'numeric' });
        },

        formatDataOra(d) {
            if (!d) return '';
            return new Date(d).toLocaleString('it-IT', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
        },
    },
}).mount('#app');
