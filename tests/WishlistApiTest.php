<?php
declare(strict_types=1);

require_once __DIR__ . '/ApiTestBase.php';

final class WishlistApiTest extends ApiTestBase
{
    private string $testEmail;

    public function __construct()
    {
        $this->testEmail = 'wishlisttest' . time() . '@example.com';
    }

    public function testCreateWishlistSuccess(): void
    {
        $this->loginAsTestUser($this->testEmail);
        
        $response = $this->post('/api/wishlists', [
            'title' => 'My Test Wishlist',
            'description' => 'A wishlist for testing',
            'is_public' => true
        ]);
        
        $json = $this->assertJsonResponse($response, 201);
        
        Assert::arrayHasKey('id', $json, 'Response should contain ID');
        Assert::equals('My Test Wishlist', $json['title'], 'Title should match');
        Assert::equals('A wishlist for testing', $json['description'], 'Description should match');
        Assert::true($json['is_public'], 'Should be public');
        Assert::arrayHasKey('share_slug', $json, 'Public wishlist should have share_slug');
        Assert::notNull($json['share_slug'], 'Share slug should not be null for public wishlist');
        
        $this->cleanup();
    }

    public function testCreateWishlistPrivate(): void
    {
        $this->loginAsTestUser($this->testEmail);
        
        $response = $this->post('/api/wishlists', [
            'title' => 'Private Wishlist',
            'description' => 'Private test wishlist',
            'is_public' => false
        ]);
        
        $json = $this->assertJsonResponse($response, 201);
        
        Assert::false($json['is_public'], 'Should be private');
        Assert::null($json['share_slug'], 'Private wishlist should not have share_slug');
        
        $this->cleanup();
    }

    public function testCreateWishlistValidationError(): void
    {
        $this->loginAsTestUser($this->testEmail);
        
        $response = $this->post('/api/wishlists', [
            'title' => '', // empty title
            'description' => 'Test description'
        ]);
        
        $json = $this->assertErrorResponse($response, 400, 'Validation Error');
        Assert::contains('required', $json['detail'], 'Should mention title is required');
        
        $this->cleanup();
    }

    public function testListWishlists(): void
    {
        $this->loginAsTestUser($this->testEmail);
        
        // Create a few wishlists
        $this->createTestWishlist('First Wishlist');
        $this->createTestWishlist('Second Wishlist');
        
        $response = $this->get('/api/wishlists');
        
        $json = $this->assertJsonResponse($response, 200);
        
        Assert::arrayHasKey('wishlists', $json, 'Response should contain wishlists array');
        Assert::true(count($json['wishlists']) >= 2, 'Should have at least 2 wishlists');
        
        // Check structure of first wishlist
        $first = $json['wishlists'][0];
        Assert::arrayHasKey('id', $first, 'Wishlist should have ID');
        Assert::arrayHasKey('title', $first, 'Wishlist should have title');
        Assert::arrayHasKey('description', $first, 'Wishlist should have description');
        Assert::arrayHasKey('is_public', $first, 'Wishlist should have is_public');
        Assert::arrayHasKey('created_at', $first, 'Wishlist should have created_at');
        
        $this->cleanup();
    }

    public function testGetWishlistWithWishes(): void
    {
        $this->loginAsTestUser($this->testEmail);
        
        // Create wishlist and wishes
        $wishlist = $this->createTestWishlist('Test Wishlist');
        $this->createTestWish($wishlist['id'], 'Test Wish 1');
        $this->createTestWish($wishlist['id'], 'Test Wish 2');
        
        $response = $this->get("/api/wishlists/{$wishlist['id']}");
        
        $json = $this->assertJsonResponse($response, 200);
        
        Assert::equals($wishlist['id'], $json['id'], 'Should return correct wishlist');
        Assert::arrayHasKey('wishes', $json, 'Response should contain wishes');
        Assert::equals(2, count($json['wishes']), 'Should have 2 wishes');
        
        // Check wish structure
        $wish = $json['wishes'][0];
        Assert::arrayHasKey('id', $wish, 'Wish should have ID');
        Assert::arrayHasKey('title', $wish, 'Wish should have title');
        Assert::arrayHasKey('priority', $wish, 'Wish should have priority');
        
        $this->cleanup();
    }

    public function testUpdateWishlist(): void
    {
        $this->loginAsTestUser($this->testEmail);
        
        $wishlist = $this->createTestWishlist('Original Title');
        
        $response = $this->put("/api/wishlists/{$wishlist['id']}", [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'is_public' => true
        ]);
        
        $json = $this->assertJsonResponse($response, 200);
        
        Assert::equals('Updated Title', $json['title'], 'Title should be updated');
        Assert::equals('Updated description', $json['description'], 'Description should be updated');
        Assert::true($json['is_public'], 'Should be public now');
        Assert::notNull($json['share_slug'], 'Should have share_slug when made public');
        
        $this->cleanup();
    }

    public function testDeleteWishlist(): void
    {
        $this->loginAsTestUser($this->testEmail);
        
        $wishlist = $this->createTestWishlist('To Delete');
        
        $response = $this->delete("/api/wishlists/{$wishlist['id']}");
        
        Assert::equals(204, $response['status'], 'Should return 204 No Content');
        
        // Verify it's gone
        $getResponse = $this->get("/api/wishlists/{$wishlist['id']}");
        $this->assertErrorResponse($getResponse, 404, 'Not Found');
        
        $this->cleanup();
    }

    public function testGetNonexistentWishlist(): void
    {
        $this->loginAsTestUser($this->testEmail);
        
        $response = $this->get('/api/wishlists/99999');
        
        $json = $this->assertErrorResponse($response, 404, 'Not Found');
        Assert::contains('not found', $json['detail'], 'Should mention wishlist not found');
        
        $this->cleanup();
    }

    public function testUpdateNonexistentWishlist(): void
    {
        $this->loginAsTestUser($this->testEmail);
        
        $response = $this->put('/api/wishlists/99999', [
            'title' => 'Updated Title'
        ]);
        
        $json = $this->assertErrorResponse($response, 404, 'Not Found');
        Assert::contains('not found', $json['detail'], 'Should mention wishlist not found');
        
        $this->cleanup();
    }

    public function testDeleteNonexistentWishlist(): void
    {
        $this->loginAsTestUser($this->testEmail);
        
        $response = $this->delete('/api/wishlists/99999');
        
        $json = $this->assertErrorResponse($response, 404, 'Not Found');
        Assert::contains('not found', $json['detail'], 'Should mention wishlist not found');
        
        $this->cleanup();
    }
}