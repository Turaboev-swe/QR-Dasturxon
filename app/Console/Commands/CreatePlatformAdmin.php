<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Creates the platform owner's login for the Filament panel (/admin).
 * Fully interactive — email/name/password are prompted, never accepted
 * as command-line arguments, so they never end up in shell history or
 * process-list snapshots; the password itself is masked on input
 * (Command::secret()) and is never echoed back or logged anywhere.
 */
class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform-admin:create';

    protected $description = 'Interactively create a platform admin (Filament /admin panel login)';

    public function handle(): int
    {
        $name = $this->ask('Ism');

        $email = $this->ask('Email');
        $emailValidator = Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'unique:platform_admins,email'],
        ]);
        if ($emailValidator->fails()) {
            $this->error($emailValidator->errors()->first('email'));

            return self::FAILURE;
        }

        $password = $this->secret('Parol (kamida 8 belgi)');
        $confirmPassword = $this->secret('Parolni tasdiqlang');

        if ($password !== $confirmPassword) {
            $this->error('Parollar mos kelmadi.');

            return self::FAILURE;
        }

        $passwordValidator = Validator::make(['password' => $password], [
            'password' => ['required', 'string', 'min:8'],
        ]);
        if ($passwordValidator->fails()) {
            $this->error($passwordValidator->errors()->first('password'));

            return self::FAILURE;
        }

        // PlatformAdmin::password is cast `hashed` — Laravel hashes it on
        // assignment, no manual Hash::make() needed (and doing it manually
        // here would double-hash it).
        PlatformAdmin::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->info("Platform admin yaratildi: {$email}");

        return self::SUCCESS;
    }
}
