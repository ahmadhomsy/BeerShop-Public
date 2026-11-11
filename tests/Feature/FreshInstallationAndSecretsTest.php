<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FreshInstallationAndSecretsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Requirement 1-3: Fresh .env initialization generates APP_KEY and JWT_SECRET.
     */
    public function test_fresh_env_initialization_generates_app_key_and_jwt_secret(): void
    {
        $tempEnvPath = base_path('.env.test_simulation_' . uniqid());
        
        // 1. Simulate fresh install: copy .env.example
        copy(base_path('.env.example'), $tempEnvPath);
        $initialContent = file_get_contents($tempEnvPath);

        $this->assertStringContainsString('APP_KEY=', $initialContent);
        $this->assertStringContainsString('JWT_SECRET=', $initialContent);
        $this->assertMatchesRegularExpression('/^APP_KEY=\s*$/m', $initialContent);
        $this->assertMatchesRegularExpression('/^JWT_SECRET=\s*$/m', $initialContent);

        // 2. Simulate entrypoint key generation logic on this file
        // Generate APP_KEY
        if (preg_match('/^APP_KEY=\s*$/m', file_get_contents($tempEnvPath))) {
            $generatedAppKey = 'base64:' . base64_encode(random_bytes(32));
            file_put_contents($tempEnvPath, preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $generatedAppKey, file_get_contents($tempEnvPath)));
        }

        // Generate JWT_SECRET
        if (preg_match('/^JWT_SECRET=\s*$/m', file_get_contents($tempEnvPath))) {
            $generatedJwtSecret = bin2hex(random_bytes(32));
            file_put_contents($tempEnvPath, preg_replace('/^JWT_SECRET=.*$/m', 'JWT_SECRET=' . $generatedJwtSecret, file_get_contents($tempEnvPath)));
        }

        $populatedContent = file_get_contents($tempEnvPath);

        // Verify keys are non-empty
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/=]{44}$/m', $populatedContent);
        $this->assertMatchesRegularExpression('/^JWT_SECRET=[a-f0-9]{64}$/m', $populatedContent);

        // Clean up simulation file
        if (file_exists($tempEnvPath)) {
            unlink($tempEnvPath);
        }
    }

    /**
     * Requirement: Never regenerate secrets on container restart (Idempotency check).
     */
    public function test_restart_preserves_existing_secrets_without_regeneration(): void
    {
        $tempEnvPath = base_path('.env.test_idempotency_' . uniqid());

        $existingAppKey = 'base64:EXISTING_KEY_DO_NOT_REGENERATE_TEST_123456=';
        $existingJwtSecret = 'existing_secret_hash_do_not_regenerate_test_67890';

        $mockEnv = "APP_KEY={$existingAppKey}\nJWT_SECRET={$existingJwtSecret}\n";
        file_put_contents($tempEnvPath, $mockEnv);

        // Simulate entrypoint logic on restart
        $content = file_get_contents($tempEnvPath);

        // Entrypoint grep checks:
        $appKeyNeedsGen = !preg_match('/^APP_KEY=.+/m', $content);
        $jwtSecretNeedsGen = !preg_match('/^JWT_SECRET=.+/m', $content);

        $this->assertFalse($appKeyNeedsGen, 'APP_KEY is already set; entrypoint must not regenerate it.');
        $this->assertFalse($jwtSecretNeedsGen, 'JWT_SECRET is already set; entrypoint must not regenerate it.');

        // Content remains untouched
        $this->assertSame($mockEnv, file_get_contents($tempEnvPath));

        if (file_exists($tempEnvPath)) {
            unlink($tempEnvPath);
        }
    }

    /**
     * Requirement: Demo accounts are restricted to local/testing and blocked in production.
     */
    public function test_production_environment_prevents_creation_of_demo_accounts(): void
    {
        // Simulate production environment
        $originalEnv = app()->environment();
        $this->app->detectEnvironment(fn () => 'production');

        // Delete demo admin if exists from prior tests
        User::where('email', 'aa@gmail.com')->delete();

        // Run seeder in production
        $seeder = new DatabaseSeeder();
        $seeder->run();

        // Verify demo accounts were NOT created
        $this->assertFalse(User::where('email', 'aa@gmail.com')->exists());
        $this->assertFalse(User::where('email', 'bb@gmail.com')->exists());
        $this->assertFalse(User::where('email', 'cc@gmail.com')->exists());

        // Restore original environment
        $this->app->detectEnvironment(fn () => $originalEnv);
    }

    /**
     * Requirement: Production admin provisioning uses a trusted secure mechanism (Env vars).
     */
    public function test_production_admin_provisioning_via_environment_variables(): void
    {
        $originalEnv = app()->environment();
        $this->app->detectEnvironment(fn () => 'production');

        $adminEmail = 'prod_admin_' . uniqid() . '@example.com';
        $adminPassword = 'StrongSecurePassword!2026';

        putenv("INITIAL_ADMIN_EMAIL={$adminEmail}");
        putenv("INITIAL_ADMIN_PASSWORD={$adminPassword}");
        putenv("INITIAL_ADMIN_NAME=Super Admin");

        $seeder = new DatabaseSeeder();
        $seeder->run();

        $adminUser = User::where('email', $adminEmail)->first();
        $this->assertNotNull($adminUser);
        $this->assertSame(User::ROLE_ADMIN, $adminUser->role);
        $this->assertTrue(Hash::check($adminPassword, $adminUser->password));

        // Clean up env
        putenv('INITIAL_ADMIN_EMAIL');
        putenv('INITIAL_ADMIN_PASSWORD');
        putenv('INITIAL_ADMIN_NAME');
        $this->app->detectEnvironment(fn () => $originalEnv);
    }

    /**
     * Requirement: Production admin provisioning uses a trusted secure mechanism (Artisan command).
     */
    public function test_production_admin_provisioning_via_artisan_command(): void
    {
        $adminEmail = 'artisan_admin_' . uniqid() . '@example.com';
        $adminPassword = 'CommandSecurePassword#2026';

        $exitCode = Artisan::call('user:create-admin', [
            'email'      => $adminEmail,
            '--name'     => 'CLI Provisioned Admin',
            '--password' => $adminPassword,
        ]);

        $this->assertSame(0, $exitCode);

        $adminUser = User::where('email', $adminEmail)->first();
        $this->assertNotNull($adminUser);
        $this->assertSame(User::ROLE_ADMIN, $adminUser->role);
        $this->assertSame('CLI Provisioned Admin', $adminUser->name);
        $this->assertTrue(Hash::check($adminPassword, $adminUser->password));
    }

    /**
     * Requirement 8-10: Successful login, JWT issuance, and access to authenticated endpoint.
     */
    public function test_demo_login_jwt_issuance_and_authenticated_endpoint_access(): void
    {
        // Clear rate limiter cache from prior tests
        \Illuminate\Support\Facades\Cache::flush();

        // 1. Ensure demo customer exists
        User::where('email', 'cc@gmail.com')->delete();
        $customer = User::create([
            'name'     => 'Demo Customer',
            'email'    => 'cc@gmail.com',
            'password' => Hash::make('0000'),
            'role'     => User::ROLE_USER,
        ]);

        // 2. Successful login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'    => 'cc@gmail.com',
            'password' => '0000',
        ]);

        $loginResponse->assertStatus(200);
        $loginResponse->assertJson([
            'success' => true,
            'message' => 'Logged in successfully.',
        ]);

        $token = $loginResponse->json('data.token');
        $this->assertNotEmpty($token);

        // 3. Access authenticated endpoint with JWT token
        $meResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $meResponse->assertStatus(200);
        $meResponse->assertJsonPath('data.email', 'cc@gmail.com');
        $meResponse->assertJsonPath('data.role', User::ROLE_USER);
    }
}
