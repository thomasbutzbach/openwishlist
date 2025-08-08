<h1>My wishlists</h1>

<p><a href="/wishlists/create">+ Create wishlist</a></p>

<table style="width:100%; border-collapse: collapse">
  <thead>
    <tr>
      <th style="text-align:left; padding:.4rem; border-bottom:1px solid #ddd">Title</th>
      <th style="text-align:left; padding:.4rem; border-bottom:1px solid #ddd">Visibility</th>
      <th style="text-align:left; padding:.4rem; border-bottom:1px solid #ddd">Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach (($lists ?? []) as $l): ?>
    <tr>
      <td style="padding:.4rem"><?= htmlspecialchars($l['title']) ?></td>
      <td style="padding:.4rem">
        <?= $l['is_public'] ? 'Public' : 'Private' ?>
        <?php if (!empty($l['is_public']) && !empty($l['share_slug'])): ?>
          <div><small>Share: <a href="/s/<?= htmlspecialchars($l['share_slug']) ?>">/s/<?= htmlspecialchars($l['share_slug']) ?></a></small></div>
        <?php endif; ?>
      </td>
      <td style="padding:.4rem">
        <a href="/wishlists/<?= (int)$l['id'] ?>">Open</a> ·
        <a href="/wishlists/<?= (int)$l['id'] ?>/edit">Edit</a> ·
        <form class="inline" action="/wishlists/<?= (int)$l['id'] ?>/delete" method="post" onsubmit="return confirm('Delete this wishlist?');" style="display:inline">
          <?= \OpenWishlist\Support\Csrf::field() ?>
          <button type="submit">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
