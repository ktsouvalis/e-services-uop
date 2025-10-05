<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $usernames = [
            'ktsouvalis',
            'earvanitaki',
            'potami',
            'chalkiop',
            'aggelos.voros',
        ];

        foreach ($usernames as $username) {
            $name = ucwords(str_replace(['.', '_'], ' ', $username));
            $email = $username . '@uop.gr';
            $admin = $username === 'ktsouvalis' ? 1 : 0;
            User::updateOrCreate(
                ['username' => $username],
                [
                    'username' => $username,
                    'name' => $name,
                    'email' => $email,
                    'admin' => $admin,
                ]
            );
        }
    }
}
