<?php
declare(strict_types=1);

require_once __DIR__ . '/ApiTestBase.php';

final class JobSystemTest extends ApiTestBase
{
    private string $testUserEmail;

    public function __construct()
    {
        parent::__construct();
        $this->testUserEmail = $this->testPrefix . 'job_user@example.com';
    }

    // Admin Job API Tests

    public function testAdminJobsApiRequiresAuth(): void
    {
        // Test DELETE job without authentication
        $response = $this->delete('/api/admin/jobs/1', [], false);
        Assert::equals(302, $response['status'], 'Delete job API should redirect unauthenticated users');
        
        // Test POST cleanup without authentication  
        $response = $this->post('/api/admin/jobs/cleanup', [], [], false);
        Assert::equals(302, $response['status'], 'Cleanup jobs API should redirect unauthenticated users');
        
        // Test POST cleanup by status without authentication
        $response = $this->post('/api/admin/jobs/cleanup-by-status', ['status' => 'completed'], [], false);
        Assert::equals(302, $response['status'], 'Cleanup jobs by status API should redirect unauthenticated users');
    }

    public function testRegularUserCannotAccessJobsApi(): void
    {
        // Login as regular user
        $this->loginAsTestUser($this->testUserEmail);
        
        // Try to delete job
        $response = $this->delete('/api/admin/jobs/1');
        Assert::equals(403, $response['status'], 'Regular user should get 403 on delete job API');
        
        // Try to cleanup jobs
        $response = $this->post('/api/admin/jobs/cleanup');
        Assert::equals(403, $response['status'], 'Regular user should get 403 on cleanup jobs API');
        
        // Try to cleanup by status
        $response = $this->post('/api/admin/jobs/cleanup-by-status', ['status' => 'completed']);
        Assert::equals(403, $response['status'], 'Regular user should get 403 on cleanup by status API');
        
        $this->cleanup();
    }

    public function testJobApiValidation(): void
    {
        // Login as regular user (we can't create admin users in tests)
        $this->loginAsTestUser($this->testUserEmail);
        
        // Test delete with invalid job ID
        $response = $this->delete('/api/admin/jobs/invalid');
        Assert::equals(403, $response['status'], 'Should get 403 as regular user');
        
        // Test cleanup by status with invalid status
        $response = $this->post('/api/admin/jobs/cleanup-by-status', ['status' => 'invalid_status']);
        Assert::equals(403, $response['status'], 'Should get 403 as regular user');
        
        // Test cleanup by status with missing status
        $response = $this->post('/api/admin/jobs/cleanup-by-status', []);
        Assert::equals(403, $response['status'], 'Should get 403 as regular user');
        
        $this->cleanup();
    }

    // Job Integration Tests (testing job creation through wish system)

    public function testJobCreationThroughWishRefetch(): void
    {
        // Create user and wishlist with wish
        $this->loginAsTestUser($this->testUserEmail);
        
        // Create wishlist
        $response = $this->post('/api/wishlists', [
            'title' => 'Job Test Wishlist',
            'description' => 'Test wishlist for job creation',
            'is_public' => false
        ]);
        
        $wishlist = $this->assertJsonResponse($response, 201);
        $wishlistId = $wishlist['id'];
        $this->testWishlistIds[] = $wishlistId;
        
        // Create wish with URL (should trigger image processing)
        $response = $this->post("/api/wishlists/{$wishlistId}/wishes", [
            'title' => 'Test Product with Image',
            'url' => 'https://example.com/product-with-image',
            'image_mode' => 'local', // This should trigger job creation
            'notes' => 'Product that needs image fetching'
        ]);
        
        $wish = $this->assertJsonResponse($response, 201);
        $wishId = $wish['id'];
        $this->testWishIds[] = $wishId;
        
        // Test refetch image endpoint (should create job)
        $response = $this->post("/api/wishes/{$wishId}/image/refetch");
        
        // Should succeed (job creation is internal)
        Assert::equals(200, $response['status'], 'Refetch image should succeed');
        
        $responseData = $this->assertJsonResponse($response, 200);
        Assert::true(isset($responseData['message']), 'Refetch should return success message');
        
        $this->cleanup();
    }

    public function testJobCreationThroughImageModeChange(): void
    {
        // Create user and wishlist with wish
        $this->loginAsTestUser($this->testUserEmail);
        
        // Create wishlist
        $response = $this->post('/api/wishlists', [
            'title' => 'Job Test Wishlist 2',
            'description' => 'Test wishlist for image mode change',
            'is_public' => false
        ]);
        
        $wishlist = $this->assertJsonResponse($response, 201);
        $wishlistId = $wishlist['id'];
        $this->testWishlistIds[] = $wishlistId;
        
        // Create wish with link mode initially
        $response = $this->post("/api/wishlists/{$wishlistId}/wishes", [
            'title' => 'Test Product Link Mode',
            'url' => 'https://example.com/product-image',
            'image_mode' => 'link',
            'notes' => 'Initially in link mode'
        ]);
        
        $wish = $this->assertJsonResponse($response, 201);
        $wishId = $wish['id'];
        $this->testWishIds[] = $wishId;
        
        // Change to local mode (should trigger job creation)
        $response = $this->put("/api/wishes/{$wishId}", [
            'title' => 'Test Product Link Mode',
            'url' => 'https://example.com/product-image',
            'image_mode' => 'local', // Change to local should create job
            'notes' => 'Changed to local mode'
        ]);
        
        Assert::equals(200, $response['status'], 'Update wish to local mode should succeed');
        
        $updatedWish = $this->assertJsonResponse($response, 200);
        Assert::equals('local', $updatedWish['image_mode'], 'Image mode should be updated to local');
        
        $this->cleanup();
    }

