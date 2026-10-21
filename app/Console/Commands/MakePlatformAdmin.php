<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Create (or reset) a ZamPayroll platform administrator who can open /platform. */
class MakePlatformAdmin extends Command
{
    protected $signature = 'zampayroll:admin {email} {--name=ZamPayroll Admin}';
    protected $description = 'Create a ZamPayroll platform administrator account';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $password = $this->secret('Password (leave empty to generate one)') ?: Str::password(14);

        $user = User::on(Tenancy::landlordConnection())->firstOrNew(['email' => $email]);
        if ($user->exists && $user->company_id) {
            $this->error('That email belongs to a company account. Use a separate email for platform administration.');

            return self::FAILURE;
        }

        $user->forceFill([
            'name' => $this->option('name'),
            'password' => Hash::make($password),
            'role' => 'platform_admin',
            'company_id' => null,
            'email_verified_at' => now(),
        ])->save();

        $this->info("Platform admin ready: {$email}");
        $this->line('Password: ' . $password);
        $this->line('Sign in at ' . url('/login') . ' — you will land on /platform.');

        return self::SUCCESS;
    }
}
