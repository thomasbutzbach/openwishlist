<h1>Edit wishlist</h1>
<form action="/wishlists/<?= (int)$wl['id'] ?>" method="post">
  <?= \OpenWishlist\Support\Csrf::field() ?>
  <div class="row">
    <label>Title<br>
      <input type="text" name="title" maxlength="190" required value="<?= htmlspecialchars($wl['title']) ?>">
    </label>
  </div>
  <div class="row">
    <label>Description<br>
      <input type="text" name="description" value="<?= htmlspecialchars($wl['description'] ?? '') ?>">
    </label>
  </div>
  <div class="row">
    <label><input type="checkbox" name="is_public" value="1" <?= $wl['is_public'] ? 'checked' : '' ?>> Public</label>
    <?php if ($wl['is_public'] && !empty($wl['share_slug'])): ?>
      <div><small>Share: <a href="/s/<?= htmlspecialchars($wl['share_slug']) ?>">/s/<?= htmlspecialchars($wl['share_slug']) ?></a></small></div>
    <?php endif; ?>
  </div>
  <div class="row">
    <button type="submit">Save</button>
  </div>
</form>

<form action="/wishlists/<?= (int)$wl['id'] ?>/toggle-public" method="post" style="margin-top:1rem">
  <?= \OpenWishlist\Support\Csrf::field() ?>
  <button type="submit"><?= $wl['is_public'] ? 'Make private' : 'Make public' ?></button>
</form>
