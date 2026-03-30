<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name'    => 'Alice Johnson',
                'email'   => 'alice@example.com',
                'balance' => 1000000.00,
            ],
            [
                'name'    => 'Bob Smith',
                'email'   => 'bob@example.com',
                'balance' => 500000.00,
            ],
            [
                'name'    => 'Charlie Brown',
                'email'   => 'charlie@example.com',
                'balance' => 250000.00,
            ],
            [
                'name'    => 'Diana Prince',
                'email'   => 'diana@example.com',
                'balance' => 750000.00,
            ],
            [
                'name'    => 'Eve Adams',
                'email'   => 'eve@example.com',
                'balance' => 100000.00,
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->command->info('Created ' . count($users) . ' users successfully.');
    }
}
