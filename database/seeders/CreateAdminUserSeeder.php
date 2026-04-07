<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CreateAdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure the admin role exists
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrateur']
        );

        // Create or retrieve the user
        $user = User::firstOrCreate(
            ['email' => 'admin@madibabc.com'],
            [
                'name' => 'Admin System',
                'password' => Hash::make('password123'),
                'phone' => '0000000000',
            ]
        );

        // Assign the role if not already assigned
        if (!$user->roles()->where('roles.id', $role->id)->exists()) {
            $user->roles()->attach($role->id);
        }

        // Set as active role
        $user->active_role_id = $role->id;
        $user->save();

        $this->command->info('Admin user created successfully.');
        $this->command->info('Email: admin@madibabc.com');
        $this->command->info('Password: password123');
    }
}
