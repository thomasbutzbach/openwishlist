<?php
declare(strict_types=1);

require_once __DIR__ . '/ApiTestBase.php';

final class AuthApiTest extends ApiTestBase
{
    private string $testEmail;

    public function __construct()
    {
        // Use unique email for each test run to avoid conflicts
        $this->testEmail = 'test' . time() . '@example.com';
    }

    public function testRegisterSuccess(): void
    {
        $response = $this->post('/api/register', [
            'email' => $this->testEmail,
            'password' => 'validpassword123',
            'passwordConfirm' => 'validpassword123'
        ]);
        
        $json = $this->assertJsonResponse($response, 201);
        
        Assert::arrayHasKey('id', $json, 'Register response should contain user ID');
        Assert::arrayHasKey('email', $json, 'Register response should contain email');
        Assert::equals($this->testEmail, $json['email'], 'Email should match');
        Assert::true($json['id'] > 0, 'User ID should be positive');
        
        $this->cleanup();
    }

    public function testRegisterInvalidEmail(): void
    {
        $response = $this->post('/api/register', [
            'email' => 'invalid-email',
            'password' => 'validpassword123',
            'passwordConfirm' => 'validpassword123'
        ]);
        
        $json = $this->assertErrorResponse($response, 400, 'Validation Error');
        Assert::contains('valid email', $json['detail'], 'Should mention email validation');
    }

    public function testRegisterShortPassword(): void
    {
        $response = $this->post('/api/register', [
            'email' => 'test@example.com',
            'password' => 'short',
            'passwordConfirm' => 'short'
        ]);
        
        $json = $this->assertErrorResponse($response, 400, 'Validation Error');
        Assert::contains('10 characters', $json['detail'], 'Should mention password length');
    }

    public function testRegisterPasswordMismatch(): void
    {
        $response = $this->post('/api/register', [
            'email' => 'test@example.com',
            'password' => 'validpassword123',
            'passwordConfirm' => 'differentpassword123'
        ]);
        
        $json = $this->assertErrorResponse($response, 400, 'Validation Error');
        Assert::contains('do not match', $json['detail'], 'Should mention password mismatch');
    }

    public function testRegisterDuplicateUser(): void
    {
        // Register first user
        $this->post('/api/register', [
            'email' => $this->testEmail,
            'password' => 'validpassword123',
            'passwordConfirm' => 'validpassword123'
        ]);
        
        // Try to register same email again
        $response = $this->post('/api/register', [
            'email' => $this->testEmail,
            'password' => 'validpassword123',
            'passwordConfirm' => 'validpassword123'
        ]);
        
        $json = $this->assertErrorResponse($response, 409, 'Conflict');
        Assert::contains('already exists', $json['detail'], 'Should mention user exists');
        
        $this->cleanup();
    }

    public function testLoginSuccess(): void
    {
        // Register user first
        $this->post('/api/register', [
            'email' => $this->testEmail,
            'password' => 'validpassword123',
            'passwordConfirm' => 'validpassword123'
        ]);
        
        // Logout to test fresh login
        $this->post('/api/logout');
        $this->sessionCookie = null;
        
        // Now login
        $response = $this->post('/api/login', [
            'email' => $this->testEmail,
            'password' => 'validpassword123'
        ]);
        
        $json = $this->assertJsonResponse($response, 200);
        Assert::arrayHasKey('id', $json, 'Login response should contain user ID');
        Assert::arrayHasKey('email', $json, 'Login response should contain email');
        Assert::equals($this->testEmail, $json['email'], 'Email should match');
        
        $this->cleanup();
    }

    public function testLoginInvalidCredentials(): void
    {
        $response = $this->post('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword'
        ]);
        
        $json = $this->assertErrorResponse($response, 401, 'Unauthorized');
        Assert::contains('Invalid credentials', $json['detail'], 'Should mention invalid credentials');
    }

    public function testLoginMissingFields(): void
    {
        $response = $this->post('/api/login', [
            'email' => 'test@example.com'
            // missing password
        ]);
        
        $json = $this->assertErrorResponse($response, 400, 'Validation Error');
        Assert::contains('required', $json['detail'], 'Should mention required fields');
    }

    public function testLogoutSuccess(): void
    {
        // Register and login
        $this->post('/api/register', [
            'email' => $this->testEmail,
            'password' => 'validpassword123',
            'passwordConfirm' => 'validpassword123'
        ]);
        
        // Logout (should work even if already logged in from registration)
        $response = $this->post('/api/logout');
        
        $json = $this->assertJsonResponse($response, 200);
        Assert::arrayHasKey('message', $json, 'Logout response should contain message');
        Assert::contains('successfully', $json['message'], 'Should confirm logout');
    }

    public function testAuthenticationRequired(): void
    {
        // Try to access protected endpoint without authentication
        $response = $this->get('/api/wishlists');
        
        $json = $this->assertErrorResponse($response, 401, 'Unauthorized');
        Assert::contains('Authentication required', $json['detail'], 'Should mention auth requirement');
    }
}