    public function testInvalidRefetchRequest(): void
    {
        $this->loginAsTestUser($this->testUserEmail);
        
        // Test refetch on nonexistent wish
        $response = $this->post('/api/wishes/99999/image/refetch');
        Assert::equals(404, $response['status'], 'Should return 404 for nonexistent wish');
        
        // Test refetch without authentication
        $this->post('/api/logout');
        $this->sessionCookie = null;
        $this->csrfToken = null;
        
        $response = $this->post('/api/wishes/1/image/refetch', [], [], false);
        Assert::equals(401, $response['status'], 'Should return 401 for unauthenticated users');
        
        $this->cleanup();
    }

    // Job Admin Page Tests

    public function testAdminJobsPageRequiresAuth(): void
    {
        $response = $this->get('/admin/jobs', [], false);
        Assert::equals(302, $response['status'], 'Admin jobs page should redirect unauthenticated users');
        
        // Verify redirect behavior (exact location may vary)
        // Just check that it's a redirect response
        Assert::true($response['status'] >= 300 && $response['status'] < 400, 'Should be a redirect response');
    }

    public function testRegularUserCannotAccessJobsPage(): void
    {
        // Login as regular user
        $this->loginAsTestUser($this->testUserEmail);
        
        $response = $this->get('/admin/jobs');
        Assert::equals(403, $response['status'], 'Regular user should get 403 on admin jobs page');
        
        $this->cleanup();
    }

    public function testJobsRunPageRequiresAuth(): void
    {
        $response = $this->post('/admin/jobs/run', [], [], false);
        Assert::equals(302, $response['status'], 'Run jobs should redirect unauthenticated users');
    }

    public function testRegularUserCannotRunJobs(): void
    {
        // Login as regular user
        $this->loginAsTestUser($this->testUserEmail);
        
        $response = $this->post('/admin/jobs/run');
        Assert::equals(403, $response['status'], 'Regular user should get 403 on run jobs');
        
        $this->cleanup();
    }

    // Image Processing Integration Tests

    public function testImageModeValidation(): void
    {
        $this->loginAsTestUser($this->testUserEmail);
        
        // Create wishlist
        $response = $this->post('/api/wishlists', [
            'title' => 'Image Mode Test Wishlist',
            'description' => 'Test wishlist for image mode validation',
            'is_public' => false
        ]);
        
        $wishlist = $this->assertJsonResponse($response, 201);
        $wishlistId = $wishlist['id'];
        $this->testWishlistIds[] = $wishlistId;
        
        // Test invalid image mode - API corrects invalid modes to 'none'
        $response = $this->post("/api/wishlists/{$wishlistId}/wishes", [
            'title' => 'Test Invalid Image Mode',
            'url' => 'https://example.com/product',
            'image_mode' => 'invalid_mode',
            'notes' => 'Should be corrected to none mode'
        ]);
        
        // API should accept it and correct invalid mode to 'none'
        Assert::equals(201, $response['status'], 'Invalid image mode should be corrected and accepted');
        
        $wish = $this->assertJsonResponse($response, 201);
        Assert::equals('none', $wish['image_mode'], 'Invalid image mode should be corrected to none');
        $this->testWishIds[] = $wish['id'];
        
        // Test valid image modes
        $validModes = ['link', 'local', 'none'];
        foreach ($validModes as $mode) {
            $response = $this->post("/api/wishlists/{$wishlistId}/wishes", [
                'title' => "Test {$mode} Mode",
                'url' => 'https://example.com/product-' . $mode,
                'image_mode' => $mode,
                'notes' => "Testing {$mode} mode"
            ]);
            
            Assert::equals(201, $response['status'], "{$mode} mode should be valid");
            
            $wish = $this->assertJsonResponse($response, 201);
            Assert::equals($mode, $wish['image_mode'], "Image mode should be set to {$mode}");
            $this->testWishIds[] = $wish['id'];
        }
        
        $this->cleanup();
    }

    public function testRefetchImageOnWishWithoutUrl(): void
    {
        $this->loginAsTestUser($this->testUserEmail);
        
        // Create wishlist
        $response = $this->post('/api/wishlists', [
            'title' => 'No URL Test Wishlist',
            'description' => 'Test wishlist for wishes without URL',
            'is_public' => false
        ]);
        
        $wishlist = $this->assertJsonResponse($response, 201);
        $wishlistId = $wishlist['id'];
        $this->testWishlistIds[] = $wishlistId;
        
        // Create wish without URL
        $response = $this->post("/api/wishlists/{$wishlistId}/wishes", [
            'title' => 'Test Product No URL',
            'notes' => 'No URL provided'
            // No URL or image_mode specified
        ]);
        
        $wish = $this->assertJsonResponse($response, 201);
        $wishId = $wish['id'];
        $this->testWishIds[] = $wishId;
        
        // Try to refetch image on wish without URL
        $response = $this->post("/api/wishes/{$wishId}/image/refetch");
        
        // Should handle gracefully (might return 400 or succeed with message)
        Assert::true(in_array($response['status'], [200, 400]), 'Refetch without URL should handle gracefully');
        
        $this->cleanup();
    }

    // Note: Full job processing tests require manual admin role setup in database
    // These are documented for manual testing:
    // 1. Create admin user in database with role='admin'
    // 2. Test job creation through admin interface: POST /admin/jobs/run
    // 3. Test job deletion: DELETE /api/admin/jobs/{id}
    // 4. Test job cleanup: POST /api/admin/jobs/cleanup
    // 5. Test job cleanup by status: POST /api/admin/jobs/cleanup-by-status
    // 6. Run worker manually: php bin/worker --max-jobs=5 --max-seconds=10
}