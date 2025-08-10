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
  <table style="width:100%; border-collapse:collapse">
    <thead>
      <tr>
        <th style="text-align:left; padding:.4rem; border-bottom:1px solid #ddd">Title</th>
        <th style="text-align:left; padding:.4rem; border-bottom:1px solid #ddd">Price</th>
        <th style="text-align:left; padding:.4rem; border-bottom:1px solid #ddd">Priority</th>
        <th style="text-align:left; padding:.4rem; border-bottom:1px solid #ddd">Image</th>
        <th style="text-align:left; padding:.4rem; border-bottom:1px solid #ddd">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($wishes as $w): ?>
        <tr>
          <td style="padding:.4rem">
            <?php if (!empty($w['url'])): ?>
              <a href="<?= htmlspecialchars($w['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($w['title']) ?></a>
            <?php else: ?>
              <?= htmlspecialchars($w['title']) ?>
            <?php endif; ?>
          </td>
          <td style="padding:.4rem">
            <?php
              echo isset($w['price_cents'])
                   ? '€ ' . number_format(((int)$w['price_cents'])/100, 2, ',', '.')
                   : '—';
            ?>
          </td>
          <td style="padding:.4rem"><?= $w['priority'] ?? '—' ?></td>
          <td style="padding:.4rem">
            <?php if ($w['image_mode'] === 'link'): ?>
              Link
            <?php else: ?>
              Local (<?= htmlspecialchars($w['image_status']) ?>)
            <?php endif; ?>
          </td>
          <td style="padding:.4rem">
            <a href="/wishes/<?= (int)$w['id'] ?>/edit">Edit</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
