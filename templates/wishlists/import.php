<h1>CSV Import for <?= htmlspecialchars($wl['title']) ?></h1>

<p><a href="/wishlists/<?= (int)$wl['id'] ?>">← Back to wishlist</a></p>

<h2>Upload CSV file</h2>

<p>Upload a CSV file with exactly 4 columns:</p>
<ol>
    <li><strong>Product Name</strong> - The wish title (required)</li>
    <li><strong>Price</strong> - Price in EUR (optional, comma or dot notation)</li>
    <li><strong>Image URL</strong> - URL to a product image (optional)</li>
    <li><strong>Product URL</strong> - Link to the product (optional)</li>
</ol>

<p><strong>Example:</strong></p>
<pre>Product Name,Price,Image URL,Product URL
iPhone 15,899.99,https://example.com/iphone.jpg,https://apple.com/iphone
PHP Book,29.50,,https://bookstore.com/php-book
Coffee Mug,15.99,https://example.com/mug.jpg,</pre>

<form method="POST" enctype="multipart/form-data">
    <?= \OpenWishlist\Support\Csrf::field() ?>
    
    <div class="grid">
        <label>
            Select CSV file
            <input type="file" name="csv_file" accept=".csv,text/csv" required>
            <small>Maximum file size: 1 MB</small>
        </label>
    </div>
    
    <button type="submit">Import CSV</button>
</form>

<hr>

<h3>Import Notes</h3>
<ul>
    <li>The first row is automatically detected as a header and skipped if it contains terms like "Product", "Title", or "Name"</li>
    <li>Rows with empty product names are skipped</li>
    <li>Both comma (15,99) and dot (15.99) can be used as decimal separators for prices</li>
    <li>Currency symbols (€, $, £, ¥) are automatically removed</li>
    <li>URLs are validated for correctness</li>
    <li>If an image URL is provided, the image is saved as "link" mode</li>
    <li>Incomplete rows (less than 4 columns) are skipped</li>
</ul>