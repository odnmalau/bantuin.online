<?php

namespace Database\Seeders;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Seeder;

class DemoCandidateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'candidate@hirepilot.test'],
            [
                'name' => 'Demo Candidate',
                'google_id' => 'seed-demo-candidate',
                'role' => UserRole::Candidate,
            ],
        );
    }
}
