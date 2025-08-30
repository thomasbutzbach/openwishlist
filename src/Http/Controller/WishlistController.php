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

    private function requireApiAuth(): int
    {
        $uid = Session::userId();
        if (!$uid) {
            Router::json(['type' => 'about:blank', 'title' => 'Unauthorized', 'status' => 401, 'detail' => 'Authentication required.'], 401);
            exit;
        }
        return $uid;
    }

    public function index(): void
    {
        $uid = $this->requireAuth();

        // Search
        $search = trim($_GET['search'] ?? '');
        
        // Pagination
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;
        $offset = ($page - 1) * $perPage;

        // Build search conditions
        $whereConditions = ['user_id = :u'];
        $params = ['u' => $uid];
        
        if ($search !== '') {
            $whereConditions[] = '(title LIKE :search OR description LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        
        $whereClause = implode(' AND ', $whereConditions);

        // Get total count
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE $whereClause");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();

        // Get paginated results
        $stmt = $this->pdo->prepare("
            SELECT id,title,is_public,share_slug,created_at,description 
            FROM wishlists 
            WHERE $whereClause
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $lists = $stmt->fetchAll();

        // Calculate pagination info
        $totalPages = (int)ceil($totalCount / $perPage);
        $hasNextPage = $page < $totalPages;
        $hasPrevPage = $page > 1;

        View::render('wishlists/index', [
            'title' => 'My wishlists',
            'lists' => $lists,
            'baseUrl' => rtrim($this->config['app']['base_url'] ?? '', '/'),
            'search' => $search,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalCount' => $totalCount,
                'perPage' => $perPage,
                'hasNextPage' => $hasNextPage,
                'hasPrevPage' => $hasPrevPage,
            ]
        ]);
    }

    public function show(array $params): void
    {
        $uid = $this->requireAuth();
        $wl = $this->getOwned((int)$params['id'], $uid);
        if (!$wl) { Router::status(404); echo 'Not found'; return; }

        // Search wishes
        $search = trim($_GET['search'] ?? '');
        
        // Pagination for wishes
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        // Build search conditions for wishes
        $whereConditions = ['wishlist_id = :wl'];
        $params = ['wl' => $wl['id']];
        
        if ($search !== '') {
            $whereConditions[] = '(title LIKE :search OR notes LIKE :search OR url LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        
        $whereClause = implode(' AND ', $whereConditions);

        // Get total count of wishes
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM wishes WHERE $whereClause");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();

        // Get paginated wishes
        $stmt = $this->pdo->prepare("
            SELECT * FROM wishes 
            WHERE $whereClause
            ORDER BY COALESCE(priority, 999) ASC, created_at DESC 
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $wishes = $stmt->fetchAll();

        // Calculate pagination info
        $totalPages = (int)ceil($totalCount / $perPage);
        $hasNextPage = $page < $totalPages;
        $hasPrevPage = $page > 1;

        View::render('wishlists/show', [
            'title' => $wl['title'],
            'wl' => $wl,
            'wishes' => $wishes,
            'search' => $search,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalCount' => $totalCount,
                'perPage' => $perPage,
                'hasNextPage' => $hasNextPage,
                'hasPrevPage' => $hasPrevPage,
            ]
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
        if (!$wl) { 
            Router::status(404); 
            View::render('404', ['title' => 'Wishlist Not Found']);
            return; 
        }

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

    public function exportCsv(array $params): void
    {
        $uid = $this->requireAuth();
        $wl = $this->getOwned((int)$params['id'], $uid);
        if (!$wl) { Router::status(404); echo 'Not found'; return; }

        $stmt = $this->pdo->prepare('SELECT * FROM wishes WHERE wishlist_id=:wl ORDER BY COALESCE(priority, 999) ASC, created_at DESC');
        $stmt->execute(['wl' => $wl['id']]);
        $wishes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filename = 'wishlist-' . $wl['id'] . '-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        
        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM for Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");
        
        // CSV Header
        fputcsv($output, [
            'Title',
            'URL', 
            'Price (EUR)',
            'Priority',
            'Notes',
            'Image Mode',
            'Image Status',
            'Created At'
        ]);
        
        // CSV Data
        foreach ($wishes as $wish) {
            $price = isset($wish['price_cents']) ? number_format($wish['price_cents'] / 100, 2) : '';
            
            fputcsv($output, [
                $wish['title'],
                $wish['url'] ?? '',
                $price,
                $wish['priority'] ?? '',
                $wish['notes'] ?? '',
                $wish['image_mode'],
                $wish['image_status'] ?? '',
                $wish['created_at']
            ]);
        }
        
        fclose($output);
    }

    public function exportJson(array $params): void
    {
        $uid = $this->requireAuth();
        $wl = $this->getOwned((int)$params['id'], $uid);
        if (!$wl) { Router::status(404); echo 'Not found'; return; }

        $stmt = $this->pdo->prepare('SELECT * FROM wishes WHERE wishlist_id=:wl ORDER BY COALESCE(priority, 999) ASC, created_at DESC');
        $stmt->execute(['wl' => $wl['id']]);
        $wishes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert price_cents to euros for easier consumption
        foreach ($wishes as &$wish) {
            if (isset($wish['price_cents'])) {
                $wish['price_euros'] = $wish['price_cents'] / 100;
            }
        }
        unset($wish);

        $exportData = [
            'wishlist' => [
                'id' => $wl['id'],
                'title' => $wl['title'],
                'description' => $wl['description'],
                'is_public' => (bool)$wl['is_public'],
                'share_slug' => $wl['share_slug'],
                'created_at' => $wl['created_at'],
                'updated_at' => $wl['updated_at']
            ],
            'wishes' => $wishes,
            'export_metadata' => [
                'exported_at' => date('c'),
                'total_wishes' => count($wishes),
                'format_version' => '1.0'
            ]
        ];

        $filename = 'wishlist-' . $wl['id'] . '-' . date('Y-m-d') . '.json';
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        
        echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function exportPdf(array $params): void
    {
        $uid = $this->requireAuth();
        $wl = $this->getOwned((int)$params['id'], $uid);
        if (!$wl) { Router::status(404); echo 'Not found'; return; }

        $stmt = $this->pdo->prepare('SELECT * FROM wishes WHERE wishlist_id=:wl ORDER BY COALESCE(priority, 999) ASC, created_at DESC');
        $stmt->execute(['wl' => $wl['id']]);
        $wishes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Render PDF template directly without layout
        extract([
            'title' => $wl['title'] . ' - PDF Export',
            'wl' => $wl,
            'wishes' => $wishes,
            'baseUrl' => rtrim($this->config['app']['base_url'] ?? '', '/')
        ], EXTR_SKIP);
        
        $tpl = __DIR__ . '/../../../templates/wishlists/export_pdf.php';
        if (!file_exists($tpl)) {
            http_response_code(500);
            echo "Template not found";
            return;
        }
        require $tpl;
    }

    // === API Methods ===

    public function apiIndex(): void
    {
        $uid = $this->requireApiAuth();
        
        $stmt = $this->pdo->prepare('SELECT id, title, description, is_public, created_at FROM wishlists WHERE user_id = :uid ORDER BY created_at DESC');
        $stmt->execute(['uid' => $uid]);
        $wishlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert to proper types
        foreach ($wishlists as &$wl) {
            $wl['id'] = (int)$wl['id'];
            $wl['is_public'] = (bool)$wl['is_public'];
        }
        
        Router::json(['wishlists' => $wishlists]);
    }

    public function apiCreate(): void
    {
        $uid = $this->requireApiAuth();
        
        try {
            $input = Router::inputJson();
            $title = trim($input['title'] ?? '');
            $description = trim($input['description'] ?? '');
            $isPublic = (bool)($input['is_public'] ?? false);
            
            if ($title === '') {
                Router::json(['type' => 'about:blank', 'title' => 'Validation Error', 'status' => 400, 'detail' => 'Title is required.'], 400);
                return;
            }
            
            $shareSlug = $isPublic ? $this->uniqueSlug() : null;
            
            $stmt = $this->pdo->prepare('INSERT INTO wishlists (user_id, title, description, is_public, share_slug) VALUES (:uid, :title, :desc, :pub, :slug)');
            $stmt->execute([
                'uid' => $uid,
                'title' => $title,
                'desc' => $description,
                'pub' => $isPublic ? 1 : 0,
                'slug' => $shareSlug
            ]);
            
            $id = (int)$this->pdo->lastInsertId();
            
            Router::json([
                'id' => $id,
                'title' => $title,
                'description' => $description,
                'is_public' => $isPublic,
                'share_slug' => $shareSlug
            ], 201);
            
        } catch (\Throwable $e) {
            Router::json(['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500, 'detail' => 'Failed to create wishlist.'], 500);
        }
    }

    public function apiShow(array $params): void
    {
        $uid = $this->requireApiAuth();
        $wl = $this->getOwned((int)$params['id'], $uid);
        
        if (!$wl) {
            Router::json(['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404, 'detail' => 'Wishlist not found.'], 404);
            return;
        }
        
        // Get wishes for this wishlist
        $stmt = $this->pdo->prepare('SELECT * FROM wishes WHERE wishlist_id = :wl ORDER BY COALESCE(priority, 999) ASC, created_at DESC');
        $stmt->execute(['wl' => $wl['id']]);
        $wishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert types
        foreach ($wishes as &$wish) {
            $wish['id'] = (int)$wish['id'];
            $wish['wishlist_id'] = (int)$wish['wishlist_id'];
            $wish['priority'] = $wish['priority'] ? (int)$wish['priority'] : null;
            $wish['price_cents'] = $wish['price_cents'] ? (int)$wish['price_cents'] : null;
        }
        
        $wl['id'] = (int)$wl['id'];
        $wl['user_id'] = (int)$wl['user_id'];
        $wl['is_public'] = (bool)$wl['is_public'];
        $wl['wishes'] = $wishes;
        
        Router::json($wl);
    }

    public function apiUpdate(array $params): void
    {
        $uid = $this->requireApiAuth();
        $wl = $this->getOwned((int)$params['id'], $uid);
        
        if (!$wl) {
            Router::json(['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404, 'detail' => 'Wishlist not found.'], 404);
            return;
        }
        
        try {
            $input = Router::inputJson();
            $title = trim($input['title'] ?? $wl['title']);
            $description = trim($input['description'] ?? $wl['description']);
            $isPublic = isset($input['is_public']) ? (bool)$input['is_public'] : (bool)$wl['is_public'];
            
            if ($title === '') {
                Router::json(['type' => 'about:blank', 'title' => 'Validation Error', 'status' => 400, 'detail' => 'Title is required.'], 400);
                return;
            }
            
            // Handle slug generation
            $shareSlug = $wl['share_slug'];
            if ($isPublic && !$shareSlug) {
                $shareSlug = $this->uniqueSlug();
            } elseif (!$isPublic) {
                $shareSlug = null;
            }
            
            $stmt = $this->pdo->prepare('UPDATE wishlists SET title = :title, description = :desc, is_public = :pub, share_slug = :slug WHERE id = :id');
            $stmt->execute([
                'id' => $wl['id'],
                'title' => $title,
                'desc' => $description,
                'pub' => $isPublic ? 1 : 0,
                'slug' => $shareSlug
            ]);
            
            Router::json([
                'id' => (int)$wl['id'],
                'title' => $title,
                'description' => $description,
                'is_public' => $isPublic,
                'share_slug' => $shareSlug
            ]);
            
        } catch (\Throwable $e) {
            Router::json(['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500, 'detail' => 'Failed to update wishlist.'], 500);
        }
    }

    public function apiDelete(array $params): void
    {
        $uid = $this->requireApiAuth();
        $wl = $this->getOwned((int)$params['id'], $uid);
        
        if (!$wl) {
            Router::json(['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404, 'detail' => 'Wishlist not found.'], 404);
            return;
        }
        
        try {
            // Delete wishes first (foreign key constraint)
            $stmt = $this->pdo->prepare('DELETE FROM wishes WHERE wishlist_id = :id');
            $stmt->execute(['id' => $wl['id']]);
            
            // Delete wishlist
            $stmt = $this->pdo->prepare('DELETE FROM wishlists WHERE id = :id');
            $stmt->execute(['id' => $wl['id']]);
            
            Router::status(204); // No Content
            
        } catch (\Throwable $e) {
            Router::json(['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500, 'detail' => 'Failed to delete wishlist.'], 500);
        }
    }

    public function apiPublicBySlug(array $params): void
    {
        $slug = $params['slug'] ?? '';
        $stmt = $this->pdo->prepare('SELECT id, title, description, is_public, share_slug, created_at FROM wishlists WHERE share_slug = :s AND is_public = 1');
        $stmt->execute(['s' => $slug]);
        $wl = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$wl) {
            Router::json(['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404, 'detail' => 'Public wishlist not found.'], 404);
            return;
        }
        
        // Get wishes
        $stmt = $this->pdo->prepare('SELECT * FROM wishes WHERE wishlist_id = :wl ORDER BY priority ASC, id ASC');
        $stmt->execute(['wl' => $wl['id']]);
        $wishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert types
        foreach ($wishes as &$wish) {
            $wish['id'] = (int)$wish['id'];
            $wish['wishlist_id'] = (int)$wish['wishlist_id'];
            $wish['priority'] = $wish['priority'] ? (int)$wish['priority'] : null;
            $wish['price_cents'] = $wish['price_cents'] ? (int)$wish['price_cents'] : null;
        }
        
        $wl['id'] = (int)$wl['id'];
        $wl['is_public'] = true;
        $wl['wishes'] = $wishes;
        
        Router::json($wl);
    }
}
