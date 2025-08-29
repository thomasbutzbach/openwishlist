<div style="margin-bottom: 2rem;">
  <h1 style="margin: 0 0 0.5rem 0; color: #333;"><?= htmlspecialchars($wl['title']) ?></h1>
  <?php if (!empty($wl['description'])): ?>
    <p style="margin: 0 0 1rem 0; color: #6c757d; font-size: 1.1em;"><?= htmlspecialchars($wl['description']) ?></p>
  <?php endif; ?>
  <div style="padding: 0.75rem 1rem; background: #e7f3ff; border-left: 4px solid #007bff; border-radius: 4px;">
    <strong>🌐 Public Wishlist</strong> - Anyone with this link can view this wishlist
  </div>
</div>

<?php if (empty($wishes)): ?>
  <div style="text-align: center; padding: 3rem; color: #6c757d; border: 2px dashed #dee2e6; border-radius: 8px;">
    <h3 style="margin: 0 0 1rem 0;">No wishes yet</h3>
    <p style="margin: 0;">This wishlist doesn't have any wishes yet.</p>
  </div>
<?php else: ?>
  <div style="display: grid; gap: 1rem;">
    <?php foreach ($wishes as $w): ?>
      <div style="border: 1px solid #ddd; border-radius: 8px; padding: 1rem; display: grid; grid-template-columns: auto 1fr; gap: 1rem; align-items: start; background: white;">
        
        <!-- Image -->
        <div style="width: 120px; height: 120px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 4px; overflow: hidden;">
          <?php if ($w['image_mode'] === 'none'): ?>
            <span style="color: #6c757d; font-size: 0.9em;">No Image</span>
          <?php elseif ($w['image_mode'] === 'link' && !empty($w['image_url'])): ?>
            <img src="<?= htmlspecialchars($w['image_url']) ?>" 
                 alt="<?= htmlspecialchars($w['title']) ?>" 
                 style="max-width: 100%; max-height: 100%; object-fit: contain;">
          <?php elseif ($w['image_mode'] === 'local' && $w['image_status'] === 'ok' && !empty($w['image_path'])): ?>
            <img src="/<?= htmlspecialchars($w['image_path']) ?>" 
                 alt="<?= htmlspecialchars($w['title']) ?>" 
                 style="max-width: 100%; max-height: 100%; object-fit: contain;">
          <?php elseif ($w['image_mode'] === 'local'): ?>
            <span style="color: #ffc107; font-size: 0.9em;">Image Loading...</span>
          <?php endif; ?>
        </div>

        <!-- Content -->
        <div>
          <h3 style="margin: 0 0 0.5rem 0; font-size: 1.3em;">
            <?php if (!empty($w['url'])): ?>
              <a href="<?= htmlspecialchars($w['url']) ?>" target="_blank" 
                 style="text-decoration: none; color: #007bff;">
                <?= htmlspecialchars($w['title']) ?>
                <span style="font-size: 0.8em; color: #6c757d;">↗</span>
              </a>
            <?php else: ?>
              <?= htmlspecialchars($w['title']) ?>
            <?php endif; ?>
          </h3>
          
          <?php if (!empty($w['notes'])): ?>
            <p style="margin: 0 0 1rem 0; color: #6c757d; line-height: 1.5;"><?= nl2br(htmlspecialchars($w['notes'])) ?></p>
          <?php endif; ?>
          
          <div style="display: flex; gap: 1.5rem; font-size: 0.9em;">
            <?php if (isset($w['price_cents'])): ?>
              <span style="padding: 0.25rem 0.75rem; background: #28a745; color: white; border-radius: 20px; font-weight: 500;">
                €<?= number_format(((int)$w['price_cents'])/100, 2, ',', '.') ?>
              </span>
            <?php endif; ?>
            <?php if ($w['priority'] && $w['priority'] <= 2): ?>
              <span style="padding: 0.25rem 0.75rem; background: #dc3545; color: white; border-radius: 20px; font-weight: 500;">
                ⭐ High Priority
              </span>
            <?php elseif ($w['priority'] && $w['priority'] <= 3): ?>
              <span style="padding: 0.25rem 0.75rem; background: #ffc107; color: #212529; border-radius: 20px; font-weight: 500;">
                Medium Priority
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
