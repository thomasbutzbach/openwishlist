<h1>Create wishlist</h1>
<form action="/wishlists" method="post">
  <?= \OpenWishlist\Support\Csrf::field() ?>
  <div class="row">
    <label>Title<br>
      <input type="text" name="title" maxlength="190" required autofocus>
    </label>
  </div>
  <div class="row">
    <label>Description<br>
      <input type="text" name="description">
    </label>
  </div>
  <div class="row">
    <label><input type="checkbox" name="is_public" value="1"> Public</label>
  </div>
  <div class="row">
    <button type="submit">Create</button>
  </div>
</form>
