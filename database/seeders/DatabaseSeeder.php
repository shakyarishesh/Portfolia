<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PortfolioSeeder::class);

        // Optional: create the admin from .env. Otherwise run `php artisan portfolio:admin you@example.com`.
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        if ($email && $password) {
            User::updateOrCreate(['email' => $email], ['name' => env('ADMIN_NAME', 'Admin'), 'password' => $password, 'is_admin' => true]);
            $this->command?->info("Admin account ready: {$email}");
        }
    }
}
