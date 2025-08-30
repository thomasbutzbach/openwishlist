<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($wl['title']) ?> - Wishlist PDF Export</title>
  <style>
    @media print {
      body { margin: 0; }
      .no-print { display: none; }
      
      /* Force borders and backgrounds to print */
      * {
        -webkit-print-color-adjust: exact !important;
        color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
      
      .wish-item {
        border: 1px solid #333 !important;
        box-shadow: none !important;
        background: white !important;
      }
      
      .wish-header {
        background: #f5f5f5 !important;
        border-bottom: 1px solid #333 !important;
      }
      
      .wishlist-header {
        background: #f8f8f8 !important;
        border: 1px solid #ddd !important;
      }
      
      .notes {
        background: #f8f8f8 !important;
        border: 1px solid #ddd !important;
      }
    }
    
    body {
      font-family: system-ui, -apple-system, sans-serif;
      line-height: 1.4;
      color: #333;
      max-width: 100%;
      margin: 0;
      padding: 10px;
      font-size: 12px;
    }
    
    h1 {
      color: #2c3e50;
      border-bottom: 1px solid #3498db;
      padding-bottom: 5px;
      font-size: 18px;
      margin: 0 0 15px 0;
    }
    
    .wishlist-header {
      background: #f8f9fa;
      padding: 8px;
      border-radius: 4px;
      margin-bottom: 15px;
      font-size: 11px;
    }
    
    .wishlist-header p {
      margin: 3px 0;
    }
    
    .wish-item {
      border: 1px solid #ddd;
      border-radius: 4px;
      margin-bottom: 8px;
      overflow: hidden;
      break-inside: avoid;
    }
    
    .wish-header {
      background: #f1f3f4;
      padding: 6px 8px;
      border-bottom: 1px solid #ddd;
    }
    
    .wish-title {
      font-size: 13px;
      font-weight: bold;
      margin: 0;
      color: #2c3e50;
    }
    
    .wish-url {
      color: #3498db;
      font-size: 10px;
      margin-top: 2px;
      word-break: break-all;
    }
    
    .wish-content {
      padding: 6px 8px;
    }
    
    .wish-details {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 6px;
    }
    
    .detail-item {
      font-size: 10px;
      flex: 0 0 auto;
    }
    
    .detail-label {
      font-weight: bold;
      color: #555;
      display: inline;
    }
    
    .priority-high { color: #e74c3c; }
    .priority-medium { color: #f39c12; }
    .priority-low { color: #27ae60; }
    
    .notes {
      background: #f8f9fa;
      padding: 6px;
      border-radius: 2px;
      font-style: italic;
      margin-top: 6px;
      font-size: 10px;
    }
    
    .export-info {
      margin-top: 15px;
      padding-top: 8px;
      border-top: 1px solid #ddd;
      font-size: 9px;
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
      padding: 8px 15px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
      z-index: 1000;
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
                <span class="detail-label">Price:</span> €<?= number_format($wish['price_cents'] / 100, 2, ',', '.') ?>
              </div>
            <?php endif; ?>
            
            <?php if ($wish['priority']): ?>
              <div class="detail-item">
                <span class="detail-label">Priority:</span> 
                <span class="<?= $wish['priority'] <= 2 ? 'priority-high' : ($wish['priority'] <= 3 ? 'priority-medium' : 'priority-low') ?>">
                  <?= (int)$wish['priority'] ?>/5<?php if ($wish['priority'] <= 2): ?>⭐<?php endif; ?>
                </span>
              </div>
            <?php endif; ?>
            
            <?php if ($wish['image_mode'] !== 'none'): ?>
              <div class="detail-item">
                <span class="detail-label">Image:</span> <?= htmlspecialchars($wish['image_mode']) ?>
              </div>
            <?php endif; ?>
            
            <div class="detail-item">
              <span class="detail-label">Added:</span> <?= date('M j, Y', strtotime($wish['created_at'])) ?>
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