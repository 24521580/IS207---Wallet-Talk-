<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        User::query()->updateOrCreate(
            ['email' => 'demo@vinoi.com'],
            [
                'name' => 'Minh Anh',
                'password' => 'Demo123@',
                'role' => User::ROLE_USER,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@vinoi.com'],
            [
                'name' => 'Quản trị Ví Nói',
                'password' => 'Admin123@',
                'role' => User::ROLE_ADMIN,
            ],
        );

        $this->call(DemoTransactionSeeder::class);
    }
}
