<?php

use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;

// uses(RefreshDatabase::class);
// uses(TestCase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user    = User::factory()->create([
        'username'   => 'testuser',
        'password'   => bcrypt('password123'),
        'company_id' => $this->company->id,
    ]);
});

// =============================
// LOGIN
// =============================

it('can login with valid credentials', function () {
    $response = $this->postJson('/api/v1/login', [
        'phone' => '081234567890',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user',
                'token',
                'token_type',
            ]
        ])
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer');
});

it('returns 422 with invalid credentials', function () {
    $response = $this->postJson('/api/v1/login', [
        'username' => 'testuser',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure([
            'errors' => ['username']
        ]);
});

it('returns 422 when username not found', function () {
    $response = $this->postJson('/api/v1/login', [
        'username' => 'tidakada',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 422 when username is empty', function () {
    $response = $this->postJson('/api/v1/login', [
        'username' => '',
        'password' => 'password123',
    ]);

    $response->assertStatus(422);
});

it('returns 422 when password is empty', function () {
    $response = $this->postJson('/api/v1/login', [
        'username' => 'testuser',
        'password' => '',
    ]);

    $response->assertStatus(422);
});

it('returns 422 when both fields are empty', function () {
    $response = $this->postJson('/api/v1/login', []);

    $response->assertStatus(422);
});

it('replaces existing token for same device on login', function () {
    // Login pertama
    $this->postJson('/api/v1/login', [
        'username' => 'testuser',
        'password' => 'password123',
    ], ['User-Agent' => 'TestDevice']);

    // Login kedua dengan device yang sama
    $this->postJson('/api/v1/login', [
        'username' => 'testuser',
        'password' => 'password123',
    ], ['User-Agent' => 'TestDevice']);

    // Harus tetap hanya 1 token untuk device ini
    expect($this->user->tokens()->where('name', 'TestDevice')->count())->toBe(1);
});

// =============================
// LOGOUT
// =============================

it('can logout when authenticated', function () {
    $token = $this->user->createToken('TestDevice')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/logout');

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    // Token harus sudah terhapus
    expect($this->user->tokens()->count())->toBe(0);
});

it('returns 401 when logout without token', function () {
    $response = $this->postJson('/api/v1/logout');

    $response->assertStatus(401);
});

// =============================
// RESET PASSWORD (sudah login)
// =============================

it('can reset password when authenticated', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/reset-password', [
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    // Password harus berubah
    expect(Hash::check('newpassword123', $this->user->fresh()->password))->toBeTrue();
});

it('other device tokens are deleted after reset password', function () {
    // Buat token device lain
    $this->user->createToken('device-lain');
    $this->user->createToken('device-lain-2');

    expect($this->user->tokens()->count())->toBe(2);

    $currentToken = $this->user->createToken('current-device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$currentToken}")
        ->postJson('/api/v1/reset-password', [
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
        ->assertStatus(200);

    // Hanya token current yang tersisa
    expect($this->user->tokens()->count())->toBe(1);
});

it('returns 422 when password confirmation does not match on reset', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/reset-password', [
            'password'              => 'newpassword123',
            'password_confirmation' => 'berbeda123',
        ])
        ->assertStatus(422);
});

it('returns 422 when password is too short on reset', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/reset-password', [
            'password'              => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertStatus(422);
});

it('returns 401 when not authenticated on reset password', function () {
    $this->postJson('/api/v1/reset-password', [
        'password'              => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertStatus(401);
});

// =============================
// FORGOT PASSWORD — STEP 1: VERIFY
// =============================

it('can verify username for forgot password', function () {
    $this->postJson('/api/v1/forgot-password/verify', [
        'username' => 'testuser',
    ])
    ->assertStatus(200)
    ->assertJsonPath('success', true)
    ->assertJsonStructure([
        'data' => ['reset_token', 'expires_in']
    ]);
});

it('returns 422 when username not found on verify', function () {
    $this->postJson('/api/v1/forgot-password/verify', [
        'username' => 'tidakada',
    ])->assertStatus(422);
});

it('returns 422 when username is empty on verify', function () {
    $this->postJson('/api/v1/forgot-password/verify', [
        'username' => '',
    ])->assertStatus(422);
});

it('old reset token is deleted when verify is called again', function () {
    // Verify pertama
    $this->postJson('/api/v1/forgot-password/verify', ['username' => 'testuser']);

    expect($this->user->tokens()->where('name', 'password-reset')->count())->toBe(1);

    // Verify kedua — token lama harus dihapus, dibuat baru
    $this->postJson('/api/v1/forgot-password/verify', ['username' => 'testuser']);

    expect($this->user->tokens()->where('name', 'password-reset')->count())->toBe(1);
});

// =============================
// FORGOT PASSWORD — STEP 2: RESET
// =============================

it('can reset password with valid reset token', function () {
    $response = $this->postJson('/api/v1/forgot-password/verify', [
        'username' => 'testuser',
    ]);

    $resetToken = $response->json('data.reset_token');

    $this->withHeader('Authorization', "Bearer {$resetToken}")
        ->postJson('/api/v1/forgot-password/reset', [
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect(Hash::check('newpassword123', $this->user->fresh()->password))->toBeTrue();
});

it('all tokens are deleted after forgot password reset', function () {
    // Login di beberapa device
    $this->user->createToken('device-1');
    $this->user->createToken('device-2');

    $response   = $this->postJson('/api/v1/forgot-password/verify', ['username' => 'testuser']);
    $resetToken = $response->json('data.reset_token');

    $this->withHeader('Authorization', "Bearer {$resetToken}")
        ->postJson('/api/v1/forgot-password/reset', [
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    // Semua token harus dihapus — user harus login ulang
    expect($this->user->tokens()->count())->toBe(0);
});

it('returns 403 when using regular login token for forgot password reset', function () {
    // Token login biasa tidak punya ability password:reset
    $loginToken = $this->user->createToken('login-device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$loginToken}")
        ->postJson('/api/v1/forgot-password/reset', [
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
        ->assertStatus(403);
});

it('returns 422 when password confirmation does not match on forgot reset', function () {
    $response   = $this->postJson('/api/v1/forgot-password/verify', ['username' => 'testuser']);
    $resetToken = $response->json('data.reset_token');

    $this->withHeader('Authorization', "Bearer {$resetToken}")
        ->postJson('/api/v1/forgot-password/reset', [
            'password'              => 'newpassword123',
            'password_confirmation' => 'berbeda123',
        ])
        ->assertStatus(422);
});

it('returns 401 when no token provided for forgot password reset', function () {
    $this->postJson('/api/v1/forgot-password/reset', [
        'password'              => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertStatus(401);
});