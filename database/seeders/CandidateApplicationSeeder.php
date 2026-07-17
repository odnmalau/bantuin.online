<?php

namespace Database\Seeders;

use App\Models\CandidateApplication;
use Illuminate\Database\Seeder;

class CandidateApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CandidateApplication::factory()->count(10)->create();
    }
}
