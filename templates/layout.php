<?php /** @var string $title */ ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title ?? 'OpenWishlist') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root { color-scheme: light dark; }
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 2rem; }
    .container { max-width: 820px; margin: 0 auto; }
    .card { border: 1px solid #ddd; border-radius: 12px; padding: 1rem 1.25rem; }
    .row { margin-bottom: 1rem; }
    input, button { font-size: 1rem; padding: .6rem .7rem; border-radius: .6rem; border: 1px solid #bbb; width: 100%; }
    button { border-color: #333; cursor: pointer; }
    .alert { padding: .7rem .9rem; border-radius: .6rem; margin-bottom: .8rem; }
    .alert.error { background: #ffe5e5; border: 1px solid #ffb3b3; }
    .alert.success { background: #e6ffea; border: 1px solid #b3ffbf; }
    nav a { margin-right: .8rem; }
    form.inline { display: inline; }
  </style>
</head>
<body>
  <div class="container">
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
  </div>
</body>
</html>
