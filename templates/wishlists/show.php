<h1><?= htmlspecialchars($wl['title']) ?></h1>
<p><?= htmlspecialchars($wl['description'] ?? '') ?></p>

<p>
  Visibility: <strong><?= $wl['is_public'] ? 'Public' : 'Private' ?></strong>
  <?php if ($wl['is_public'] && !empty($wl['share_slug'])): ?>
    · Share link: <a href="/s/<?= htmlspecialchars($wl['share_slug']) ?>">/s/<?= htmlspecialchars($wl['share_slug']) ?></a>
  <?php endif; ?>
</p>

<p><a href="/wishlists/<?= (int)$wl['id'] ?>/edit">Edit list</a> ·
   <a href="/wishlists/<?= (int)$wl['id'] ?>/wishes/new">Add wish</a></p>

<?php if (empty($wishes)): ?>
  <p><em>No wishes yet.</em></p>
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
              <?php if ($w['priority']): ?>
                <li><strong>Priority:</strong> <?= (int)$w['priority'] ?>/5</li>
              <?php endif; ?>
              <li><strong>Image Mode:</strong> <?= htmlspecialchars($w['image_mode']) ?></li>
            </ul>
          </details>
          
          <footer>
            <a href="/wishes/<?= (int)$w['id'] ?>/edit" role="button" class="secondary">Edit</a>
            <form style="display: inline-block;" action="/wishes/<?= (int)$w['id'] ?>/delete" method="post" onsubmit="return confirm('Delete this wish?');">
              <?= \OpenWishlist\Support\Csrf::field() ?>
              <input type="submit" value="Delete" class="outline">
            </form>
          </footer>
        </div>
      </div>
    </article>
  <?php endforeach; ?>
<?php endif; ?>
