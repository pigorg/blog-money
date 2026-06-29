-- Blog Money - Schema Database
-- Eseguire una volta su cPanel > phpMyAdmin

CREATE DATABASE IF NOT EXISTS blog_money CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE blog_money;

-- 1. SORGENTI
CREATE TABLE sorgenti (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nome VARCHAR(100) NOT NULL,
  url VARCHAR(255) NOT NULL,
  tipo ENUM('rss','scrape','api') DEFAULT 'rss',
  attiva BOOLEAN DEFAULT TRUE,
  ultima_sincronizzazione TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. TITOLI ESTRATTI
CREATE TABLE titoli_estratti (
  id INT PRIMARY KEY AUTO_INCREMENT,
  sorgente_id INT NOT NULL,
  titolo_originale VARCHAR(255) NOT NULL,
  url_originale VARCHAR(500),
  categoria VARCHAR(100),
  stato ENUM('nuovo','elaborato','scartato') DEFAULT 'nuovo',
  data_estrazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sorgente_id) REFERENCES sorgenti(id) ON DELETE CASCADE,
  INDEX idx_stato (stato),
  INDEX idx_data (data_estrazione)
);

-- 3. ARTICOLI GENERATI
CREATE TABLE articoli (
  id INT PRIMARY KEY AUTO_INCREMENT,
  titolo_estratto_id INT NOT NULL,
  titolo_finale VARCHAR(255) NOT NULL,
  slug VARCHAR(255) UNIQUE NOT NULL,
  contenuto LONGTEXT NOT NULL,
  excerpt VARCHAR(500),
  meta_description VARCHAR(160),
  keywords VARCHAR(255),
  categoria VARCHAR(100),
  immagine_url VARCHAR(500),
  immagine_alt VARCHAR(255),
  immagine_piccola_url VARCHAR(500),
  immagine_piccola_alt VARCHAR(255),
  stato ENUM('draft','pubblicato','schedulato') DEFAULT 'draft',
  data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  data_pubblicazione TIMESTAMP NULL,
  data_scheduling TIMESTAMP NULL,
  fonte_url VARCHAR(500),
  tempo_lettura INT,
  visite INT DEFAULT 0,
  FOREIGN KEY (titolo_estratto_id) REFERENCES titoli_estratti(id) ON DELETE CASCADE,
  INDEX idx_stato (stato),
  INDEX idx_categoria (categoria),
  INDEX idx_slug (slug),
  INDEX idx_data_pub (data_pubblicazione)
);

