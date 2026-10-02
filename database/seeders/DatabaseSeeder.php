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
        $adminRole = \App\Models\Role::where('nombre', 'admin')->first();

        // Create admin user Jenny Garcia Morales
        User::create([
            'name' => 'Jenny',
            'apellidos' => 'Garcia Morales',
            'ci' => '5927724',
            'role_id' => $adminRole ? $adminRole->id : 2,
            'password' => \Hash::make('5927724'), // CI as default password
            'status' => 1,
            'password_changed_at' => null // Force password change on first login
        ]);
    }
}
