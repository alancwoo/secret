<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="robots" content="noindex">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="/css/app.css" rel="stylesheet">
  <script src="/js/app.js"></script>
  <title><?= htmlspecialchars($title ?? 'Secret') ?></title>
  <link rel="apple-touch-icon" sizes="180x180" href="/images/favicon/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon/favicon-16x16.png">
  <link rel="manifest" href="/images/favicon/site.webmanifest">
  <meta name="msapplication-config" content="/images/favicon/browserconfig.xml">
  <meta name="msapplication-TileColor" content="#ffffff">
  <meta name="theme-color" content="#ffffff">
</head>
<body>
<div class="container">
  <?= $content ?>
</div>
<a href="https://github.com/alancwoo/secret" class="footer-link" title="Secret on Github">&#x3C0;</a>
</body>
</html>