-- 4. CONFIGURAZIONI BOT
CREATE TABLE configurazioni (
  id INT PRIMARY KEY AUTO_INCREMENT,
  chiave VARCHAR(100) UNIQUE NOT NULL,
  valore TEXT NOT NULL,
  descrizione VARCHAR(255),
  tipo ENUM('string','int','boolean','json') DEFAULT 'string',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 5. LOG ELABORAZIONI
CREATE TABLE log_elaborazioni (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tipo ENUM('estrazione','generazione','pubblicazione','errore') NOT NULL,
  articolo_id INT,
  messaggio TEXT,
  status ENUM('success','error','warning') DEFAULT 'success',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (articolo_id) REFERENCES articoli(id) ON DELETE SET NULL,
  INDEX idx_tipo (tipo),
  INDEX idx_created (created_at)
);

-- 6. TEMPLATE
CREATE TABLE template (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nome VARCHAR(100) UNIQUE NOT NULL,
  html LONGTEXT NOT NULL,
  css TEXT,
  javascript TEXT,
  attivo BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- DATI INIZIALI
INSERT INTO sorgenti (nome, url, tipo) VALUES
-- 🇮🇹 Blog Italiani - Finanza Personale
('Finanza Cafona',              'https://finanzacafona.it/feed/',                                    'rss'),
('Rendite Passive',             'https://renditepassive.net/feed/',                                  'rss'),
('Davide Marciano',             'https://davidemarciano.com/feed/',                                  'rss'),
('Angelo Colombo',              'https://angelocolomboeducazionefinanziaria.com/feed/',               'rss'),
('Investimi',                   'https://investimi.com/feed/',                                        'rss'),
('Italia Personal Finance',     'https://www.italiapersonalfinance.it/blog/feed/',                   'rss'),
('Moneyfarm Blog',              'https://blog.moneyfarm.com/it/feed/',                               'rss'),
('Alfio Bardolla',              'https://www.alfiobardolla.com/feed/',                               'rss'),
('The Italian Leather Sofa',    'https://theitalianleathersofa.com/feed/',                           'rss'),
-- 🇮🇹 News e Analisi Italiane
('Il Sole 24 Ore',              'https://www.ilsole24ore.com/rss/finanza.xml',                       'rss'),
('Corriere Economia',           'https://www.corriere.it/rss/economia.xml',                          'rss'),
('Repubblica Economia',         'https://www.repubblica.it/rss/economia/rss2.0.xml',                 'rss'),
('ANSA Economia',               'https://www.ansa.it/sito/notizie/economia/economia_rss.xml',        'rss'),
('Money.it',                    'https://www.money.it/rss/news.xml',                                 'rss'),
('Milano Finance Times',        'https://www.milanofintimes.com/feed/',                              'rss'),
('AGI Economia',                'https://www.agi.it/feed/economia',                                  'rss'),
-- 🇺🇸 Blog USA - Top Authority
('Mr Money Mustache',           'https://www.mrmoneymustache.com/feed/',                             'rss'),
('Financial Samurai',           'https://www.financialsamurai.com/feed/',                            'rss'),
('The Simple Dollar',           'https://www.thesimpledollar.com/feed/',                             'rss'),
('Get Rich Slowly',             'https://www.getrichslowly.org/feed/',                               'rss'),
('Afford Anything',             'https://affordanything.com/feed/',                                  'rss'),
('Of Dollars and Data',         'https://ofdollarsanddata.com/feed/',                                'rss'),
-- 🇺🇸 Blog USA - FIRE & Early Retirement
('Think Save Retire',           'https://thinksaveretire.com/feed/',                                 'rss'),
('Frugalwoods',                 'https://www.frugalwoods.com/feed/',                                 'rss'),
('Mad Fientist',                'https://www.madfientist.com/feed/',                                 'rss'),
-- 🇺🇸 Blog USA - Content & Millennials
('Money Crashers',              'https://www.moneycrashers.com/feed/',                               'rss'),
('Money Talks News',            'https://www.moneytalksnews.com/feed/',                              'rss'),
('Wallet Hacks',                'https://www.wallethacks.com/feed/',                                 'rss'),
('The College Investor',        'https://www.thecollegeinvestor.com/feed/',                          'rss'),
('Money Under 30',              'https://www.moneyunder30.com/feed/',                                'rss'),
('Millennial Money',            'https://millennialmoney.com/feed/',                                 'rss'),
('Side Hustle Nation',          'https://www.sidehustlenation.com/feed/',                            'rss'),
('My Wife Quit Her Job',        'https://www.mywifequitherjob.com/feed/',                            'rss'),
-- 🇺🇸 News & Mercati Internazionali
('MarketWatch',                 'https://feeds.content.dowjones.io/public/rss/mw_topstories',        'rss'),
('Reuters Business',            'https://feeds.reuters.com/reuters/businessNews',                    'rss'),
('CNBC Business',               'https://www.cnbc.com/id/100003114/device/rss/rss.html',            'rss'),
('Yahoo Finance',               'https://finance.yahoo.com/news/rssindex',                           'rss'),
-- 🌐 Community & Forum
('Reddit personalfinance',      'https://www.reddit.com/r/personalfinance/.rss',                     'rss'),
('Reddit financialindependence','https://www.reddit.com/r/financialindependence/.rss',               'rss');

INSERT INTO configurazioni (chiave, valore, descrizione, tipo) VALUES
('articoli_al_giorno', '1', 'Numero articoli da generare ogni giorno', 'int'),
('lunghezza_articolo', '1500', 'Parole minime per articolo', 'int'),
('claude_model', 'claude-sonnet-4-6', 'Modello Claude da usare', 'string'),
('orario_pubblicazione', '09:00', 'Orario pubblicazione automatica (HH:MM)', 'string'),
('categoria_default', 'Finanza', 'Categoria di default per nuovi articoli', 'string'),
('lingua', 'it', 'Lingua degli articoli generati', 'string');

INSERT INTO template (nome, html, css, attivo) VALUES ('default', '<article>{{contenuto}}</article>', '', TRUE);

-- Se il database esiste già, eseguire solo questo:
-- ALTER TABLE articoli
--   ADD COLUMN immagine_piccola_url VARCHAR(500) AFTER immagine_alt,
--   ADD COLUMN immagine_piccola_alt VARCHAR(255) AFTER immagine_piccola_url;
