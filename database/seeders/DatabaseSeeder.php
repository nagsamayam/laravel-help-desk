<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
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
        // User::factory(10)->create();
        $this->call(CategorySeeder::class);

        User::factory()->create([
            'first_name' => 'Nageswara Rao',
            'last_name' => 'Samayam',
            'email' => 'nag.samayam@gmail.com',
            'role' => Role::Admin,
        ]);
    }
}
