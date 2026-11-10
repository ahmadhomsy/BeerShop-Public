<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Users\Services\UserService;
use Illuminate\Console\Command;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-admin 
                            {email : The email address of the administrator}
                            {--name=Administrator : The display name of the administrator}
                            {--password= : The administrator password (prompts securely if omitted)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision a secure administrator account';

    /**
     * Execute the console command.
     */
    public function handle(UserService $userService): int
    {
        $email = $this->argument('email');
        $name = $this->option('name') ?: 'Administrator';
        $password = $this->option('password');

        if (empty($password)) {
            $password = $this->secret('Enter administrator password:');
            if (empty($password)) {
                $this->error('Password cannot be empty.');
                return self::FAILURE;
            }
        }

        if (strlen($password) < 8) {
            $this->error('Administrator password must be at least 8 characters long.');
            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("User with email [{$email}] already exists.");
            return self::FAILURE;
        }

        $user = $userService->createPrivilegedUser([
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
        ], User::ROLE_ADMIN);

        $this->info("Administrator [{$user->email}] successfully created with ID: {$user->id}.");

        return self::SUCCESS;
    }
}
