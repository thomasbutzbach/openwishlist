<h1>Edit wish</h1>

<form action="/wishes/<?= (int)$wish['id'] ?>" method="post">
  <?= \OpenWishlist\Support\Csrf::field() ?>
  <div class="row">
    <label>Title<br>
      <input type="text" name="title" maxlength="190" required value="<?= htmlspecialchars($wish['title']) ?>">
    </label>
  </div>
  <div class="row">
    <label>Product URL<br>
      <input type="url" name="url" value="<?= htmlspecialchars($wish['url'] ?? '') ?>">
    </label>
  </div>
  <div class="row">
    <label>Price<br>
      <input type="text" name="price" value="<?php
        echo isset($wish['price_cents']) ? number_format(((int)$wish['price_cents'])/100, 2, '.', '') : '' ?>">
    </label>
  </div>
  <div class="row">
    <label>Priority<br>
      <select name="priority">
        <option value="">—</option>
        <?php for ($i=1;$i<=5;$i++): ?>
          <option value="<?= $i ?>" <?= ((int)($wish['priority'] ?? 0) === $i) ? 'selected' : '' ?>><?= $i ?></option>
        <?php endfor; ?>
      </select>
    </label>
  </div>
  <div class="row">
    <label>Notes<br><textarea name="notes" rows="3"><?= htmlspecialchars($wish['notes'] ?? '') ?></textarea></label>
  </div>
  <fieldset class="row">
    <legend>Image</legend>
    <label><input type="radio" name="image_mode" value="link" <?= $wish['image_mode']==='link'?'checked':'' ?>> Link</label>
    <label><input type="radio" name="image_mode" value="local" <?= $wish['image_mode']==='local'?'checked':'' ?>> Local</label>
    <label>Image URL<br>
      <input type="url" name="image_url" value="<?= htmlspecialchars($wish['image_url'] ?? '') ?>">
    </label>
    <p><small>Status: <strong><?= htmlspecialchars($wish['image_status'] ?? 'pending') ?></strong></small></p>
  </fieldset>
  <div class="row">
    <button type="submit">Save</button>
    <a href="/wishlists/<?= (int)$wish['wishlist_id'] ?>">Back</a>
  </div>
</form>

<form action="/wishes/<?= (int)$wish['id'] ?>/delete" method="post" style="margin-top:1rem" onsubmit="return confirm('Delete this wish?');">
  <?= \OpenWishlist\Support\Csrf::field() ?>
  <button type="submit">Delete wish</button>
</form>
