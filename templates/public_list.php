<hgroup>
  <h1><?= htmlspecialchars($wl['title']) ?></h1>
  <?php if (!empty($wl['description'])): ?>
    <p><?= htmlspecialchars($wl['description']) ?></p>
  <?php endif; ?>
</hgroup>

<article style="background-color: var(--primary-background); border-left: 4px solid var(--primary);">
  <strong>🌐 Public Wishlist</strong> - Anyone with this link can view this wishlist
</article>

<?php if (empty($wishes)): ?>
  <article>
    <header>
      <h3>No wishes yet</h3>
    </header>
    <p>This wishlist doesn't have any wishes yet.</p>
  </article>
<?php else: ?>
  <?php foreach ($wishes as $w): ?>
    <article>
      <div class="grid">
        <!-- Image -->
        <div>
          <?php if ($w['image_mode'] === 'none'): ?>
            <figure style="background-color: var(--muted-color); text-align: center; padding: 3rem; margin: 0;">
              <small>No Image</small>
            </figure>
          <?php elseif ($w['image_mode'] === 'link' && !empty($w['image_url'])): ?>
            <img src="<?= htmlspecialchars($w['image_url']) ?>" 
                 alt="<?= htmlspecialchars($w['title']) ?>" 
                 style="width: 100%; height: 200px; object-fit: contain; background-color: var(--muted-color);">
          <?php elseif ($w['image_mode'] === 'local' && $w['image_status'] === 'ok' && !empty($w['image_path'])): ?>
            <img src="/<?= htmlspecialchars($w['image_path']) ?>" 
                 alt="<?= htmlspecialchars($w['title']) ?>" 
                 style="width: 100%; height: 200px; object-fit: contain; background-color: var(--muted-color);">
          <?php elseif ($w['image_mode'] === 'local'): ?>
            <figure style="background-color: var(--muted-color); text-align: center; padding: 3rem; margin: 0;">
              <small><?= $w['image_status'] === 'pending' ? 'Processing...' : 'Failed to load' ?></small>
            </figure>
          <?php endif; ?>
        </div>

        <!-- Content -->
        <div>
          <header>
            <?php if ($w['priority']): ?>
              <?php
              $priorityLabel = '';
              $priorityColor = '#999';
              $priorityIcon = '';
              
              switch((int)$w['priority']) {
                case 1: $priorityLabel = 'Highest'; $priorityColor = '#dc3545'; $priorityIcon = '🔴'; break;
                case 2: $priorityLabel = 'High'; $priorityColor = '#fd7e14'; $priorityIcon = '🟠'; break;  
                case 3: $priorityLabel = 'Medium'; $priorityColor = '#ffc107'; $priorityIcon = '🟡'; break;
                case 4: $priorityLabel = 'Low'; $priorityColor = '#20c997'; $priorityIcon = '🟢'; break;
                case 5: $priorityLabel = 'Lowest'; $priorityColor = '#6c757d'; $priorityIcon = '⚫'; break;
              }
              ?>
              <small style="color: <?= $priorityColor ?>; font-weight: bold; font-size: 0.8em;"><?= $priorityIcon ?> <?= $priorityLabel ?></small>
            <?php endif; ?>
            
            <h4>
              <?php if (!empty($w['url'])): ?>
                <a href="<?= htmlspecialchars($w['url']) ?>" target="_blank">
                  <?= htmlspecialchars($w['title']) ?> ↗
                </a>
              <?php else: ?>
                <?= htmlspecialchars($w['title']) ?>
              <?php endif; ?>
            </h4>
          </header>
          
          <?php if (!empty($w['notes'])): ?>
            <p><?= nl2br(htmlspecialchars($w['notes'])) ?></p>
          <?php endif; ?>
          
          <details>
            <summary>Details</summary>
            <ul>
              <?php if (isset($w['price_cents'])): ?>
                <li><strong>Price:</strong> €<?= number_format(((int)$w['price_cents'])/100, 2, ',', '.') ?></li>
              <?php endif; ?>
              <li><strong>Image Mode:</strong> <?= htmlspecialchars($w['image_mode']) ?></li>
            </ul>
          </details>
        </div>
      </div>
    </article>
  <?php endforeach; ?>
<?php endif; ?>
