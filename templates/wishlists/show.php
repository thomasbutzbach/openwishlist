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
   <a href="/wishlists/<?= (int)$wl['id'] ?>/import">Import CSV</a> ·
   <a href="/wishlists/<?= (int)$wl['id'] ?>/export/csv">Export CSV</a> ·
   <a href="/wishlists/<?= (int)$wl['id'] ?>/export/json">Export JSON</a> ·
   <a href="/wishlists/<?= (int)$wl['id'] ?>/export/pdf" target="_blank">Export PDF</a></p>

<form method="GET" style="margin-bottom: 1rem;">
  <div style="display: flex; gap: 0.5rem; align-items: stretch;">
    <input type="search" name="search" placeholder="Search wishes..." value="<?= htmlspecialchars($search ?? '') ?>" style="flex-grow: 1; min-width: 200px;">
    <input type="submit" value="Search" style="flex-grow: 0; width: 80px; padding: 0.5rem; font-size: 0.85em;">
  </div>
  <?php if (!empty($search)): ?>
    <small><a href="?">Clear search</a></small>
  <?php endif; ?>
</form>

<?php if (empty($wishes)): ?>
  <p><em>No wishes yet.</em></p>
<?php else: ?>
  <?php foreach ($wishes as $w): ?>
    <article style="margin-bottom: 0.4rem; padding: 0.3rem 0.75rem; font-size: 0.85em;">
      <div class="grid" style="grid-template-columns: 45px 1fr auto; gap: 0.75rem; align-items: center;">
        <!-- Compact Image -->
        <div>
          <?php if ($w['image_mode'] === 'none'): ?>
            <div style="width: 45px; height: 60px; background-color: var(--muted-color); display: flex; align-items: center; justify-content: center; border-radius: 3px;">
              <small style="color: var(--muted-border-color); font-size: 0.65em;">No Image</small>
            </div>
          <?php elseif ($w['image_mode'] === 'link' && !empty($w['image_url'])): ?>
            <img src="<?= htmlspecialchars($w['image_url']) ?>"
                 alt="<?= htmlspecialchars($w['title']) ?>"
                 style="width: 45px; height: 60px; object-fit: cover; border-radius: 3px; background-color: var(--muted-color);">
          <?php elseif ($w['image_mode'] === 'local' && $w['image_status'] === 'ok' && !empty($w['image_path'])): ?>
            <img src="/<?= htmlspecialchars($w['image_path']) ?>"
                 alt="<?= htmlspecialchars($w['title']) ?>"
                 style="width: 45px; height: 60px; object-fit: cover; border-radius: 3px; background-color: var(--muted-color);">
          <?php elseif ($w['image_mode'] === 'local'): ?>
            <div style="width: 45px; height: 60px; background-color: var(--muted-color); display: flex; align-items: center; justify-content: center; border-radius: 3px;">
              <small style="color: var(--muted-border-color); font-size: 0.65em;"><?= $w['image_status'] === 'pending' ? '...' : 'Error' ?></small>
            </div>
          <?php endif; ?>
        </div>

        <!-- Content -->
        <div>
          <div style="display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.2rem;">
            <?php if ($w['priority']): ?>
              <?php
              $priorityIcon = '';
              switch((int)$w['priority']) {
                case 1: $priorityIcon = '🔴'; break;
                case 2: $priorityIcon = '🟠'; break;
                case 3: $priorityIcon = '🟡'; break;
                case 4: $priorityIcon = '🟢'; break;
                case 5: $priorityIcon = '⚫'; break;
              }
              ?>
              <span style="font-size: 0.7em;"><?= $priorityIcon ?></span>
            <?php endif; ?>

            <strong style="margin: 0; font-size: 0.95em; font-weight: 600;">
              <?php if (!empty($w['url'])): ?>
                <a href="<?= htmlspecialchars($w['url']) ?>" target="_blank" style="text-decoration: none; color: inherit;">
                  <?= htmlspecialchars($w['title']) ?> <span style="opacity: 0.5; font-size: 0.8em;">↗</span>
                </a>
              <?php else: ?>
                <?= htmlspecialchars($w['title']) ?>
              <?php endif; ?>
            </strong>
          </div>

          <?php if (!empty($w['notes'])): ?>
            <div style="margin: 0.15rem 0; font-size: 0.8em; color: var(--muted-color); line-height: 1.2;">
              <?= htmlspecialchars(strlen($w['notes']) > 80 ? substr($w['notes'], 0, 80) . '...' : $w['notes']) ?>
            </div>
          <?php endif; ?>

          <?php if (isset($w['price_cents'])): ?>
            <div style="color: var(--primary); font-size: 0.9em; font-weight: 600; margin-top: 0.2rem;">€<?= number_format(((int)$w['price_cents'])/100, 2, ',', '.') ?></div>
          <?php endif; ?>
        </div>

        <!-- Actions -->
        <div style="display: flex; flex-direction: column; gap: 0.25rem; min-width: 50px; justify-content: center; align-self: center;">
          <a href="/wishes/<?= (int)$w['id'] ?>/edit" style="padding: 0.2rem 0.3rem; font-size: 0.7em; text-align: center; color: var(--secondary); text-decoration: none; background: transparent; line-height: 1;">✏️ Edit</a>
          <form style="margin: 0;" action="/wishes/<?= (int)$w['id'] ?>/delete" method="post" onsubmit="return confirm('Delete this wish?');">
            <?= \OpenWishlist\Support\Csrf::field() ?>
            <input type="submit" value="🗑️ Delete" style="width: 100%; padding: 0.2rem 0.3rem; font-size: 0.7em; background-color: transparent; color: var(--secondary); border: none; cursor: pointer; line-height: 1;">
          </form>
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
