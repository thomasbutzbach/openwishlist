<?php
declare(strict_types=1);

namespace OpenWishlist\Http\Controller;

use PDO;
use OpenWishlist\Http\Router;
use OpenWishlist\Support\Csrf;
use OpenWishlist\Support\Session;
use OpenWishlist\Support\View;

final class WishController
{
    public function __construct(private PDO $pdo, private array $config) {}

    private function requireAuth(): int
    {
        $uid = Session::userId();
        if (!$uid) Router::redirect('/login');
        return $uid;
    }

    private function requireApiAuth(): int
    {
        $uid = Session::userId();
        if (!$uid) {
            Router::json(['type' => 'about:blank', 'title' => 'Unauthorized', 'status' => 401, 'detail' => 'Authentication required.'], 401);
            exit;
        }
        return $uid;
    }

    private function getOwnedWishlist(int $wishlistId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, user_id, title, description, is_public, share_slug FROM wishlists WHERE id=:id AND user_id=:u');
        $stmt->execute(['id' => $wishlistId, 'u' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getOwnedWish(int $wishId, int $userId): ?array
    {
        $sql = 'SELECT w.* FROM wishes w
                JOIN wishlists wl ON wl.id = w.wishlist_id
                WHERE w.id = :id AND wl.user_id = :u';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $wishId, 'u' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createForm(array $params): void
    {
        $uid = $this->requireAuth();
        $wl = $this->getOwnedWishlist((int)$params['id'], $uid);
        if (!$wl) { Router::status(404); echo 'Not found'; return; }

        View::render('wishes/create', ['title' => 'Add wish', 'wl' => $wl]);
    }

    public function create(array $params): void
    {
        $uid = $this->requireAuth();
        Csrf::assert();
        $wl = $this->getOwnedWishlist((int)$params['id'], $uid);
        if (!$wl) { Router::status(404); echo 'Not found'; return; }

        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $priceCents = $this->parsePriceCents($_POST['price'] ?? '');
        $priority = isset($_POST['priority']) && $_POST['priority'] !== '' ? (int)$_POST['priority'] : null;
        $notes = trim($_POST['notes'] ?? '');

        $imageMode = $_POST['image_mode'] ?? 'none';
        $imageUrl = trim($_POST['image_url'] ?? '');

        if (!in_array($imageMode, ['none','link','local'], true)) {
            Session::flash('error', 'Invalid image mode.');
            Router::redirect('/wishlists/'.$wl['id'].'/wishes/new');
        }

        if ($imageMode !== 'none') {
            if ($imageUrl === '' || !preg_match('#^https?://#i', $imageUrl)) {
                Session::flash('error', 'Please provide a valid image URL (http/https).');
                Router::redirect('/wishlists/'.$wl['id'].'/wishes/new');
            }
        }

        if ($priority !== null && ($priority < 1 || $priority > 5)) {
            Session::flash('error', 'Priority must be between 1 and 5.');
            Router::redirect('/wishlists/'.$wl['id'].'/wishes/new');
        }

        $imageStatus = match ($imageMode) {
            'local' => 'pending',
            'link'  => 'ok',
            'none'  => null,
        };

        $stmt = $this->pdo->prepare(
            'INSERT INTO wishes (wishlist_id,title,url,price_cents,priority,notes,image_mode,image_url,image_status)
             VALUES (:wl,:t,:u,:p,:pr,:n,:im,:iu,:is)'
        );
        $stmt->execute([
            'wl' => $wl['id'],
            't'  => $title,
            'u'  => $url !== '' ? $url : null,
            'p'  => $priceCents,
            'pr' => $priority,
            'n'  => $notes !== '' ? $notes : null,
            'im' => $imageMode,
            'iu' => $imageMode === 'none' ? null : $imageUrl,
            'is' => $imageStatus,
        ]);

        Session::flash('success', 'Wish added.');
        Router::redirect('/wishlists/'.$wl['id']);
    }

    public function editForm(array $params): void
    {
        $uid = $this->requireAuth();
        $wish = $this->getOwnedWish((int)$params['id'], $uid);
        if (!$wish) { Router::status(404); echo 'Not found'; return; }

        View::render('wishes/edit', ['title' => 'Edit wish', 'wish' => $wish]);
    }

    public function update(array $params): void
    {
        $uid = $this->requireAuth();
        Csrf::assert();
        $wish = $this->getOwnedWish((int)$params['id'], $uid);
        if (!$wish) { Router::status(404); echo 'Not found'; return; }

        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $priceCents = $this->parsePriceCents($_POST['price'] ?? '');
        $priority = isset($_POST['priority']) && $_POST['priority'] !== '' ? (int)$_POST['priority'] : null;
        $notes = trim($_POST['notes'] ?? '');

        $imageMode = $_POST['image_mode'] ?? $wish['image_mode'];
        $imageUrl = trim($_POST['image_url'] ?? ($wish['image_url'] ?? ''));

        if ($title === '' || mb_strlen($title) > 190) {
            Session::flash('error', 'Title is required and must be ≤ 190 characters.');
            Router::redirect('/wishes/'.$wish['id'].'/edit');
        }
        if (!in_array($imageMode, ['none','link','local'], true)) {
            Session::flash('error', 'Invalid image mode.');
            Router::redirect('/wishes/'.$wish['id'].'/edit');
        }
        if ($imageMode !== 'none') {
            if ($imageUrl === '' || !preg_match('#^https?://#i', $imageUrl)) {
                Session::flash('error', 'Please provide a valid image URL (http/https).');
                Router::redirect('/wishes/'.$wish['id'].'/edit');
            }
        }
        if ($priority !== null && ($priority < 1 || $priority > 5)) {
            Session::flash('error', 'Priority must be between 1 and 5.');
            Router::redirect('/wishes/'.$wish['id'].'/edit');
        }

        // Image status transitions
        $imageStatus = $wish['image_status'];
        $imagePath = $wish['image_path'];
        if ($imageMode === 'local' && $wish['image_mode'] !== 'local') {
            $imageStatus = 'pending'; // will be fetched by worker later
            $imagePath = null;
        }
        if ($imageMode === 'link') {
            // no local file
            $imagePath = null;
            if ($wish['image_mode'] !== 'link') {
                $imageStatus = 'ok';
            }
        }
        if ($imageMode === 'none') {
            $imagePath = null;
            $imageStatus = null;
            $imageUrl = null;
        }

        $sql = 'UPDATE wishes SET
                    title=:t, url=:u, price_cents=:p, priority=:pr, notes=:n,
                    image_mode=:im, image_url=:iu, image_status=:is, image_path=:ip
                WHERE id=:id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            't'  => $title,
            'u'  => $url !== '' ? $url : null,
            'p'  => $priceCents,
            'pr' => $priority,
            'n'  => $notes !== '' ? $notes : null,
            'im' => $imageMode,
            'iu' => $imageUrl,
            'is' => $imageStatus,
            'ip' => $imagePath,
            'id' => $wish['id'],
        ]);

        Session::flash('success', 'Wish updated.');
        Router::redirect('/wishes/'.$wish['id'].'/edit');
    }

    public function delete(array $params): void
    {
        $uid = $this->requireAuth();
        Csrf::assert();
        $wish = $this->getOwnedWish((int)$params['id'], $uid);
        if (!$wish) { Router::status(404); echo 'Not found'; return; }

        $del = $this->pdo->prepare('DELETE FROM wishes WHERE id=:id');
        $del->execute(['id' => $wish['id']]);

        Session::flash('success', 'Wish deleted.');
        Router::redirect('/wishlists/'.$wish['wishlist_id']);
    }

    private function parsePriceCents(string $input): ?int
    {
        $trim = trim($input);
        if ($trim === '') return null;
        // accept "12.34" or "12,34"
        $norm = str_replace(['.', ','], ['.', '.'], $trim);
        if (!is_numeric($norm)) return null;
        $float = (float)$norm;
        if ($float < 0 || $float > 1_000_000_000) return null;
        return (int) round($float * 100);
    }

    // === API Methods ===

    public function apiCreate(array $params): void
    {
        $uid = $this->requireApiAuth();
        $wl = $this->getOwnedWishlist((int)$params['id'], $uid);
        
        if (!$wl) {
            Router::json(['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404, 'detail' => 'Wishlist not found.'], 404);
            return;
        }
        
        try {
            $input = Router::inputJson();
            $title = trim($input['title'] ?? '');
            $url = trim($input['url'] ?? '');
            $notes = trim($input['notes'] ?? '');
            $priority = isset($input['priority']) ? (int)$input['priority'] : null;
            $imageMode = $input['image_mode'] ?? 'none';
            $imageUrl = trim($input['image_url'] ?? '');
            $priceCents = null;
            
            if (isset($input['price']) && $input['price'] !== '') {
                $priceCents = $this->parsePriceCents((string)$input['price']);
            }
            
            if ($title === '') {
                Router::json(['type' => 'about:blank', 'title' => 'Validation Error', 'status' => 400, 'detail' => 'Title is required.'], 400);
                return;
            }
            
            if (!in_array($imageMode, ['none', 'link', 'local'])) {
                $imageMode = 'none';
            }
            
            if ($priority !== null && ($priority < 1 || $priority > 5)) {
                $priority = null;
            }
            
            $stmt = $this->pdo->prepare('
                INSERT INTO wishes (wishlist_id, title, url, notes, priority, image_mode, image_url, price_cents)
                VALUES (:wl, :title, :url, :notes, :priority, :mode, :img_url, :price)
            ');
            $stmt->execute([
                'wl' => $wl['id'],
                'title' => $title,
                'url' => $url,
                'notes' => $notes,
                'priority' => $priority,
                'mode' => $imageMode,
                'img_url' => $imageUrl,
                'price' => $priceCents
            ]);
            
            $wishId = (int)$this->pdo->lastInsertId();
            
            Router::json([
                'id' => $wishId,
                'wishlist_id' => (int)$wl['id'],
                'title' => $title,
                'url' => $url,
                'notes' => $notes,
                'priority' => $priority,
                'image_mode' => $imageMode,
                'image_url' => $imageUrl,
                'price_cents' => $priceCents
            ], 201);
            
        } catch (\Throwable $e) {
            Router::json(['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500, 'detail' => 'Failed to create wish.'], 500);
        }
    }

    public function apiShow(array $params): void
    {
        $uid = $this->requireApiAuth();
        $wish = $this->getOwnedWish((int)$params['id'], $uid);
        
        if (!$wish) {
            Router::json(['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404, 'detail' => 'Wish not found.'], 404);
            return;
        }
        
        // Convert types
        $wish['id'] = (int)$wish['id'];
        $wish['wishlist_id'] = (int)$wish['wishlist_id'];
        $wish['priority'] = $wish['priority'] ? (int)$wish['priority'] : null;
        $wish['price_cents'] = $wish['price_cents'] ? (int)$wish['price_cents'] : null;
        
        Router::json($wish);
    }

    public function apiUpdate(array $params): void
    {
        $uid = $this->requireApiAuth();
        $wish = $this->getOwnedWish((int)$params['id'], $uid);
        
        if (!$wish) {
            Router::json(['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404, 'detail' => 'Wish not found.'], 404);
            return;
        }
        
        try {
            $input = Router::inputJson();
            $title = isset($input['title']) ? trim($input['title']) : $wish['title'];
            $url = isset($input['url']) ? trim($input['url']) : $wish['url'];
            $notes = isset($input['notes']) ? trim($input['notes']) : $wish['notes'];
            $priority = isset($input['priority']) ? (int)$input['priority'] : ($wish['priority'] ? (int)$wish['priority'] : null);
            $imageMode = $input['image_mode'] ?? $wish['image_mode'];
            $imageUrl = isset($input['image_url']) ? trim($input['image_url']) : $wish['image_url'];
            $priceCents = $wish['price_cents'] ? (int)$wish['price_cents'] : null;
            
            if (isset($input['price'])) {
                $priceCents = $input['price'] !== '' ? $this->parsePriceCents((string)$input['price']) : null;
            }
            
            if ($title === '') {
                Router::json(['type' => 'about:blank', 'title' => 'Validation Error', 'status' => 400, 'detail' => 'Title is required.'], 400);
                return;
            }
            
            if (!in_array($imageMode, ['none', 'link', 'local'])) {
                $imageMode = 'none';
            }
            
            if ($priority !== null && ($priority < 1 || $priority > 5)) {
                $priority = null;
            }
            
            $stmt = $this->pdo->prepare('
                UPDATE wishes 
                SET title = :title, url = :url, notes = :notes, priority = :priority, 
                    image_mode = :mode, image_url = :img_url, price_cents = :price
                WHERE id = :id
            ');
            $stmt->execute([
                'id' => $wish['id'],
                'title' => $title,
                'url' => $url,
                'notes' => $notes,
                'priority' => $priority,
                'mode' => $imageMode,
                'img_url' => $imageUrl,
                'price' => $priceCents
            ]);
            
            Router::json([
                'id' => (int)$wish['id'],
                'wishlist_id' => (int)$wish['wishlist_id'],
                'title' => $title,
                'url' => $url,
                'notes' => $notes,
                'priority' => $priority,
                'image_mode' => $imageMode,
                'image_url' => $imageUrl,
                'price_cents' => $priceCents
            ]);
            
        } catch (\Throwable $e) {
            Router::json(['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500, 'detail' => 'Failed to update wish.'], 500);
        }
    }

    public function apiDelete(array $params): void
    {
        $uid = $this->requireApiAuth();
        $wish = $this->getOwnedWish((int)$params['id'], $uid);
        
        if (!$wish) {
            Router::json(['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404, 'detail' => 'Wish not found.'], 404);
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare('DELETE FROM wishes WHERE id = :id');
            $stmt->execute(['id' => $wish['id']]);
            
            Router::status(204); // No Content
            
        } catch (\Throwable $e) {
            Router::json(['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500, 'detail' => 'Failed to delete wish.'], 500);
        }
    }

    public function apiRefetchImage(array $params): void
    {
        $uid = $this->requireApiAuth();
        $wish = $this->getOwnedWish((int)$params['id'], $uid);
        
        if (!$wish) {
            Router::json(['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404, 'detail' => 'Wish not found.'], 404);
            return;
        }
        
        if ($wish['image_mode'] !== 'local') {
            Router::json(['type' => 'about:blank', 'title' => 'Bad Request', 'status' => 400, 'detail' => 'Only local images can be refetched.'], 400);
            return;
        }
        
        try {
            // Reset image status to trigger refetch
            $stmt = $this->pdo->prepare('UPDATE wishes SET image_status = NULL WHERE id = :id');
            $stmt->execute(['id' => $wish['id']]);
            
            // Enqueue job for refetch
            $jobs = new \OpenWishlist\Support\Jobs($this->pdo);
            $jobs->enqueue('image.fetch', ['wishId' => (int)$wish['id']]);
            
            Router::json(['message' => 'Image refetch queued successfully.']);
            
        } catch (\Throwable $e) {
            Router::json(['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500, 'detail' => 'Failed to queue image refetch.'], 500);
        }
    }
}
