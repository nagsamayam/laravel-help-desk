<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('auth-register:127.0.0.1');
    RateLimiter::clear('auth-login:127.0.0.1');
    RateLimiter::clear('auth-refresh:127.0.0.1');
});

test('user can register successfully', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@gmail.com',
        'password' => 'Welcome123@',
        'password_confirmation' => 'Welcome123@',
    ]);

    $response->assertCreated()
        ->assertJsonPath('token_type', 'bearer')
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'john@gmail.com',
    ]);
});

test('registered user default role should be customer', function () {
    $this->postJson('/api/v1/auth/register', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@gmail.com',
        'password' => 'Welcome123@',
        'password_confirmation' => 'Welcome123@',
    ]);

    $this->assertDatabaseHas('users', [
        'role' => Role::Customer,
    ]);
});

test('user can login with valid credentials', function () {
    $user = User::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@gmail.com',
        'password' => 'Welcome123@',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Welcome123@',
    ]);

    $response->assertOk();
});

test('authenticated user can view profile with /me endpoint', function () {
    $user = User::factory()->create();

    $token = auth('api')->login($user);

    $response = $this->withToken($token)->getJson('/api/v1/auth/me');

    $response->assertOk();
});

test('authenticated user can logout', function () {
    $user = User::factory()->create();

    $token = auth('api')->login($user);

    $response = $this
        ->withToken($token)
        ->postJson('/api/v1/auth/logout');

    $response
        ->assertOk()
        ->assertJson([
            'message' => 'Successfully logged out',
        ]);

    $this
        ->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

test('authenticated user can refresh token', function () {
    $user = User::factory()->create();

    $token = auth('api')->login($user);

    $response = $this
        ->withToken($token)
        ->postJson('/api/v1/auth/refresh');

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
        ]);
});
