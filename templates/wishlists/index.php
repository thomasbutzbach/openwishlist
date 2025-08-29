<hgroup>
  <h1>My Wishlists</h1>
  <p>Manage your personal wishlists</p>
</hgroup>

<?php if (empty($lists)): ?>
  <article>
    <header>
      <h3>No wishlists yet</h3>
    </header>
    <p>Create your first wishlist to get started!</p>
    <footer>
      <a href="/wishlists/create" role="button">Create Your First Wishlist</a>
    </footer>
  </article>
<?php else: ?>
  <?php foreach ($lists as $l): ?>
    <article>
      <header>
        <h3><a href="/wishlists/<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['title']) ?></a></h3>
        <?php if (!empty($l['description'])): ?>
          <p><?= htmlspecialchars($l['description']) ?></p>
        <?php endif; ?>
      </header>
      
      <p>
        <mark><?= $l['is_public'] ? '🌐 Public' : '🔒 Private' ?></mark>
        <?php if (!empty($l['is_public']) && !empty($l['share_slug'])): ?>
          <br><small>Share: <a href="/s/<?= htmlspecialchars($l['share_slug']) ?>">/s/<?= htmlspecialchars($l['share_slug']) ?></a></small>
        <?php endif; ?>
      </p>
      
      <footer>
        <a href="/wishlists/<?= (int)$l['id'] ?>" role="button">Open</a>
        <a href="/wishlists/<?= (int)$l['id'] ?>/edit" role="button" class="secondary">Edit</a>
        <form style="display: inline-block;" action="/wishlists/<?= (int)$l['id'] ?>/delete" method="post" onsubmit="return confirm('Delete this wishlist and all its wishes?');">
          <?= \OpenWishlist\Support\Csrf::field() ?>
          <input type="submit" value="Delete" class="outline">
        </form>
      </footer>
    </article>
  <?php endforeach; ?>
<?php endif; ?>
