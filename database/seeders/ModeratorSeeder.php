<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ModeratorSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => Role::MODERATOR]);
        $moderator = User::firstOrCreate(['email' => 'moderator@example.com'], [
            'name' => 'Модератор',
            'password' => Hash::make('Moderator123!'),
        ]);
        $moderator->role()->associate($role);
        $moderator->save();
    }
}
