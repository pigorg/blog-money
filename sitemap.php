<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/Database.php';

header('Content-Type: application/xml; charset=utf-8');

$database = new Database();
$database->connect();
$db = $database->getConn();

$articoli = $db->query(
    "SELECT slug, categoria, data_pubblicazione FROM articoli WHERE stato = 'pubblicato' ORDER BY data_pubblicazione DESC"
)->fetch_all(MYSQLI_ASSOC);

$categorie = $db->query(
    "SELECT DISTINCT categoria FROM articoli WHERE stato = 'pubblicato'"
)->fetch_all(MYSQLI_ASSOC);

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">

  <url>
    <loc><?= SITE_URL ?>/</loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>

  <?php foreach ($categorie as $c): ?>
  <url>
    <loc><?= SITE_URL ?>/?categoria=<?= urlencode($c['categoria']) ?></loc>
    <changefreq>daily</changefreq>
    <priority>0.7</priority>
  </url>
  <?php endforeach; ?>

  <?php foreach ($articoli as $a): ?>
  <url>
    <loc><?= SITE_URL ?>/articolo/<?= $a['slug'] ?></loc>
    <lastmod><?= date('Y-m-d', strtotime($a['data_pubblicazione'])) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
  <?php endforeach; ?>

</urlset>
