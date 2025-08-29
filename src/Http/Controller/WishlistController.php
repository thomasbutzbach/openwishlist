<?php
declare(strict_types=1);

namespace OpenWishlist\Http\Controller;

use PDO;
use OpenWishlist\Http\Router;
use OpenWishlist\Support\Csrf;
use OpenWishlist\Support\Session;
use OpenWishlist\Support\View;
use OpenWishlist\Support\Str;

final class WishlistController
{
    public function __construct(private PDO $pdo, private array $config) {}

    private function requireAuth(): int
    {
        $uid = Session::userId();
        if (!$uid) Router::redirect('/login');
        return $uid;
    }

    public function index(): void
    {
        $uid = $this->requireAuth();

        $stmt = $this->pdo->prepare('SELECT id,title,is_public,share_slug,created_at FROM wishlists WHERE user_id=:u ORDER BY created_at DESC');
        $stmt->execute(['u' => $uid]);
        $lists = $stmt->fetchAll();

        View::render('wishlists/index', [
            'title' => 'My wishlists',
            'lists' => $lists,
            'baseUrl' => rtrim($this->config['app']['base_url'] ?? '', '/'),
        ]);
    }

    public function show(array $params): void
    {
        $uid = $this->requireAuth();
        $wl = $this->getOwned((int)$params['id'], $uid);
        if (!$wl) { Router::status(404); echo 'Not found'; return; }

        $stmt = $this->pdo->prepare('SELECT * FROM wishes WHERE wishlist_id=:wl ORDER BY COALESCE(priority, 999) ASC, created_at DESC');
        $stmt->execute(['wl' => $wl['id']]);
        $wishes = $stmt->fetchAll();

        View::render('wishlists/show', [
            'title' => $wl['title'],
            'wl' => $wl,
            'wishes' => $wishes,
        ]);
    }

    public function createForm(): void
    {
        $this->requireAuth();
        View::render('wishlists/create', ['title' => 'Create wishlist']);
    }

