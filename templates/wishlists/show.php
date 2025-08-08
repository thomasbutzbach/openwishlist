<h1><?= htmlspecialchars($wl['title']) ?></h1>
<p><?= htmlspecialchars($wl['description'] ?? '') ?></p>

<p>
  Visibility: <strong><?= $wl['is_public'] ? 'Public' : 'Private' ?></strong>
  <?php if ($wl['is_public'] && !empty($wl['share_slug'])): ?>
    · Share link: <a href="/s/<?= htmlspecialchars($wl['share_slug']) ?>">/s/<?= htmlspecialchars($wl['share_slug']) ?></a>
  <?php endif; ?>
</p>

<p><a href="/wishlists/<?= (int)$wl['id'] ?>/edit">Edit</a></p>

<hr>
<p>(Here we will list wishes in the next step.)</p>
