<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SecondCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiSecurityAndPermissionsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $customerA;
    protected User $customerB;
    protected string $adminToken;
    protected string $customerAToken;
    protected string $customerBToken;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login');
        RateLimiter::clear('api');

        // Create Admin
        $this->admin = User::create([
            'name'     => 'Admin Tester',
            'email'    => 'admin_test_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role'     => 'admin',
        ]);
        $this->adminToken = auth('api')->tokenById($this->admin->id);

        // Create Customer A
        $this->customerA = User::create([
            'name'     => 'Customer A',
            'email'    => 'customer_a_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role'     => 'user',
        ]);
        $this->customerAToken = auth('api')->tokenById($this->customerA->id);

        // Create Customer B
        $this->customerB = User::create([
            'name'     => 'Customer B',
            'email'    => 'customer_b_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role'     => 'user',
        ]);
        $this->customerBToken = auth('api')->tokenById($this->customerB->id);

        auth()->forgetGuards();
    }

    /**
     * 1. Proof of Security Headers
     */
    public function test_security_headers_are_present_on_api_responses(): void
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Strict-Transport-Security');
        $response->assertHeader('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
    }

    /**
     * 2. Proof of Rate Limiting (Throttle on login - exactly 5 allowed, 6th rejected with 429)
     */
    public function test_login_rate_limiting_throttles_after_repeated_attempts(): void
    {
        // Attempts 1 to 5: must be processed (401 because credentials are invalid)
        for ($i = 1; $i <= 5; $i++) {
            $res = $this->postJson('/api/v1/auth/login', [
                'email'    => 'wrong@example.com',
                'password' => 'wrongpass',
            ]);
            $res->assertStatus(401);
        }

        // Attempt 6: MUST be throttled and rejected with 429
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'wrong@example.com',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(429);
        $response->assertJson([
            'success' => false,
            'message' => 'Too many requests. Please slow down.',
        ]);
    }

    /**
     * 3. Proof of Unauthorized (401) on protected routes without JWT
     */
    public function test_unauthenticated_request_is_rejected_with_401(): void
    {
        $response = $this->getJson('/api/v1/orders');

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthenticated or invalid token.',
        ]);
    }

    /**
     * 4. Proof: Customer CANNOT view another customer's order (403 Forbidden)
     */
    public function test_customer_cannot_view_another_customer_order(): void
    {
        // Order belongs to Customer B
        $orderB = Order::create([
            'user_id' => $this->customerB->id,
            'name'    => 'Customer B',
            'email'   => $this->customerB->email,
            'address' => 'Customer B Address',
            'phone'   => '0123456789',
            'note'    => 'Secret note',
            'total'   => 150,
            'status'  => '0',
        ]);

        // Customer A attempts to view Customer B's order
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->customerAToken)
            ->getJson("/api/v1/orders/{$orderB->id}");

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
        ]);
    }

    /**
     * 5. Proof: Customer CAN view their own order (200 OK)
     */
    public function test_customer_can_view_own_order(): void
    {
        $orderA = Order::create([
            'user_id' => $this->customerA->id,
            'name'    => 'Customer A',
            'email'   => $this->customerA->email,
            'address' => 'Customer A Address',
            'phone'   => '0123456789',
            'note'    => 'My note',
            'total'   => 100,
            'status'  => '0',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->customerAToken)
            ->getJson("/api/v1/orders/{$orderA->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $orderA->id);
    }

    /**
     * 6. Proof: Customer CANNOT update order status (403 Forbidden)
     */
    public function test_customer_cannot_change_order_status(): void
    {
        $orderA = Order::create([
            'user_id' => $this->customerA->id,
            'name'    => 'Customer A',
            'email'   => $this->customerA->email,
            'address' => 'Customer A Address',
            'phone'   => '0123456789',
            'note'    => 'My note',
            'total'   => 100,
            'status'  => '0',
        ]);

        // Customer A attempts to patch status
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->customerAToken)
            ->patchJson("/api/v1/orders/{$orderA->id}/status", [
                'status' => 'completed',
            ]);

        $response->assertStatus(403);
    }

    /**
     * 7. Proof: Admin CAN update order status (200 OK)
     */
    public function test_admin_can_update_order_status(): void
    {
        $orderA = Order::create([
            'user_id' => $this->customerA->id,
            'name'    => 'Customer A',
            'email'   => $this->customerA->email,
            'address' => 'Customer A Address',
            'phone'   => '0123456789',
            'note'    => 'My note',
            'total'   => 100,
            'status'  => '0',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->patchJson("/api/v1/orders/{$orderA->id}/status", [
                'status' => 'completed',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'completed');
    }

    /**
     * 8. Proof: Regular customer CANNOT create a product (403 Forbidden)
     */
    public function test_customer_cannot_create_product(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->customerAToken)
            ->postJson('/api/v1/products', [
                'name'        => 'Unauthorized Product',
                'price'       => 50,
                'category_id' => 1,
            ]);

        $response->assertStatus(403);
    }
}
