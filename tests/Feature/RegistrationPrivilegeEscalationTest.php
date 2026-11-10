<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Modules\Users\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class RegistrationPrivilegeEscalationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $legitimateAdmin;
    protected string $adminToken;
    protected User $customer;
    protected string $customerToken;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create a trusted legitimate admin account
        $this->legitimateAdmin = User::create([
            'name'     => 'Legit Admin',
            'email'    => 'legit_admin_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role'     => User::ROLE_ADMIN,
        ]);
        $this->adminToken = auth('api')->tokenById($this->legitimateAdmin->id);

        // 2. Create a legitimate existing customer
        $this->customer = User::create([
            'name'     => 'Legit Customer',
            'email'    => 'legit_cust_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role'     => User::ROLE_USER,
        ]);
        $this->customerToken = auth('api')->tokenById($this->customer->id);

        auth()->forgetGuards();
    }

    /**
     * Requirement 1: Normal registration creates a regular user.
     */
    public function test_normal_registration_creates_regular_user(): void
    {
        $email = 'normal_user_' . uniqid() . '@example.com';

        $response = $this->postJson('/api/v1/auth/register', [
            'name'     => 'Normal User',
            'email'    => $email,
            'password' => 'secret1234',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'User registered successfully.',
        ]);

        $response->assertJsonPath('data.user.role', User::ROLE_USER);
        $this->assertNotEmpty($response->json('data.token'));

        // Verify persisted role in database
        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_USER, $user->role);
    }

    /**
     * Requirement 2: Registration with role=admin cannot create an admin.
     */
    public function test_registration_with_role_admin_cannot_create_admin(): void
    {
        $email = 'hacker_admin_' . uniqid() . '@example.com';

        $response = $this->postJson('/api/v1/auth/register', [
            'name'     => 'Privilege Escalation Attempt',
            'email'    => $email,
            'password' => 'secret1234',
            'role'     => 'admin',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.user.role', User::ROLE_USER);

        // Verify persisted record in the database is NOT admin
        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_USER, $user->role);
        $this->assertNotSame(User::ROLE_ADMIN, $user->role);
    }

    /**
     * Requirement 3: Registration with role=seller cannot create a seller.
     */
    public function test_registration_with_role_seller_cannot_create_seller(): void
    {
        $email = 'hacker_seller_' . uniqid() . '@example.com';

        $response = $this->postJson('/api/v1/auth/register', [
            'name'     => 'Seller Escalation Attempt',
            'email'    => $email,
            'password' => 'secret1234',
            'role'     => 'seller',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.user.role', User::ROLE_USER);

        // Verify persisted record in the database is NOT seller
        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_USER, $user->role);
        $this->assertNotSame(User::ROLE_SELLER, $user->role);
    }

    /**
     * Requirement 4: The resulting JWT does not provide administrative privileges.
     */
    public function test_resulting_jwt_does_not_provide_administrative_privileges(): void
    {
        $email = 'exploit_jwt_' . uniqid() . '@example.com';

        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name'     => 'Attacker Trying Admin',
            'email'    => $email,
            'password' => 'password123',
            'role'     => 'admin',
        ]);

        $token = $registerResponse->json('data.token');
        $this->assertNotEmpty($token);

        // Verify JWT claims: role claim MUST be 'user', not 'admin'
        $payload = JWTAuth::setToken($token)->getPayload();
        $this->assertSame(User::ROLE_USER, $payload->get('role'));
        $this->assertNotSame(User::ROLE_ADMIN, $payload->get('role'));

        // Attempt admin-restricted action: Creating a product
        $productResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/products', [
                'name'        => 'Unauthorized Product Creation',
                'price'       => 99,
                'category_id' => 1,
            ]);

        $productResponse->assertStatus(403);
    }

    /**
     * Requirement 5: A newly registered user cannot change another user's order status.
     */
    public function test_newly_registered_user_cannot_change_another_user_order_status(): void
    {
        // Pre-existing order belonging to customer
        $order = Order::create([
            'user_id' => $this->customer->id,
            'name'    => 'Legit Customer',
            'email'   => $this->customer->email,
            'address' => 'Customer Street 10',
            'phone'   => '0555555555',
            'note'    => 'Test order',
            'total'   => 200,
            'status'  => 'pending',
        ]);

        // Attacker registers submitting role=admin
        $email = 'attacker_order_' . uniqid() . '@example.com';
        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name'     => 'Attacker',
            'email'    => $email,
            'password' => 'password123',
            'role'     => 'admin',
        ]);

        $attackerToken = $registerResponse->json('data.token');

        // Attacker attempts to update the order's status to 'completed'
        $patchResponse = $this->withHeader('Authorization', 'Bearer ' . $attackerToken)
            ->patchJson("/api/v1/orders/{$order->id}/status", [
                'status' => 'completed',
            ]);

        $patchResponse->assertStatus(403);

        // Verify order status in database was NOT modified
        $order->refresh();
        $this->assertSame('pending', $order->status);
        $this->assertNotSame('completed', $order->status);
    }

    /**
     * Requirement 6: Existing legitimate admin accounts can still change order status.
     */
    public function test_existing_legitimate_admin_accounts_can_change_order_status(): void
    {
        $order = Order::create([
            'user_id' => $this->customer->id,
            'name'    => 'Legit Customer',
            'email'   => $this->customer->email,
            'address' => 'Customer Street 10',
            'phone'   => '0555555555',
            'note'    => 'Test order',
            'total'   => 200,
            'status'  => 'pending',
        ]);

        // Legitimate admin updates status
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->patchJson("/api/v1/orders/{$order->id}/status", [
                'status' => 'completed',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'completed');

        // Verify status in database
        $order->refresh();
        $this->assertSame('completed', $order->status);
    }

    /**
     * Requirement 7: Existing customers can still create and access their own orders.
     */
    public function test_existing_customers_can_still_create_and_access_own_orders(): void
    {
        // 1. Customer creates an order
        $order = Order::create([
            'user_id' => $this->customer->id,
            'name'    => 'Customer Own Order',
            'email'   => $this->customer->email,
            'address' => 'Customer Street 12',
            'phone'   => '0555555555',
            'note'    => 'My Order Note',
            'total'   => 120,
            'status'  => 'pending',
        ]);

        // 2. Customer accesses own order -> 200 OK
        $viewOwnResponse = $this->withHeader('Authorization', 'Bearer ' . $this->customerToken)
            ->getJson("/api/v1/orders/{$order->id}");

        $viewOwnResponse->assertStatus(200);
        $viewOwnResponse->assertJsonPath('data.id', $order->id);

        // 3. Customer cannot access another user's order
        $otherCustomer = User::create([
            'name'     => 'Other Customer',
            'email'    => 'other_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role'     => User::ROLE_USER,
        ]);
        $otherOrder = Order::create([
            'user_id' => $otherCustomer->id,
            'name'    => 'Other Customer Order',
            'email'   => $otherCustomer->email,
            'address' => 'Other Street 99',
            'phone'   => '0999999999',
            'note'    => 'Private order',
            'total'   => 500,
            'status'  => 'pending',
        ]);

        $viewOtherResponse = $this->withHeader('Authorization', 'Bearer ' . $this->customerToken)
            ->getJson("/api/v1/orders/{$otherOrder->id}");

        $viewOtherResponse->assertStatus(403);
    }

    /**
     * Defense-in-depth: Ensure UserService::register forces User::ROLE_USER even if passed arbitrary array.
     */
    public function test_user_service_register_forces_customer_role_server_side(): void
    {
        $userService = app(UserService::class);

        $result = $userService->register([
            'name'     => 'Service Exploit',
            'email'    => 'service_exploit_' . uniqid() . '@example.com',
            'password' => 'secret123',
            'role'     => 'admin', // Injected into service call directly
        ]);

        $this->assertSame(User::ROLE_USER, $result['user']['role']);

        $user = User::find($result['user']['id']);
        $this->assertSame(User::ROLE_USER, $user->role);
    }

    /**
     * Administrative provisioning remains a separate, dedicated trusted operation.
     */
    public function test_administrative_account_provisioning_is_separate_trusted_operation(): void
    {
        $userService = app(UserService::class);

        $admin = $userService->createPrivilegedUser([
            'name'     => 'Internal Admin',
            'email'    => 'internal_admin_' . uniqid() . '@example.com',
            'password' => 'secret123',
        ], User::ROLE_ADMIN);

        $this->assertSame(User::ROLE_ADMIN, $admin->role);

        $seller = $userService->createPrivilegedUser([
            'name'     => 'Internal Seller',
            'email'    => 'internal_seller_' . uniqid() . '@example.com',
            'password' => 'secret123',
        ], User::ROLE_SELLER);

        $this->assertSame(User::ROLE_SELLER, $seller->role);
    }
}
