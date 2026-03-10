<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Crée un utilisateur par défaut pour le développement.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'ronsard2014@gmail.com'],
            [
                'first_name' => 'Denis',
                'last_name' => "AMAH",
                'password' => Hash::make('DenisAmah@#2023'),
            ]
        );
    }
}

