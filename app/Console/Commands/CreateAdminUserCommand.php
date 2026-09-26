<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdminUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create {email?} {password?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update admin user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email') ?? 'admin@vinoi.com';
        $password = $this->argument('password') ?? 'Admin123@';

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Quản trị Ví Nói',
                'password' => bcrypt($password),
                'role' => User::ROLE_ADMIN,
            ]
        );

        $this->info("✓ Admin user created/updated:");
        $this->table(
            ['Field', 'Value'],
            [
                ['Email', $user->email],
                ['Name', $user->name],
                ['Role', $user->role],
                ['Password', $password],
            ]
        );

        return self::SUCCESS;
    }
}
