<?php
// DOMUS — pagina dell'applicazione. Il contenuto viene disegnato da assets/js.
declare(strict_types=1);

require __DIR__ . '/src/App.php';
\Domus\App::boot();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
// Area Planimetrie: il motore CAD (Python) gira nel browser con Pyodide, caricato da cdn.jsdelivr.net;
// 'wasm-unsafe-eval' permette solo WebAssembly, non eval() né script inline.
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; font-src 'self'; style-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'wasm-unsafe-eval'; connect-src 'self' https://cdn.jsdelivr.net; frame-ancestors 'none'");

$v = static fn (string $f): string => $f . '?v=' . filemtime(__DIR__ . '/' . $f);
$name = htmlspecialchars((string) \Domus\App::config('app_name'), ENT_QUOTES);
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="dark">
  <meta name="theme-color" content="#0a0a0a">
  <link rel="preload" href="assets/fonts/inter-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
  <title><?= $name ?></title>
  <link rel="icon" href="<?= $v('assets/icon.svg') ?>" type="image/svg+xml">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <link rel="stylesheet" href="<?= $v('assets/css/domus.css') ?>">
</head>
<body>
  <div id="app" class="booting" aria-busy="true"></div>
  <script src="<?= $v('assets/js/core.js') ?>"></script>
  <script src="<?= $v('assets/js/icons.js') ?>"></script>
  <script src="<?= $v('assets/js/views.js') ?>"></script>
  <script src="<?= $v('assets/js/screens.js') ?>"></script>
  <script src="<?= $v('assets/js/cad.js') ?>"></script>
  <script src="<?= $v('assets/js/planimetrie.js') ?>"></script>
</body>
</html>
