<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
     $project = \App\Models\Project::create(['name' => 'Terpel']);

$project->solutions()->createMany([
    ['name' => 'Terpel POS'],
    ['name' => 'Alisson'],
    ['name' => 'Hidrocarburos'],
]);

\App\Models\User::factory()->create([
    'name' => 'Admin Performance',
    'email' => 'admin@ntt.com',
    'password' => bcrypt('password'),
]);
    }
}
