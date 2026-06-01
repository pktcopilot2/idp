<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'username' => 'testuser',
        //     'email' => 'test@example.com',
        // ]);

        // User::factory()->create([
        //     'name' => 'Sallie Trixie Zebada Mansurina',
        //     'username' => 'k236615',
        //     'email' => 'k236615@pupukkaltim.com',
        // ]);

        // User::factory()->create([
        //     'name' => 'Arif',
        //     'username' => 'k258008',
        //     'email' => 'k258008@pupukkaltim.com',
        // ]);

        // User::factory(50)->create();

        $user = User::query()->firstOrCreate([
            'username' => 'admin',
        ], [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('Superadmin');

        $user = User::query()->firstOrCreate([
            'username' => 'k236615',
        ], [
            'name' => 'Sallie Trixie Zebada Mansurina',
            'email' => 'k236615@pupukkaltim.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole(['Superadmin', 'Viewer']);
    }
}
