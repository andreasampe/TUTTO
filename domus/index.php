<?php
// DOMUS — pagina dell'applicazione. Il contenuto viene disegnato da assets/js.
declare(strict_types=1);

require __DIR__ . '/src/App.php';
\Domus\App::boot();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; frame-ancestors 'none'");

$v = static fn (string $f): string => $f . '?v=' . filemtime(__DIR__ . '/' . $f);
$name = htmlspecialchars((string) \Domus\App::config('app_name'), ENT_QUOTES);
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <title><?= $name ?></title>
  <link rel="icon" href="<?= $v('assets/icon.svg') ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= $v('assets/css/domus.css') ?>">
</head>
<body>
  <div id="app" class="booting" aria-busy="true"></div>
  <script src="<?= $v('assets/js/core.js') ?>"></script>
  <script src="<?= $v('assets/js/views.js') ?>"></script>
</body>
</html>