    public function create(): void
    {
        $uid = $this->requireAuth();
        Csrf::assert();

        $title = trim($_POST['title'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $isPublic = isset($_POST['is_public']) ? 1 : 0;

        if ($title === '' || mb_strlen($title) > 190) {
            Session::flash('error', 'Title is required and must be ≤ 190 characters.');
            Router::redirect('/wishlists/create');
        }

        $slug = null;
        if ($isPublic) {
            $slug = $this->uniqueSlug();
        }

        $stmt = $this->pdo->prepare('INSERT INTO wishlists (user_id,title,description,is_public,share_slug) VALUES (:u,:t,:d,:p,:s)');
        $stmt->execute([
            'u' => $uid, 't' => $title, 'd' => $desc !== '' ? $desc : null,
            'p' => $isPublic, 's' => $slug
        ]);

        Session::flash('success', 'Wishlist created.');
        Router::redirect('/wishlists');
    }

    public function editForm(array $params): void
    {
        $uid = $this->requireAuth();
        $wl = $this->getOwned((int)$params['id'], $uid);
        if (!$wl) { Router::status(404); echo 'Not found'; return; }

        View::render('wishlists/edit', ['title' => 'Edit wishlist', 'wl' => $wl, 'baseUrl' => rtrim($this->config['app']['base_url'] ?? '', '/')]);
    }

    public function update(array $params): void
    {
        $uid = $this->requireAuth();
        Csrf::assert();
        $wl = $this->getOwned((int)$params['id'], $uid);
        if (!$wl) { Router::status(404); echo 'Not found'; return; }

        $title = trim($_POST['title'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $isPublic = isset($_POST['is_public']) ? 1 : 0;

        if ($title === '' || mb_strlen($title) > 190) {
            Session::flash('error', 'Title is required and must be ≤ 190 characters.');
            Router::redirect('/wishlists/'.$wl['id'].'/edit');
        }

        $shareSlug = $wl['share_slug'];
        if ($isPublic && !$shareSlug) {
            $shareSlug = $this->uniqueSlug();
        }
        if (!$isPublic) {
            $shareSlug = null; // remove slug when making private
        }

        $stmt = $this->pdo->prepare('UPDATE wishlists SET title=:t, description=:d, is_public=:p, share_slug=:s WHERE id=:id AND user_id=:u');
        $stmt->execute([
            't' => $title,
            'd' => $desc !== '' ? $desc : null,
            'p' => $isPublic,
            's' => $shareSlug,
            'id'=> $wl['id'],
            'u' => $uid,
        ]);

        Session::flash('success', 'Wishlist updated.');
        Router::redirect('/wishlists/'.$wl['id'].'/edit');
    }

    public function delete(array $params): void
    {
        $uid = $this->requireAuth();
        Csrf::assert();
        $wl = $this->getOwned((int)$params['id'], $uid);
        if (!$wl) { Router::status(404); echo 'Not found'; return; }

        $del = $this->pdo->prepare('DELETE FROM wishlists WHERE id=:id AND user_id=:u');
        $del->execute(['id' => $wl['id'], 'u' => $uid]);

        Session::flash('success', 'Wishlist deleted.');
        Router::redirect('/wishlists');
    }

    public function togglePublic(array $params): void
    {
        $uid = $this->requireAuth();
        Csrf::assert();
        $wl = $this->getOwned((int)$params['id'], $uid);
        if (!$wl) { Router::status(404); echo 'Not found'; return; }

        $toPublic = !$wl['is_public'];
        $slug = $wl['share_slug'];

        if ($toPublic && !$slug) {
            $slug = $this->uniqueSlug();
        }
        if (!$toPublic) {
            $slug = null;
        }

        $upd = $this->pdo->prepare('UPDATE wishlists SET is_public=:p, share_slug=:s WHERE id=:id AND user_id=:u');
        $upd->execute([
            'p' => $toPublic ? 1 : 0,
            's' => $slug,
            'id'=> $wl['id'],
            'u' => $uid,
        ]);

        Session::flash('success', $toPublic ? 'Wishlist is now public.' : 'Wishlist is now private.');
        Router::redirect('/wishlists/'.$wl['id'].'/edit');
    }

    public function publicBySlug(array $params): void
    {
        $slug = $params['slug'] ?? '';
        $stmt = $this->pdo->prepare('SELECT id,user_id,title,description,is_public,share_slug,created_at FROM wishlists WHERE share_slug=:s AND is_public=1');
        $stmt->execute(['s' => $slug]);
        $wl = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wl) { Router::status(404); echo 'Not found'; return; }

        // Load wishes for this public wishlist
        $stmt = $this->pdo->prepare('SELECT * FROM wishes WHERE wishlist_id=:wid ORDER BY priority ASC, id ASC');
        $stmt->execute(['wid' => $wl['id']]);
        $wishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        View::render('public_list', [
            'title' => $wl['title'],
            'wl' => $wl,
            'wishes' => $wishes,
        ]);
    }

    private function getOwned(int $id, int $uid): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM wishlists WHERE id=:id AND user_id=:u');
        $stmt->execute(['id'=>$id,'u'=>$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function uniqueSlug(): string
    {
        // Try a few times to avoid rare collisions
        for ($i = 0; $i < 5; $i++) {
            $slug = Str::randomSlug(10);
            $q = $this->pdo->prepare('SELECT 1 FROM wishlists WHERE share_slug=:s');
            $q->execute(['s' => $slug]);
            if (!$q->fetchColumn()) return $slug;
        }
        // Worst case, make it longer
        do {
            $slug = Str::randomSlug(14);
            $q = $this->pdo->prepare('SELECT 1 FROM wishlists WHERE share_slug=:s');
            $q->execute(['s' => $slug]);
        } while ($q->fetchColumn());
        return $slug;
    }
}
