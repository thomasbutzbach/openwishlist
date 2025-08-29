<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($wl['title']) ?> - Wishlist PDF Export</title>
  <style>
    @media print {
      body { margin: 0; }
      .no-print { display: none; }
    }
    
    body {
      font-family: system-ui, -apple-system, sans-serif;
      line-height: 1.6;
      color: #333;
      max-width: 800px;
      margin: 0 auto;
      padding: 20px;
    }
    
    h1 {
      color: #2c3e50;
      border-bottom: 2px solid #3498db;
      padding-bottom: 10px;
    }
    
    .wishlist-header {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 30px;
    }
    
    .wish-item {
      border: 1px solid #ddd;
      border-radius: 8px;
      margin-bottom: 20px;
      overflow: hidden;
      break-inside: avoid;
    }
    
    .wish-header {
      background: #f1f3f4;
      padding: 15px;
      border-bottom: 1px solid #ddd;
    }
    
    .wish-title {
      font-size: 18px;
      font-weight: bold;
      margin: 0;
      color: #2c3e50;
    }
    
    .wish-url {
      color: #3498db;
      font-size: 14px;
      margin-top: 5px;
    }
    
    .wish-content {
      padding: 15px;
    }
    
    .wish-details {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 15px;
      margin-bottom: 15px;
    }
    
    .detail-item {
      font-size: 14px;
    }
    
    .detail-label {
      font-weight: bold;
      color: #555;
    }
    
    .priority-high { color: #e74c3c; }
    .priority-medium { color: #f39c12; }
    .priority-low { color: #27ae60; }
    
    .notes {
      background: #f8f9fa;
      padding: 10px;
      border-radius: 4px;
      font-style: italic;
      margin-top: 10px;
    }
    
    .export-info {
      margin-top: 40px;
      padding-top: 20px;
      border-top: 1px solid #ddd;
      font-size: 12px;
      color: #666;
      text-align: center;
    }
    
    .print-button {
      position: fixed;
      top: 20px;
      right: 20px;
      background: #3498db;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
    }
    
    .print-button:hover {
      background: #2980b9;
    }
  </style>
</head>
<body>
  <button class="print-button no-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
  
  <h1><?= htmlspecialchars($wl['title']) ?></h1>
  
  <div class="wishlist-header">
    <?php if (!empty($wl['description'])): ?>
      <p><strong>Description:</strong> <?= htmlspecialchars($wl['description']) ?></p>
    <?php endif; ?>
    <p><strong>Visibility:</strong> <?= $wl['is_public'] ? 'Public' : 'Private' ?></p>
    <?php if ($wl['is_public'] && !empty($wl['share_slug'])): ?>
      <p><strong>Share Link:</strong> <?= htmlspecialchars($baseUrl ?? '') ?>/s/<?= htmlspecialchars($wl['share_slug']) ?></p>
    <?php endif; ?>
    <p><strong>Total Wishes:</strong> <?= count($wishes) ?></p>
  </div>

  <?php if (empty($wishes)): ?>
    <div class="wish-item">
      <div class="wish-content">
        <p><em>No wishes in this list yet.</em></p>
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($wishes as $i => $wish): ?>
      <div class="wish-item">
        <div class="wish-header">
          <h3 class="wish-title"><?= htmlspecialchars($wish['title']) ?></h3>
          <?php if (!empty($wish['url'])): ?>
            <div class="wish-url"><?= htmlspecialchars($wish['url']) ?></div>
          <?php endif; ?>
        </div>
        
        <div class="wish-content">
          <div class="wish-details">
            <?php if (isset($wish['price_cents'])): ?>
              <div class="detail-item">
                <div class="detail-label">Price:</div>
                <div>€<?= number_format($wish['price_cents'] / 100, 2, ',', '.') ?></div>
              </div>
            <?php endif; ?>
            
            <?php if ($wish['priority']): ?>
              <div class="detail-item">
                <div class="detail-label">Priority:</div>
                <div class="<?= $wish['priority'] <= 2 ? 'priority-high' : ($wish['priority'] <= 3 ? 'priority-medium' : 'priority-low') ?>">
                  <?= (int)$wish['priority'] ?>/5
                  <?php if ($wish['priority'] <= 2): ?>⭐<?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
            
            <div class="detail-item">
              <div class="detail-label">Image:</div>
              <div><?= htmlspecialchars($wish['image_mode']) ?></div>
            </div>
            
            <div class="detail-item">
              <div class="detail-label">Added:</div>
              <div><?= date('M j, Y', strtotime($wish['created_at'])) ?></div>
            </div>
          </div>
          
          <?php if (!empty($wish['notes'])): ?>
            <div class="notes">
              <strong>Notes:</strong><br>
              <?= nl2br(htmlspecialchars($wish['notes'])) ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
  
  <div class="export-info">
    <p>Exported from OpenWishlist on <?= date('F j, Y \a\t g:i A') ?></p>
    <p>Generated by <a href="https://github.com/thomasbutzbach/openwishlist">OpenWishlist</a></p>
  </div>
</body>
</html>