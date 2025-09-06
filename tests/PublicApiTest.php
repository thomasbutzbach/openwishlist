<?php
declare(strict_types=1);

require_once __DIR__ . '/ApiTestBase.php';

final class PublicApiTest extends ApiTestBase
{
    private string $testEmail;

    public function __construct()
    {
        $this->testEmail = 'publictest' . time() . '@example.com';
    }

    public function testHealthEndpoint(): void
    {
        // Test the simple /health endpoint (different from /health.php)
        $response = $this->get('/health');
        
        $json = $this->assertJsonResponse($response, 200);
        
        Assert::arrayHasKey('status', $json, 'Health response should contain status');
        Assert::arrayHasKey('time', $json, 'Health response should contain time');
        Assert::equals('ok', $json['status'], 'Status should be ok');
        Assert::true(strlen($json['time']) > 0, 'Time should not be empty');
    }

    public function testPublicWishlistBySlug(): void
    {
        $this->loginAsTestUser($this->testEmail);
        
        // Create a public wishlist
        $response = $this->post('/api/wishlists', [
            'title' => 'Public Test Wishlist',
            'description' => 'A public wishlist for testing',
            'is_public' => true
        ]);
        $wishlist = $this->assertJsonResponse($response, 201);
        
        // Add some wishes
        $this->createTestWish($wishlist['id'], 'Public Wish 1');
        $this->createTestWish($wishlist['id'], 'Public Wish 2');
        
        // Logout to test public access
        $this->post('/api/logout');
        $this->sessionCookie = null;
        
        // Access public wishlist by slug
        $publicResponse = $this->get("/api/public/lists/{$wishlist['share_slug']}");
        
        $json = $this->assertJsonResponse($publicResponse, 200);
        
        Assert::equals($wishlist['id'], $json['id'], 'Should return correct wishlist');
        Assert::equals('Public Test Wishlist', $json['title'], 'Title should match');
        Assert::true($json['is_public'], 'Should be public');
        Assert::arrayHasKey('wishes', $json, 'Should contain wishes');
        Assert::equals(2, count($json['wishes']), 'Should have 2 wishes');
        
        // Check wish structure
        $wish = $json['wishes'][0];
        Assert::arrayHasKey('title', $wish, 'Wish should have title');
        Assert::arrayHasKey('url', $wish, 'Wish should have URL');
        Assert::arrayHasKey('priority', $wish, 'Wish should have priority');
    }

    public function testPublicWishlistNonexistentSlug(): void
    {
        // Try to access non-existent public wishlist
        $response = $this->get('/api/public/lists/nonexistent-slug');
        
        $json = $this->assertErrorResponse($response, 404, 'Not Found');
        Assert::contains('not found', $json['detail'], 'Should mention wishlist not found');
    }

    public function testPublicWishlistPrivateList(): void
    {
        $this->loginAsTestUser($this->testEmail);
        
        // Create a private wishlist
        $response = $this->post('/api/wishlists', [
            'title' => 'Private Wishlist',
            'description' => 'Should not be accessible publicly',
            'is_public' => false
        ]);
        $wishlist = $this->assertJsonResponse($response, 201);
        
        // Make it public temporarily to get a slug, then make it private
        $updateResponse = $this->put("/api/wishlists/{$wishlist['id']}", [
            'is_public' => true
        ]);
        $publicWishlist = $this->assertJsonResponse($updateResponse, 200);
        $slug = $publicWishlist['share_slug'];
        
        // Make it private again
        $this->put("/api/wishlists/{$wishlist['id']}", [
            'is_public' => false
        ]);
        
        // Logout and try to access
        $this->post('/api/logout');
        $this->sessionCookie = null;
        
        // Should not be accessible now
        $publicResponse = $this->get("/api/public/lists/$slug");
        
        $json = $this->assertErrorResponse($publicResponse, 404, 'Not Found');
        Assert::contains('not found', $json['detail'], 'Private wishlist should not be accessible');
    }

    public function testUnauthorizedEndpoints(): void
    {
        // Test various endpoints that require authentication
        $protectedEndpoints = [
            ['GET', '/api/wishlists'],
            ['POST', '/api/wishlists'],
            ['GET', '/api/wishlists/1'],
            ['PUT', '/api/wishlists/1'],
            ['DELETE', '/api/wishlists/1'],
            ['POST', '/api/wishlists/1/wishes'],
            ['GET', '/api/wishes/1'],
            ['PUT', '/api/wishes/1'],
            ['DELETE', '/api/wishes/1'],
            ['POST', '/api/wishes/1/image/refetch']
        ];

        foreach ($protectedEndpoints as [$method, $path]) {
            $response = $this->makeRequest($method, $path);
            
            Assert::equals(401, $response['status'], 
                "$method $path should require authentication");
            
            if ($response['json']) {
                Assert::arrayHasKey('detail', $response['json'], 
                    "$method $path should return error detail");
                Assert::contains('Authentication required', $response['json']['detail'],
                    "$method $path should mention authentication requirement");
            }
        }
    }

    public function testJsonContentType(): void
    {
        $this->loginAsTestUser($this->testEmail);
        
        $response = $this->get('/api/wishlists');
        
        $this->assertJsonResponse($response, 200);
        Assert::contains('application/json', $response['headers'], 
            'API responses should have JSON content type');
    }

    public function testCrossOriginRequests(): void
    {
        // Test basic CORS handling (if implemented)
        $response = $this->makeRequest('OPTIONS', '/api/wishlists');
        
        // This might be 404 if OPTIONS isn't implemented, which is fine for now
        // Just checking that the server doesn't crash
        Assert::true($response['status'] >= 200 && $response['status'] < 500, 
            'OPTIONS request should not cause server error');
    }

    public function testInvalidJsonInput(): void
    {
        $this->loginAsTestUser($this->testEmail);
        
        // Send malformed JSON
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/wishlists',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{"invalid": json}',  // Malformed JSON
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Cookie: ' . $this->sessionCookie
            ],
            CURLOPT_HEADER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Should handle malformed JSON gracefully
        Assert::true($httpCode >= 400 && $httpCode < 500, 
            'Malformed JSON should return client error, got ' . $httpCode);
    }
}