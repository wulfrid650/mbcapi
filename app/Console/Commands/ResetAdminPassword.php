<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetAdminPassword extends Command
{
    protected $signature = 'admin:reset-password {email=admin@madibabc.com} {password=Admin123!}';
    protected $description = 'Reset admin user password';

    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User not found: {$email}");
            return 1;
        }

        $user->password = Hash::make($password);
        $user->is_active = true;
        $user->save();

        $this->info("Password reset successfully for: {$email}");
        $this->info("New password: {$password}");
        
        // Verify the password works
        if (Hash::check($password, $user->fresh()->password)) {
            $this->info("Password verification: OK");
        } else {
            $this->error("Password verification: FAILED");
        }

        return 0;
    }
}
