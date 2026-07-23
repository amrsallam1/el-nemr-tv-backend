<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email} {--name=Administrator} {--password=}';
    protected $description = 'Create or promote an El-Nemr TV administrator';

    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Admin password');

        if (! is_string($password) || strlen($password) < 8) {
            $this->error('Password must contain at least 8 characters.');
            return self::FAILURE;
        }

        $user = User::firstOrNew([
            'email' => strtolower($this->argument('email')),
        ]);

        $user->forceFill([
            'name' => $this->option('name'),
            'password' => $password,
            'role' => 'admin',
            'is_active' => true,
        ])->save();

        $this->info('Administrator is ready.');
        return self::SUCCESS;
    }
}
