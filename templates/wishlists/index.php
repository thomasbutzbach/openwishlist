<hgroup>
  <h1>My Wishlists</h1>
  <p>Manage your personal wishlists</p>
</hgroup>

<form method="GET" style="margin-bottom: 1rem;">
  <div class="grid">
    <input type="search" name="search" placeholder="Search wishlists..." value="<?= htmlspecialchars($search ?? '') ?>">
    <button type="submit">Search</button>
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
    <article>
      <header>
        <h3><a href="/wishlists/<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['title']) ?></a></h3>
        <?php if (!empty($l['description'])): ?>
          <p><?= htmlspecialchars($l['description']) ?></p>
        <?php endif; ?>
      </header>
      
      <p>
        <mark><?= $l['is_public'] ? '🌐 Public' : '🔒 Private' ?></mark>
        <?php if (!empty($l['is_public']) && !empty($l['share_slug'])): ?>
          <br><small>Share: <a href="/s/<?= htmlspecialchars($l['share_slug']) ?>">/s/<?= htmlspecialchars($l['share_slug']) ?></a></small>
        <?php endif; ?>
      </p>
      
      <footer>
        <a href="/wishlists/<?= (int)$l['id'] ?>" role="button">Open</a>
        <a href="/wishlists/<?= (int)$l['id'] ?>/edit" role="button" class="secondary">Edit</a>
        <form style="display: inline-block;" action="/wishlists/<?= (int)$l['id'] ?>/delete" method="post" onsubmit="return confirm('Delete this wishlist and all its wishes?');">
          <?= \OpenWishlist\Support\Csrf::field() ?>
          <input type="submit" value="Delete" class="outline">
        </form>
      </footer>
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
