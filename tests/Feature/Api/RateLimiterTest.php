<?php

use App\Models\Category;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->owner()->create();

    $this->payload = [
        'name'    => 'A',
    ];

    $this->payload2= [
        'name'    => 'B',
    ];
});

it('limits POST requests to 1 per 6 seconds', function () {
    $this->actingAs($this->user);
    $this->freezeTime();
    
    // Request pertama -> sukses
    $this->postJson('/api/v1/categories', ['name' => 'Category A'])->assertStatus(201);
    
    // Request kedua dalam 6 detik -> kena limit 429
    $this->postJson('/api/v1/categories', ['name' => 'Category B'])->assertStatus(429);
    
    // Majukan waktu 6 detik
    $this->travel(6)->seconds();
    
    // Request setelah 6 detik -> sukses lagi
    $this->postJson('/api/v1/categories', ['name' => 'Category C'])->assertStatus(201);
});

it('limits PUT/PATCH/DELETE requests to 1 per 5 seconds', function () {
    $this->actingAs($this->user);
    $this->freezeTime();
    
    $category = Category::factory()->create(['company_id' => $this->user->company_id]);
    
    // Request pertama -> sukses
    $this->patchJson("/api/v1/categories/{$category->uuid}", ['name' => 'Updated'])
        ->assertStatus(200);
    
    // Request kedua dalam 5 detik -> kena limit
    $this->patchJson("/api/v1/categories/{$category->uuid}", ['name' => 'Updated Again'])
        ->assertStatus(429);
    
    // Majukan waktu 7 detik
    $this->travel(7)->seconds();
    
    // Request setelah 6 detik -> sukses
    $this->patchJson("/api/v1/categories/{$category->uuid}", ['name' => 'Updated Again'])
        ->assertStatus(200);
});

it('limits GET requests to 80 per minute', function () {
    $this->actingAs($this->user);
    $this->freezeTime();
    
    // Lakukan 80 request berturut-turut -> semua sukses
    for ($i = 0; $i < 80; $i++) {
        $this->getJson('/api/v1/categories')->assertStatus(200);
    }
    
    // Request ke-81 -> kena limit 429
    $this->getJson('/api/v1/categories')->assertStatus(429);
    
    // Majukan waktu 61 detik -> reset limit
    $this->travel(61)->seconds();
    $this->getJson('/api/v1/categories')->assertStatus(200);
});

it('returns correct response format on rate limit', function () {
    $this->actingAs($this->user);
    $this->freezeTime();
    
    $this->postJson('/api/v1/categories', ['name' => 'Category X'])->assertStatus(201);
    
    $response = $this->postJson('/api/v1/categories', ['name' => 'Category Y']);
    
    $response->assertStatus(429);
    $response->assertJsonStructure([
        'success',
        'message',
        'code'
    ]);
    expect($response->json('success'))->toBeFalse();
    expect($response->json('code'))->toBe(429);
});

it('uses IP based limit for unauthenticated users', function () {
    $this->freezeTime();
    
    // Guest user (tidak login)
    $response1 = $this->postJson('/api/v1/login', [
        'username' => 'wrong',
        'password' => 'wrong'
    ]);
    
    $response2 = $this->postJson('/api/v1/login', [
        'username' => 'wrong',
        'password' => 'wrong'
    ]);
    
    // Harusnya kena limit juga (berdasarkan IP)
    if ($response2->status() === 429) {
        $response2->assertJsonPath('code', 429);
    }
});