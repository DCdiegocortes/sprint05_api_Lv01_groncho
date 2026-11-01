<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    private const PASSWORD = 'password';

    private const USERS = [
        'Ana' => 'ana@groncho.com',
        'Beto' => 'beto@groncho.com',
        'Carla' => 'carla@groncho.com',
        'Dani' => 'dani@groncho.com',
        'Eli' => 'eli@groncho.com',
    ];

    public function run(): void
    {
        $password = Hash::make(self::PASSWORD);

        foreach (self::USERS as $name => $email) {
            User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password]
            );
        }
    }
}
