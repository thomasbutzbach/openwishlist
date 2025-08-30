<?php
declare(strict_types=1);

require_once __DIR__ . '/ApiTestBase.php';

final class WishApiTest extends ApiTestBase
{
    private string $testEmail;

    public function __construct()
    {
        $this->testEmail = 'wishtest' . time() . '@example.com';
    }

    public function testCreateWishSuccess(): void
    {
        $this->loginAsTestUser($this->testEmail);
        $wishlist = $this->createTestWishlist('Test Wishlist');
        
        $response = $this->post("/api/wishlists/{$wishlist['id']}/wishes", [
            'title' => 'Amazing Product',
            'url' => 'https://example.com/product',
            'notes' => 'Really want this one!',
            'priority' => 2,
            'image_mode' => 'link',
            'image_url' => 'https://example.com/image.jpg',
            'price' => '29.99'
        ]);
        
        $json = $this->assertJsonResponse($response, 201);
        
        Assert::arrayHasKey('id', $json, 'Response should contain ID');
        Assert::equals('Amazing Product', $json['title'], 'Title should match');
        Assert::equals('https://example.com/product', $json['url'], 'URL should match');
        Assert::equals('Really want this one!', $json['notes'], 'Notes should match');
        Assert::equals(2, $json['priority'], 'Priority should match');
        Assert::equals('link', $json['image_mode'], 'Image mode should match');
        Assert::equals('https://example.com/image.jpg', $json['image_url'], 'Image URL should match');
        Assert::equals(2999, $json['price_cents'], 'Price should be converted to cents');
        Assert::equals($wishlist['id'], $json['wishlist_id'], 'Should belong to correct wishlist');
        
        $this->cleanup();
    }

    public function testCreateWishMinimal(): void
    {
        $this->loginAsTestUser($this->testEmail);
        $wishlist = $this->createTestWishlist('Test Wishlist');
        
        $response = $this->post("/api/wishlists/{$wishlist['id']}/wishes", [
            'title' => 'Simple Wish'
            // All other fields optional
        ]);
        
        $json = $this->assertJsonResponse($response, 201);
        
        Assert::equals('Simple Wish', $json['title'], 'Title should match');
        Assert::equals('', $json['url'], 'URL should be empty');
        Assert::equals('', $json['notes'], 'Notes should be empty');
        Assert::null($json['priority'], 'Priority should be null');
        Assert::equals('none', $json['image_mode'], 'Image mode should default to none');
        Assert::null($json['price_cents'], 'Price should be null');
        
        $this->cleanup();
    }

    public function testCreateWishValidationError(): void
    {
        $this->loginAsTestUser($this->testEmail);
        $wishlist = $this->createTestWishlist('Test Wishlist');
        
        $response = $this->post("/api/wishlists/{$wishlist['id']}/wishes", [
            'title' => '' // empty title
        ]);
        
        $json = $this->assertErrorResponse($response, 400, 'Validation Error');
        Assert::contains('required', $json['detail'], 'Should mention title is required');
        
        $this->cleanup();
    }

    public function testCreateWishNonexistentWishlist(): void
    {
        $this->loginAsTestUser($this->testEmail);
        
        $response = $this->post('/api/wishlists/99999/wishes', [
            'title' => 'Test Wish'
        ]);
        
        $json = $this->assertErrorResponse($response, 404, 'Not Found');
        Assert::contains('not found', $json['detail'], 'Should mention wishlist not found');
        
        $this->cleanup();
    }

    public function testGetWish(): void
    {
        $this->loginAsTestUser($this->testEmail);
        $wishlist = $this->createTestWishlist('Test Wishlist');
        $wish = $this->createTestWish($wishlist['id'], 'Test Wish');
        
        $response = $this->get("/api/wishes/{$wish['id']}");
        
        $json = $this->assertJsonResponse($response, 200);
        
        Assert::equals($wish['id'], $json['id'], 'Should return correct wish');
        Assert::equals('Test Wish', $json['title'], 'Title should match');
        Assert::equals($wishlist['id'], $json['wishlist_id'], 'Should belong to correct wishlist');
        
        $this->cleanup();
    }

    public function testUpdateWish(): void
    {
        $this->loginAsTestUser($this->testEmail);
        $wishlist = $this->createTestWishlist('Test Wishlist');
        $wish = $this->createTestWish($wishlist['id'], 'Original Title');
        
        $response = $this->put("/api/wishes/{$wish['id']}", [
            'title' => 'Updated Title',
            'url' => 'https://updated.com',
            'priority' => 1,
            'price' => '49.95'
        ]);
        
        $json = $this->assertJsonResponse($response, 200);
        
        Assert::equals('Updated Title', $json['title'], 'Title should be updated');
        Assert::equals('https://updated.com', $json['url'], 'URL should be updated');
        Assert::equals(1, $json['priority'], 'Priority should be updated');
        Assert::equals(4995, $json['price_cents'], 'Price should be updated');
        
        $this->cleanup();
    }

