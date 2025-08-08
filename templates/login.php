<h1>Login</h1>
<form action="/login" method="post">
  <?= \OpenWishlist\Support\Csrf::field() ?>
  <div class="row">
    <label>Email<br>
      <input type="email" name="email" required autofocus>
    </label>
  </div>
  <div class="row">
    <label>Password<br>
      <input type="password" name="password" required>
    </label>
  </div>
  <div class="row">
    <button type="submit">Login</button>
  </div>
  <p>No account? <a href="/register">Register</a></p>
</form>
