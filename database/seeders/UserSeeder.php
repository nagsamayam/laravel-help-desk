<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('password');

        // Admins
        $admins = [
            [
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'email' => 'admin@example.com',
                'role' => Role::Admin,
                'email_verified_at' => now(),
                'password' => $defaultPassword,
            ],
        ];

        foreach ($admins as $adminData) {
            User::firstOrCreate(
                ['email' => $adminData['email']],
                $adminData
            );
        }

        // Support Agents
        $agents = [
            [
                'first_name' => 'Sarah',
                'last_name' => 'Connor',
                'email' => 'sarah.agent@example.com',
                'role' => Role::Agent,
                'email_verified_at' => now(),
                'password' => $defaultPassword,
            ],
            [
                'first_name' => 'Alex',
                'last_name' => 'Morgan',
                'email' => 'alex.agent@example.com',
                'role' => Role::Agent,
                'email_verified_at' => now(),
                'password' => $defaultPassword,
            ],
            [
                'first_name' => 'David',
                'last_name' => 'Miller',
                'email' => 'david.agent@example.com',
                'role' => Role::Agent,
                'email_verified_at' => now(),
                'password' => $defaultPassword,
            ],
            [
                'first_name' => 'Elena',
                'last_name' => 'Rostova',
                'email' => 'elena.agent@example.com',
                'role' => Role::Agent,
                'email_verified_at' => now(),
                'password' => $defaultPassword,
            ],
        ];

        foreach ($agents as $agentData) {
            User::firstOrCreate(
                ['email' => $agentData['email']],
                $agentData
            );
        }

        // Customers
        $customers = [
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.customer@example.com',
                'role' => Role::Customer,
                'email_verified_at' => now(),
                'password' => $defaultPassword,
            ],
            [
                'first_name' => 'Emily',
                'last_name' => 'Davis',
                'email' => 'emily.customer@example.com',
                'role' => Role::Customer,
                'email_verified_at' => now(),
                'password' => $defaultPassword,
            ],
            [
                'first_name' => 'Michael',
                'last_name' => 'Scott',
                'email' => 'michael.customer@example.com',
                'role' => Role::Customer,
                'email_verified_at' => now(),
                'password' => $defaultPassword,
            ],
            [
                'first_name' => 'Sophia',
                'last_name' => 'Lee',
                'email' => 'sophia.customer@example.com',
                'role' => Role::Customer,
                'email_verified_at' => now(),
                'password' => $defaultPassword,
            ],
            [
                'first_name' => 'Bruce',
                'last_name' => 'Wayne',
                'email' => 'bruce.wayne@example.com',
                'role' => Role::Customer,
                'email_verified_at' => now(),
                'password' => $defaultPassword,
            ],
        ];

        foreach ($customers as $customerData) {
            User::firstOrCreate(
                ['email' => $customerData['email']],
                $customerData
            );
        }
    }
}
