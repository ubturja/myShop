<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'CUSTOMER',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'access_token',
                'token_type',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'customer@test.com',
            'role' => 'CUSTOMER',
        ]);
    }

    public function test_seller_can_register_with_business_name(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test Seller',
            'email' => 'seller@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'SELLER',
            'business_name' => 'Test Store',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'seller@test.com',
            'role' => 'SELLER',
        ]);

        $user = User::where('email', 'seller@test.com')->first();
        $this->assertNotNull($user->seller);
        $this->assertEquals('Test Store', $user->seller->store_name);
    }

    public function test_login_returns_token_and_user(): void
    {
        $user = User::factory()->create([
            'email' => 'test@test.com',
            'password' => bcrypt('password123'),
            'role' => 'CUSTOMER',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'email', 'role'],
                'access_token',
                'token_type',
            ]);
    }

    public function test_login_with_wrong_role_returns_403(): void
    {
        $user = User::factory()->create([
            'email' => 'seller@test.com',
            'password' => bcrypt('password123'),
            'role' => 'SELLER',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'seller@test.com',
            'password' => 'password123',
            'role' => 'ADMIN', // Try to login as admin but user is seller
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Role mismatch',
            ]);
    }

    public function test_login_with_correct_role_succeeds(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role' => 'ADMIN',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
            'role' => 'ADMIN',
        ]);

        $response->assertStatus(200);
    }

    public function test_login_without_role_succeeds(): void
    {
        $user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => bcrypt('password123'),
            'role' => 'CUSTOMER',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
    }

    public function test_invalid_credentials_return_422(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(0, $user->tokens()->count());
    }
}

