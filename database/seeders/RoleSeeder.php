<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $reader = Role::firstOrCreate(['name' => Role::READER]);
        Role::firstOrCreate(['name' => Role::MODERATOR]);

        User::whereNull('role_id')->update(['role_id' => $reader->id]);
    }
}