    public function testUpdateWishPartial(): void
    {
        $this->loginAsTestUser($this->testEmail);
        $wishlist = $this->createTestWishlist('Test Wishlist');
        $wish = $this->createTestWish($wishlist['id'], 'Original Title');
        
        // Only update title, leave other fields unchanged
        $response = $this->put("/api/wishes/{$wish['id']}", [
            'title' => 'Partially Updated'
        ]);
        
        $json = $this->assertJsonResponse($response, 200);
        
        Assert::equals('Partially Updated', $json['title'], 'Title should be updated');
        Assert::equals('https://example.com/product', $json['url'], 'URL should remain unchanged');
        Assert::equals(3, $json['priority'], 'Priority should remain unchanged');
        
        $this->cleanup();
    }

    public function testDeleteWish(): void
    {
        $this->loginAsTestUser($this->testEmail);
        $wishlist = $this->createTestWishlist('Test Wishlist');
        $wish = $this->createTestWish($wishlist['id'], 'To Delete');
        
        $response = $this->delete("/api/wishes/{$wish['id']}");
        
        Assert::equals(204, $response['status'], 'Should return 204 No Content');
        
        // Verify it's gone
        $getResponse = $this->get("/api/wishes/{$wish['id']}");
        $this->assertErrorResponse($getResponse, 404, 'Not Found');
        
        $this->cleanup();
    }

    public function testGetNonexistentWish(): void
    {
        $this->loginAsTestUser($this->testEmail);
        
        $response = $this->get('/api/wishes/99999');
        
        $json = $this->assertErrorResponse($response, 404, 'Not Found');
        Assert::contains('not found', $json['detail'], 'Should mention wish not found');
        
        $this->cleanup();
    }

    public function testUpdateNonexistentWish(): void
    {
        $this->loginAsTestUser($this->testEmail);
        
        $response = $this->put('/api/wishes/99999', [
            'title' => 'Updated Title'
        ]);
        
        $json = $this->assertErrorResponse($response, 404, 'Not Found');
        Assert::contains('not found', $json['detail'], 'Should mention wish not found');
        
        $this->cleanup();
    }

    public function testRefetchImageSuccess(): void
    {
        $this->loginAsTestUser($this->testEmail);
        $wishlist = $this->createTestWishlist('Test Wishlist');
        
        // Create wish with local image mode
        $response = $this->post("/api/wishlists/{$wishlist['id']}/wishes", [
            'title' => 'Local Image Wish',
            'image_mode' => 'local',
            'image_url' => 'https://example.com/image.jpg'
        ]);
        $wish = $this->assertJsonResponse($response, 201);
        
        $refetchResponse = $this->post("/api/wishes/{$wish['id']}/image/refetch");
        
        $json = $this->assertJsonResponse($refetchResponse, 200);
        Assert::arrayHasKey('message', $json, 'Should contain success message');
        Assert::contains('queued', $json['message'], 'Should mention job queued');
        
        $this->cleanup();
    }

    public function testRefetchImageInvalidMode(): void
    {
        $this->loginAsTestUser($this->testEmail);
        $wishlist = $this->createTestWishlist('Test Wishlist');
        
        // Create wish with link image mode (not local)
        $response = $this->post("/api/wishlists/{$wishlist['id']}/wishes", [
            'title' => 'Link Image Wish',
            'image_mode' => 'link',
            'image_url' => 'https://example.com/image.jpg'
        ]);
        $wish = $this->assertJsonResponse($response, 201);
        
        $refetchResponse = $this->post("/api/wishes/{$wish['id']}/image/refetch");
        
        $json = $this->assertErrorResponse($refetchResponse, 400, 'Bad Request');
        Assert::contains('Only local images', $json['detail'], 'Should mention local images only');
        
        $this->cleanup();
    }

    public function testPriceParsing(): void
    {
        $this->loginAsTestUser($this->testEmail);
        $wishlist = $this->createTestWishlist('Test Wishlist');
        
        // Test various price formats
        $testCases = [
            ['12.34', 1234],
            ['12,34', 1234],
            ['0.99', 99],
            ['100', 10000],
            ['0', 0],
            ['', null]
        ];
        
        foreach ($testCases as [$input, $expected]) {
            $response = $this->post("/api/wishlists/{$wishlist['id']}/wishes", [
                'title' => "Price Test: $input",
                'price' => $input
            ]);
            
            $json = $this->assertJsonResponse($response, 201);
            Assert::equals($expected, $json['price_cents'], "Price '$input' should convert to $expected cents");
        }
        
        $this->cleanup();
    }
}