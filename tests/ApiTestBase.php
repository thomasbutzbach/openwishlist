<?php
declare(strict_types=1);

require_once __DIR__ . '/TestRunner.php';

/**
 * Base class for API testing with HTTP client functionality
 */
abstract class ApiTestBase
{
    protected string $baseUrl = 'http://127.0.0.1:8080';
    protected ?string $sessionCookie = null;
    protected ?string $csrfToken = null;
    protected array $testUserIds = [];
    protected array $testWishlistIds = [];
    protected array $testWishIds = [];
    protected string $testPrefix;

    protected function makeRequest(
        string $method, 
        string $path, 
        ?array $data = null, 
        array $headers = []
    ): array {
        $url = $this->baseUrl . $path;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 10
        ]);

        // Add session cookie if available
        if ($this->sessionCookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $this->sessionCookie);
        }

        // Add CSRF token if available and needed
        if ($this->csrfToken && in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $headers['X-CSRF-Token'] = $this->csrfToken;
        }

        // Add JSON data for API calls
        if ($data !== null) {
            $headers['Content-Type'] = 'application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        // Set headers
        if (!empty($headers)) {
            $headerList = [];
            foreach ($headers as $key => $value) {
                $headerList[] = "$key: $value";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerList);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        if ($response === false) {
            throw new Exception('CURL error: ' . curl_error($ch));
        }
        
        curl_close($ch);

        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        // Extract session cookie from Set-Cookie header
        if (preg_match('/Set-Cookie:\s*(owl_session=[^;]+)/', $headers, $matches)) {
            $this->sessionCookie = $matches[1];
        }

        // Try to parse JSON response
        $jsonData = json_decode($body, true);
        
        return [
            'status' => $httpCode,
            'headers' => $headers,
            'body' => $body,
            'json' => $jsonData,
            'raw' => $response
        ];
    }

    protected function get(string $path, array $headers = []): array
    {
        return $this->makeRequest('GET', $path, null, $headers);
    }

    protected function post(string $path, ?array $data = null, array $headers = []): array
    {
        return $this->makeRequest('POST', $path, $data, $headers);
    }

    protected function put(string $path, ?array $data = null, array $headers = []): array
    {
        return $this->makeRequest('PUT', $path, $data, $headers);
    }

    protected function delete(string $path, array $headers = []): array
    {
        return $this->makeRequest('DELETE', $path, null, $headers);
    }

    protected function assertJsonResponse(array $response, int $expectedStatus = 200): array
    {
        Assert::equals($expectedStatus, $response['status'], 
            "Expected HTTP $expectedStatus, got {$response['status']}. Body: {$response['body']}");
        
        Assert::notNull($response['json'], 'Response should be valid JSON');
        
        return $response['json'];
    }

    protected function assertErrorResponse(array $response, int $expectedStatus, string $expectedTitle = null): array
    {
        $json = $this->assertJsonResponse($response, $expectedStatus);
        
        Assert::arrayHasKey('type', $json, 'Error response should have type field');
        Assert::arrayHasKey('status', $json, 'Error response should have status field');
        Assert::arrayHasKey('detail', $json, 'Error response should have detail field');
        
        Assert::equals($expectedStatus, $json['status'], 'Status in JSON should match HTTP status');
        
        if ($expectedTitle) {
            Assert::arrayHasKey('title', $json, 'Error response should have title field');
            Assert::equals($expectedTitle, $json['title'], 'Error title mismatch');
        }
        
        return $json;
    }

    public function __construct()
    {
        $this->testPrefix = 'test_' . uniqid() . '_';
    }

    protected function loginAsTestUser(string $email = null, string $password = 'testpassword123'): array
    {
        if ($email === null) {
            $email = $this->testPrefix . 'user@example.com';
        }
        
        // Register test user (might fail if already exists, that's ok)
        $registerResponse = $this->post('/api/register', [
            'email' => $email,
            'password' => $password,
            'passwordConfirm' => $password
        ]);
        
        // Track user ID for cleanup if registration succeeded
        if ($registerResponse['status'] === 201 && isset($registerResponse['json']['id'])) {
            $this->testUserIds[] = $registerResponse['json']['id'];
        }
        
        // Login (works whether registration succeeded or user already existed)
        $response = $this->post('/api/login', [
            'email' => $email,
            'password' => $password
        ]);
        
        $json = $this->assertJsonResponse($response, 200);
        Assert::arrayHasKey('id', $json, 'Login response should contain user ID');
        Assert::arrayHasKey('email', $json, 'Login response should contain email');
        
        // Track user ID for cleanup
        if (!in_array($json['id'], $this->testUserIds)) {
            $this->testUserIds[] = $json['id'];
        }
        
        return $json;
    }

    protected function createTestWishlist(string $title = null): array
    {
        if ($title === null) {
            $title = $this->testPrefix . 'wishlist';
        }
        
        $response = $this->post('/api/wishlists', [
            'title' => $title,
            'description' => 'A test wishlist',
            'is_public' => false
        ]);
        
        $json = $this->assertJsonResponse($response, 201);
        
        // Track wishlist ID for cleanup
        $this->testWishlistIds[] = $json['id'];
        
        return $json;
    }

    protected function createTestWish(int $wishlistId, string $title = null): array
    {
        if ($title === null) {
            $title = $this->testPrefix . 'wish';
        }
        
        $response = $this->post("/api/wishlists/$wishlistId/wishes", [
            'title' => $title,
            'url' => 'https://example.com/product',
            'notes' => 'Test notes',
            'priority' => 3,
            'image_mode' => 'link',
            'image_url' => 'https://example.com/image.jpg',
            'price' => '19.99'
        ]);
        
        $json = $this->assertJsonResponse($response, 201);
        
        // Track wish ID for cleanup
        $this->testWishIds[] = $json['id'];
        
        return $json;
    }

    public function cleanup(): void
    {
        // Cleanup wishes first (foreign key constraints)
        foreach ($this->testWishIds as $wishId) {
            $this->delete("/api/wishes/$wishId");
        }
        
        // Cleanup wishlists
        foreach ($this->testWishlistIds as $wishlistId) {
            $this->delete("/api/wishlists/$wishlistId");
        }
        
        // Logout if logged in
        if ($this->sessionCookie) {
            $this->post('/api/logout');
            $this->sessionCookie = null;
            $this->csrfToken = null;
        }
        
        // Note: We don't delete users as they might be referenced by other data
        // and user cleanup should be done by a separate maintenance script
        
        // Reset tracking arrays
        $this->testWishIds = [];
        $this->testWishlistIds = [];
    }

    protected function cleanupDatabase(): void
    {
        // Force cleanup of all test data via direct API calls
        // This method can be called at the end of test suites
        
        if (!$this->sessionCookie) {
            // Try to login with any test user to get admin access
            foreach ($this->testUserIds as $userId) {
                // We can't login with user ID, so skip this approach
                break;
            }
        }
        
        // Alternative: Cleanup via database query patterns
        // This would require database access, which we'll implement separately
    }
}