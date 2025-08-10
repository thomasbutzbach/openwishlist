<?php /** @var string $title */ ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title ?? 'OpenWishlist') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/css/pico.min.css">
</head>
<body>
  <main class="container">
    <nav class="row">
      <a href="/">Home</a>
      <?php if (\OpenWishlist\Support\Session::userId()): ?>
        <form class="inline" action="/logout" method="post">
          <?= \OpenWishlist\Support\Csrf::field() ?>
          <button type="submit">Logout</button>
        </form>
      <?php else: ?>
        <a href="/login">Login</a>
        <a href="/register">Register</a>
      <?php endif; ?>
    </nav>

    <?php if (!empty($flashError)): ?>
      <div class="alert error"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>
    <?php if (!empty($flashSuccess)): ?>
      <div class="alert success"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>

    <div class="card">
      <?php require $tpl; ?>
    </div>
    <p style="margin-top:1rem;color:#666">
      Source: <a href="https://github.com/thomasbutzbach/openwishlist">OpenWishlist</a> – AGPLv3-or-later
    </p>
    </main>
</body>
</html>
