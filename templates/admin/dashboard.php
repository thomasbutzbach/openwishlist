<?php
use OpenWishlist\Support\Version;
?>

<nav style="margin-bottom: 1rem;">
    <ul>
        <li><strong><a href="/admin" style="text-decoration: none;">Dashboard</a></strong></li>
        <li><a href="/admin/jobs">Jobs</a></li>
        <li><a href="/admin/settings">Settings</a></li>
    </ul>
</nav>


<?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>
    <article>
        <header>
            <strong>✅ Update Completed</strong>
        </header>
        <p>
            Your OpenWishlist installation has been successfully updated!
        </p>
    </article>
<?php endif; ?>

<hgroup>
    <h1>Admin Dashboard <small style="color: #666; font-weight: normal;"><?= Version::formatDisplay() ?></small></h1>
    <p>System overview and statistics</p>
</hgroup>

<div class="grid">
    <article>
        <header><strong>Users</strong></header>
        <p>
            <strong><?= number_format($userStats['total_users'] ?? 0) ?></strong> total users<br>
            <?= number_format($userStats['admin_users'] ?? 0) ?> admins, <?= number_format($userStats['regular_users'] ?? 0) ?> regular users
        </p>
    </article>
    
    <article>
        <header><strong>Wishlists</strong></header>
        <p>
            <strong><?= number_format($wishlistStats['total_wishlists'] ?? 0) ?></strong> total wishlists<br>
            <?= number_format($wishlistStats['public_wishlists'] ?? 0) ?> public, <?= number_format($wishlistStats['private_wishlists'] ?? 0) ?> private
        </p>
    </article>
    
    <article>
        <header><strong>Wishes</strong></header>
        <p>
            <strong><?= number_format($wishStats['total_wishes'] ?? 0) ?></strong> total wishes<br>
            <?= number_format($wishStats['wishes_with_images'] ?? 0) ?> with images, <?= number_format($wishStats['wishes_with_price'] ?? 0) ?> with price
        </p>
    </article>
    
    <article>
        <header><strong>Image Modes</strong></header>
        <?php if (empty($imageStats)): ?>
            <p><em>No wishes with images yet</em></p>
        <?php else: ?>
            <?php foreach ($imageStats as $stat): ?>
                <p style="margin: 0.25rem 0;">
                    <strong><?= ucfirst($stat['image_mode']) ?>:</strong> <?= number_format($stat['count']) ?><br>
                    <small style="color: #666;">
                        <?= $stat['successful'] ?> successful, 
                        <?= $stat['failed'] ?> failed, 
                        <?= $stat['pending'] ?> pending
                    </small>
                </p>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php
        $linkCount = 0;
        foreach ($imageStats as $stat) {
            if ($stat['image_mode'] === 'link') {
                $linkCount = (int)$stat['count'];
                break;
            }
        }
        ?>
        
        <?php if ($linkCount > 0): ?>
            <footer>
                <form method="POST" action="/admin/convert-links-to-local" style="display: inline;">
                    <?= \OpenWishlist\Support\Csrf::field() ?>
                    <button type="submit" class="secondary" data-confirm="Convert all <?= $linkCount ?> linked images to local storage? This will queue background jobs to download the images.">Convert <?= $linkCount ?> Links to Local</button>
                </form>
            </footer>
        <?php endif; ?>
    </article>
</div>

<div class="grid">
    <article>
        <header><strong>Background Jobs</strong></header>
        <p>
            <strong><?= number_format($jobStats['queued'] ?? 0) ?></strong> queued<br>
            <strong><?= number_format($jobStats['processing'] ?? 0) ?></strong> processing<br>
            <strong><?= number_format($jobStats['completed'] ?? 0) ?></strong> completed<br>
            <strong><?= number_format($jobStats['failed'] ?? 0) ?></strong> failed
        </p>
        <footer>
            <a href="/admin/jobs" role="button">Manage Jobs</a>
        </footer>
    </article>
    
    <article>
        <header><strong>Recent Wishlists</strong></header>
        <?php if (empty($recentWishlists)): ?>
            <p><em>No wishlists yet</em></p>
        <?php else: ?>
            <div style="max-height: 200px; overflow-y: auto;">
                <?php foreach ($recentWishlists as $wishlist): ?>
                    <p style="margin: 0.25rem 0; font-size: 0.9em;">
                        <strong><?= htmlspecialchars($wishlist['title']) ?></strong><br>
                        <small>by <?= htmlspecialchars($wishlist['email']) ?> on <?= date('M j, Y', strtotime($wishlist['created_at'])) ?></small>
                    </p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</div>

