<h1>Add wish to "<?= htmlspecialchars($wl['title']) ?>"</h1>

<form action="/wishlists/<?= (int)$wl['id'] ?>/wishes" method="post">
  <?= \OpenWishlist\Support\Csrf::field() ?>
  <div class="row">
    <label>Title<br><input type="text" name="title" maxlength="190" required autofocus></label>
  </div>
  <div class="row">
    <label>Product URL (optional)<br><input type="url" name="url" placeholder="https://…"></label>
  </div>
  <div class="row">
    <label>Price (optional)<br><input type="text" name="price" placeholder="e.g. 19.99"></label>
  </div>
  <div class="row">
    <label>Priority (1=high … 5=low, optional)<br>
      <select name="priority">
        <option value="">—</option>
        <?php for ($i=1;$i<=5;$i++): ?>
          <option value="<?= $i ?>"><?= $i ?></option>
        <?php endfor; ?>
      </select>
    </label>
  </div>
  <div class="row">
    <label>Notes (optional)<br><textarea name="notes" rows="3"></textarea></label>
  </div>
  <fieldset class="row">
    <legend>Image</legend>
    <label><input type="radio" name="image_mode" value="link" checked> Link (remote)</label>
    <label><input type="radio" name="image_mode" value="local"> Local (download & store)</label>
    <label>Image URL<br><input type="url" name="image_url" placeholder="https://…"></label>
  </fieldset>
  <div class="row">
    <button type="submit">Add wish</button>
    <a href="/wishlists/<?= (int)$wl['id'] ?>">Cancel</a>
  </div>
</form>
