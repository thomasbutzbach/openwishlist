<h1><?= htmlspecialchars($wl['title']) ?></h1>
<p><?= htmlspecialchars($wl['description'] ?? '') ?></p>

<p>
  Visibility: <strong><?= $wl['is_public'] ? 'Public' : 'Private' ?></strong>
  <?php if ($wl['is_public'] && !empty($wl['share_slug'])): ?>
    · Share link: <a href="/s/<?= htmlspecialchars($wl['share_slug']) ?>">/s/<?= htmlspecialchars($wl['share_slug']) ?></a>
  <?php endif; ?>
</p>

<p><a href="/wishlists/<?= (int)$wl['id'] ?>/edit">Edit list</a> ·
   <a href="/wishlists/<?= (int)$wl['id'] ?>/wishes/new">Add wish</a> ·
   <a href="/wishlists/<?= (int)$wl['id'] ?>/export/csv">Export CSV</a> ·
   <a href="/wishlists/<?= (int)$wl['id'] ?>/export/json">Export JSON</a> ·
   <a href="/wishlists/<?= (int)$wl['id'] ?>/export/pdf" target="_blank">Export PDF</a></p>

<form method="GET" style="margin-bottom: 1rem;">
  <div class="grid">
    <input type="search" name="search" placeholder="Search wishes..." value="<?= htmlspecialchars($search ?? '') ?>">
    <button type="submit">Search</button>
  </div>
  <?php if (!empty($search)): ?>
    <small><a href="?">Clear search</a></small>
  <?php endif; ?>
</form>

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
  
  <?php if ($pagination['totalPages'] > 1): ?>
    <nav aria-label="Pagination">
      <ul style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; list-style: none; padding: 0;">
        <?php if ($pagination['hasPrevPage']): ?>
          <li><a href="?page=<?= $pagination['currentPage'] - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" role="button" class="secondary">← Previous</a></li>
        <?php endif; ?>
        
        <?php
        $start = max(1, $pagination['currentPage'] - 2);
        $end = min($pagination['totalPages'], $pagination['currentPage'] + 2);
        $searchParam = !empty($search) ? '&search=' . urlencode($search) : '';
        ?>
        
        <?php if ($start > 1): ?>
          <li><a href="?page=1<?= $searchParam ?>" role="button" class="outline">1</a></li>
          <?php if ($start > 2): ?><li>...</li><?php endif; ?>
        <?php endif; ?>
        
        <?php for ($i = $start; $i <= $end; $i++): ?>
          <li>
            <?php if ($i === $pagination['currentPage']): ?>
              <span role="button" aria-current="page"><?= $i ?></span>
            <?php else: ?>
              <a href="?page=<?= $i ?><?= $searchParam ?>" role="button" class="outline"><?= $i ?></a>
            <?php endif; ?>
          </li>
        <?php endfor; ?>
        
        <?php if ($end < $pagination['totalPages']): ?>
          <?php if ($end < $pagination['totalPages'] - 1): ?><li>...</li><?php endif; ?>
          <li><a href="?page=<?= $pagination['totalPages'] ?><?= $searchParam ?>" role="button" class="outline"><?= $pagination['totalPages'] ?></a></li>
        <?php endif; ?>
        
        <?php if ($pagination['hasNextPage']): ?>
          <li><a href="?page=<?= $pagination['currentPage'] + 1 ?><?= $searchParam ?>" role="button" class="secondary">Next →</a></li>
        <?php endif; ?>
      </ul>
      <p style="text-align: center; margin-top: 0.5rem; color: #666; font-size: 0.9em;">
        Showing <?= min($pagination['perPage'], $pagination['totalCount'] - ($pagination['currentPage'] - 1) * $pagination['perPage']) ?> 
        of <?= $pagination['totalCount'] ?> wishes
      </p>
    </nav>
  <?php endif; ?>
<?php endif; ?>
