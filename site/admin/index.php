<?php
/* Redigeringsværktøj til Teater Scenario. Selve værktøjet ligger i admin.js. */
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    http_response_code(500);
    exit('Redigeringsværktøjet kræver PHP 7.4 eller nyere. Serveren kører PHP ' . PHP_VERSION . '.');
}
require dirname(__DIR__) . '/inc/lib.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; img-src 'self' https: data: blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; connect-src 'self'; frame-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

$asset = function (string $f): string {
    $p = __DIR__ . '/' . $f;
    return $f . (is_file($p) ? '?v=' . filemtime($p) : '');
};
?>
<!doctype html>
<html lang="da">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Redigér hjemmesiden – Teater Scenario</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars($asset('admin.css')) ?>">
  <link rel="icon" href="../favicon.ico" sizes="any">
  <link rel="apple-touch-icon" href="../apple-touch-icon.png">
</head>
<body>
  <div id="app">
    <div class="boot"><div class="spinner" aria-hidden="true"></div><p>Indlæser…</p></div>
  </div>
  <noscript><p class="noscript">Redigeringsværktøjet kræver, at JavaScript er slået til i din browser.</p></noscript>
  <script src="<?= htmlspecialchars($asset('admin.js')) ?>"></script>
</body>
</html>
