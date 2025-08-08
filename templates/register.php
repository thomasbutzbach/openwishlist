<h1>Register</h1>
<form action="/register" method="post">
  <?= \OpenWishlist\Support\Csrf::field() ?>
  <div class="row">
    <label>Email<br>
      <input type="email" name="email" required autofocus>
    </label>
  </div>
  <div class="row">
    <label>Password (min 10 chars)<br>
      <input type="password" name="password" minlength="10" required>
    </label>
  </div>
  <div class="row">
    <label>Confirm Password<br>
      <input type="password" name="passwordConfirm" minlength="10" required>
    </label>
  </div>
  <div class="row">
    <button type="submit">Create account</button>
  </div>
  <p>Already have an account? <a href="/login">Login</a></p>
</form>
