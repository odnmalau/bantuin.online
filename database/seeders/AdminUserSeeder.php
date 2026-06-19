<?php

namespace Database\Seeders;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = (string) env('SEED_ADMIN_EMAIL', 'admin@hirepilot.test');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) env('SEED_ADMIN_NAME', 'HirePilot Admin'),
                'google_id' => env('SEED_ADMIN_GOOGLE_ID'),
                'role' => UserRole::Admin,
            ],
        );
    }
}
