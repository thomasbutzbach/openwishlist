<hgroup>
  <h1>My Wishlists</h1>
  <p>Manage your personal wishlists</p>
</hgroup>

<form method="GET" style="margin-bottom: 1rem;">
  <div style="display: flex; gap: 0.5rem; align-items: stretch;">
    <input type="search" name="search" placeholder="Search wishlists..." value="<?= htmlspecialchars($search ?? '') ?>" style="flex-grow: 1; min-width: 200px;">
    <input type="submit" value="Search" style="flex-grow: 0; width: 80px; padding: 0.5rem; font-size: 0.85em;">
  </div>
  <?php if (!empty($search)): ?>
    <small><a href="/wishlists">Clear search</a></small>
  <?php endif; ?>
</form>

<?php if (empty($lists)): ?>
  <article>
    <header>
      <h3>No wishlists yet</h3>
    </header>
    <p>Create your first wishlist to get started!</p>
    <footer>
      <a href="/wishlists/create" role="button">Create Your First Wishlist</a>
    </footer>
  </article>
<?php else: ?>
  <?php foreach ($lists as $l): ?>
    <article style="margin-bottom: 0.4rem; padding: 0.4rem 0.75rem; font-size: 0.9em;">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <!-- Main Content -->
        <div style="flex: 1;">
          <div style="margin-bottom: 0.2rem;">
            <strong style="font-size: 1.1em;">
              <a href="/wishlists/<?= (int)$l['id'] ?>" style="text-decoration: none; color: inherit;">
                <?= htmlspecialchars($l['title']) ?>
              </a>
            </strong>
            <span style="margin-left: 0.5rem; font-size: 0.8em;">
              <?= $l['is_public'] ? '🌐 Public' : '🔒 Private' ?>
            </span>
          </div>
          
          <?php if (!empty($l['description'])): ?>
            <div style="font-size: 0.85em; color: var(--muted-color); line-height: 1.3; margin-bottom: 0.2rem;">
              <?= htmlspecialchars(strlen($l['description']) > 120 ? substr($l['description'], 0, 120) . '...' : $l['description']) ?>
            </div>
          <?php endif; ?>
          
          <?php if (!empty($l['is_public']) && !empty($l['share_slug'])): ?>
            <div style="font-size: 0.75em; color: var(--muted-color);">
              Share: <a href="/s/<?= htmlspecialchars($l['share_slug']) ?>" style="color: var(--primary); text-decoration: none;">/s/<?= htmlspecialchars($l['share_slug']) ?></a>
            </div>
          <?php endif; ?>
        </div>
        
        <!-- Actions -->
        <div style="display: flex; flex-direction: column; gap: 0.25rem; min-width: 50px;">
          <a href="/wishlists/<?= (int)$l['id'] ?>/edit" style="padding: 0.2rem 0.3rem; font-size: 0.7em; text-align: center; color: var(--secondary); text-decoration: none; background: transparent; line-height: 1;">✏️ Edit</a>
          <form style="margin: 0;" action="/wishlists/<?= (int)$l['id'] ?>/delete" method="post">
            <?= \OpenWishlist\Support\Csrf::field() ?>
            <input type="submit" value="🗑️ Delete" data-confirm="Delete this wishlist and all its wishes?" style="width: 100%; padding: 0.2rem 0.3rem; font-size: 0.7em; background-color: transparent; color: var(--secondary); border: none; cursor: pointer; line-height: 1;">
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
        of <?= $pagination['totalCount'] ?> wishlists
      </p>
    </nav>
  <?php endif; ?>
<?php endif; ?>
