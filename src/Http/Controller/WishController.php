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

        $imageMode = $_POST['image_mode'] ?? 'link';
        $imageUrl = trim($_POST['image_url'] ?? '');

        if ($title === '' || mb_strlen($title) > 190) {
            Session::flash('error', 'Title is required and must be ≤ 190 characters.');
            Router::redirect('/wishlists/'.$wl['id'].'/wishes/new');
        }
        if (!in_array($imageMode, ['link','local'], true)) {
            Session::flash('error', 'Invalid image mode.');
            Router::redirect('/wishlists/'.$wl['id'].'/wishes/new');
        }
        if ($imageUrl === '' || !preg_match('#^https?://#i', $imageUrl)) {
            Session::flash('error', 'Please provide a valid image URL (http/https).');
            Router::redirect('/wishlists/'.$wl['id'].'/wishes/new');
        }
        if ($priority !== null && ($priority < 1 || $priority > 5)) {
            Session::flash('error', 'Priority must be between 1 and 5.');
            Router::redirect('/wishlists/'.$wl['id'].'/wishes/new');
        }

        $imageStatus = $imageMode === 'local' ? 'pending' : 'ok';

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
            'iu' => $imageUrl,
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
        if (!in_array($imageMode, ['link','local'], true)) {
            Session::flash('error', 'Invalid image mode.');
            Router::redirect('/wishes/'.$wish['id'].'/edit');
        }
        if ($imageUrl === '' || !preg_match('#^https?://#i', $imageUrl)) {
            Session::flash('error', 'Please provide a valid image URL (http/https).');
            Router::redirect('/wishes/'.$wish['id'].'/edit');
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
}
