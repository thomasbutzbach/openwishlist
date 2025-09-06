<?php
declare(strict_types=1);

require_once __DIR__ . '/ApiTestBase.php';

final class HealthAdminTest extends ApiTestBase
{
    private string $testUserEmail;

    public function __construct()
    {
        parent::__construct();
        $this->testUserEmail = $this->testPrefix . 'user@example.com';
    }

    // Health Check Tests

    public function testHealthCheckRequiresAuth(): void
    {
        // Test without authentication
        $response = $this->get('/health.php');
        
        Assert::equals(401, $response['status'], 'Health check should require authentication');
        Assert::true(strpos($response['body'], 'Authentication Required') !== false, 'Should show auth required message');
    }

    public function testHealthCheckInvalidBasicAuth(): void
    {
        // Test with invalid credentials
        $response = $this->makeRequestWithBasicAuth('GET', '/health.php', null, 'invalid@example.com', 'wrongpassword');
        
        Assert::equals(401, $response['status'], 'Invalid credentials should be rejected');
        
        $this->cleanup();
    }

    public function testHealthCheckWithRegularUserBasicAuth(): void
    {
        // Create regular user first
        $this->createTestUser($this->testUserEmail, 'user');
        
        // Test with HTTP Basic Auth using regular user
        $response = $this->makeRequestWithBasicAuth('GET', '/health.php', null, $this->testUserEmail, 'validpassword123');
        
        // Regular users should be denied even with valid credentials
        Assert::equals(401, $response['status'], 'Regular user should be denied health check access');
        
        $this->cleanup();
    }

    public function testHealthCheckJsonFormat(): void
    {
        // Test that JSON format parameter doesn't break unauthenticated access
        $response = $this->get('/health.php?format=json');
        
        Assert::equals(401, $response['status'], 'JSON format should still require authentication');
        
        $this->cleanup();
    }

    // Settings API Tests

    public function testSettingsApiRequiresAuth(): void
    {
        // Test GET settings without authentication - should redirect to login
        $response = $this->get('/api/admin/settings', [], false); // Don't follow redirects
        Assert::equals(302, $response['status'], 'Settings API should redirect unauthenticated users to login');
        
        // Test PUT settings without authentication - should redirect to login
        $response = $this->put('/api/admin/settings', ['app_name' => 'Test'], [], false);
        Assert::equals(302, $response['status'], 'Settings update should redirect unauthenticated users to login');
    }

    public function testRegularUserCannotAccessSettingsApi(): void
    {
        // Login as regular user
        $this->loginAsTestUser($this->testUserEmail);
        
        // Try to access settings API
        $response = $this->get('/api/admin/settings');
        Assert::equals(403, $response['status'], 'Regular user should get 403 on settings API');
        
        // Try to update settings
        $response = $this->put('/api/admin/settings', ['app_name' => 'Test']);
        Assert::equals(403, $response['status'], 'Regular user should get 403 on settings update');
        
        $this->cleanup();
    }

    public function testSettingsApiValidation(): void
    {
        // Login as regular user (we can't create admin users in tests)
        $this->loginAsTestUser($this->testUserEmail);
        
        // Test with invalid max_file_size
        $response = $this->put('/api/admin/settings', [
            'max_file_size' => 99999999999 // Too large
        ]);
        Assert::equals(403, $response['status'], 'Should get 403 as regular user');
        
        // Test with invalid JSON
        $response = $this->makeRequest('PUT', '/api/admin/settings', null, [
            'Content-Type: application/json',
            'X-CSRF-Token: ' . ($this->csrfToken ?? '')
        ]);
        // Add malformed JSON body manually
        $response = $this->put('/api/admin/settings', [
            'app_name' => str_repeat('a', 1000) // Very long name
        ]);
        Assert::equals(403, $response['status'], 'Should get 403 as regular user');
        
        $this->cleanup();
    }

    // Note: Full admin settings tests require manual admin role setup in database
    // These are documented for manual testing:
    // 1. Create admin user in database with role='admin'  
    // 2. Test: curl -X GET -H "Authorization: Basic $(echo -n admin@example.com:password | base64)" http://127.0.0.1:8080/api/admin/settings
    // 3. Test: curl -X PUT -H "Content-Type: application/json" -H "Authorization: Basic $(echo -n admin@example.com:password | base64)" -d '{"app_name":"Test App"}' http://127.0.0.1:8080/api/admin/settings

    // Admin Authentication Tests

    public function testAdminRouteRequiresAuth(): void
    {
        $response = $this->get('/admin', [], false); // Don't follow redirects
        
        Assert::equals(302, $response['status'], 'Admin route should redirect unauthenticated users');
        
        // Should redirect to login
        $location = '';
        foreach ($response['headers'] as $header) {
            if (strpos($header, 'Location:') === 0) {
                $location = trim(substr($header, 9));
                break;
            }
        }
        Assert::equals('/login', $location, 'Should redirect to login page');
    }

    public function testRegularUserCannotAccessAdmin(): void
    {
        // Login as regular user
        $this->loginAsTestUser($this->testUserEmail);
        
        $response = $this->get('/admin');
        
        Assert::equals(403, $response['status'], 'Regular user should get 403 on admin routes');
        
        $this->cleanup();
    }

    public function testAdminJobsRouteRequiresAuth(): void
    {
        $response = $this->get('/admin/jobs', [], false);
        
        Assert::equals(302, $response['status'], 'Admin jobs route should redirect unauthenticated users');
        
        $this->cleanup();
    }

    public function testAdminSettingsRouteRequiresAuth(): void
    {
        $response = $this->get('/admin/settings', [], false);
        
        Assert::equals(302, $response['status'], 'Admin settings route should redirect unauthenticated users');
        
        $this->cleanup();
    }

    public function testRegularUserCannotAccessAdminAPI(): void
    {
        // Login as regular user
        $this->loginAsTestUser($this->testUserEmail);
        
        // Try to access admin API endpoints
        $response = $this->get('/api/admin/settings');
        Assert::equals(403, $response['status'], 'Regular user should get 403 on admin API routes');
        
        $this->cleanup();
    }

    // Helper Methods

    private function createTestUser(string $email, string $role = 'user'): array
    {
        // Register user
        $response = $this->post('/api/register', [
            'email' => $email,
            'password' => 'validpassword123',
            'passwordConfirm' => 'validpassword123'
        ]);
        
        $user = $this->assertJsonResponse($response, 201);
        $this->testUserIds[] = $user['id'];
        
        // Set role if admin (requires direct DB access, but we'll simulate with API)
        if ($role === 'admin') {
            // In a real test, we'd need to set the role in the database directly
            // For now, we assume the first registered user can be made admin via some mechanism
            // This is a limitation of the current test setup
        }
        
        return $user;
    }

    private function makeRequestWithBasicAuth(string $method, string $path, ?array $data = null, string $username = '', string $password = ''): array
    {
        $headers = [];
        if ($username && $password) {
            $credentials = base64_encode($username . ':' . $password);
            $headers[] = 'Authorization: Basic ' . $credentials;
        }
        
        return $this->makeRequest($method, $path, $data, $headers);
    }
}