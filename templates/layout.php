<?php /** @var string $title */ ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title ?? ($appSettings['app_name'] ?? 'OpenWishlist')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/css/pico.min.css">
</head>
<body>
  <main class="container">
    <nav>
      <ul>
        <li><strong><a href="/">🎁 <?= htmlspecialchars($appSettings['app_name'] ?? 'OpenWishlist') ?></a></strong></li>
      </ul>
      <ul>
        <?php if (\OpenWishlist\Support\Session::userId()): ?>
          <li><a href="/wishlists">My Lists</a></li>
          <li><a href="/wishlists/create">+ New List</a></li>
          <li>
            <form action="/logout" method="post">
              <?= \OpenWishlist\Support\Csrf::field() ?>
              <input type="submit" value="Logout" class="secondary">
            </form>
          </li>
        <?php else: ?>
          <li><a href="/login">Login</a></li>
          <?php if ($appSettings['public_registration'] ?? true): ?>
            <li><a href="/register">Register</a></li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>
    </nav>

    <?php if (!empty($flashError)): ?>
      <article style="background-color: var(--del-color); color: var(--del-color-text);">
        <?= htmlspecialchars($flashError) ?>
      </article>
    <?php endif; ?>
    <?php if (!empty($flashSuccess)): ?>
      <article style="background-color: var(--ins-color); color: var(--ins-color-text);">
        <?= htmlspecialchars($flashSuccess) ?>
      </article>
    <?php endif; ?>

    <div class="card">
      <?php require $tpl; ?>
    </div>
    <p style="margin-top:1rem;color:#666">
      Source: <a href="https://github.com/thomasbutzbach/openwishlist">OpenWishlist</a> <?= \OpenWishlist\Support\Version::formatDisplay() ?> – AGPLv3-or-later
    </p>
    </main>

    <!-- Confirm Modal -->
    <dialog id="confirmModal">
      <article style="max-width: 400px; margin: 0;">
        <header>
          <h4 id="confirmTitle">Confirm Action</h4>
        </header>
        <p id="confirmMessage">Are you sure?</p>
        <footer>
          <button id="confirmCancel" class="secondary">Cancel</button>
          <button id="confirmOk" class="primary">Confirm</button>
        </footer>
      </article>
    </dialog>

    <script>
    // Show/hide image URL field based on image mode selection
    document.addEventListener('DOMContentLoaded', function() {
      const imageUrlField = document.getElementById('image-url-field');
      const imageModeRadios = document.querySelectorAll('input[name="image_mode"]');
      
      if (imageUrlField && imageModeRadios.length > 0) {
        const urlInput = imageUrlField.querySelector('input[name="image_url"]');
        let originalUrl = '';
        
        // Store the original URL value on page load
        if (urlInput) {
          originalUrl = urlInput.value;
        }
        
        function updateImageUrlField() {
          const selectedMode = document.querySelector('input[name="image_mode"]:checked')?.value;
          if (selectedMode === 'none') {
            imageUrlField.style.display = 'none';
          } else {
            imageUrlField.style.display = 'block';
            // Restore original URL if input is empty
            if (urlInput && !urlInput.value && originalUrl) {
              urlInput.value = originalUrl;
            }
          }
        }
        
        // Set initial state
        updateImageUrlField();
        
        // Listen for changes
        imageModeRadios.forEach(radio => {
          radio.addEventListener('change', updateImageUrlField);
        });
        
        // Update stored URL when user types
        if (urlInput) {
          urlInput.addEventListener('input', function() {
            if (this.value) {
              originalUrl = this.value;
            }
          });
        }
      }
      
      // Confirm Modal System
      const confirmModal = document.getElementById('confirmModal');
      const confirmTitle = document.getElementById('confirmTitle');
      const confirmMessage = document.getElementById('confirmMessage');
      const confirmOk = document.getElementById('confirmOk');
      const confirmCancel = document.getElementById('confirmCancel');
      
      // Global confirm function
      window.showConfirm = function(message, title = 'Confirm Action') {
        return new Promise((resolve) => {
          confirmTitle.textContent = title;
          confirmMessage.textContent = message;
          confirmModal.showModal();
          
          const handleOk = () => {
            confirmModal.close();
            cleanup();
            resolve(true);
          };
          
          const handleCancel = () => {
            confirmModal.close();
            cleanup();
            resolve(false);
          };
          
          const cleanup = () => {
            confirmOk.removeEventListener('click', handleOk);
            confirmCancel.removeEventListener('click', handleCancel);
            confirmModal.removeEventListener('click', handleDialogClick);
          };
          
          const handleDialogClick = (e) => {
            if (e.target === confirmModal) {
              handleCancel();
            }
          };
          
          confirmOk.addEventListener('click', handleOk);
          confirmCancel.addEventListener('click', handleCancel);
          confirmModal.addEventListener('click', handleDialogClick);
        });
      };
      
      // Replace all confirm() calls with custom modal
      document.addEventListener('click', async function(e) {
        if (e.target.hasAttribute('data-confirm')) {
          e.preventDefault();
          const message = e.target.getAttribute('data-confirm');
          const confirmed = await showConfirm(message);
          
          if (confirmed) {
            if (e.target.tagName === 'A') {
              window.location.href = e.target.href;
            } else if (e.target.form) {
              e.target.form.submit();
            }
          }
        }
      });
    });
    </script>
</body>
</html>
