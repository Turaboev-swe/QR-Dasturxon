<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The platform owner's own login for the Filament SaaS operator panel
     * (/admin) — a THIRD, completely independent auth mechanism alongside
     * customer `telegram.auth` (TelegramUser) and restaurant `staff.auth`
     * (Staff). Ordinary email+password, its own guard/provider
     * (`platform_admin`, config/auth.php) — no relation whatsoever to
     * `users`, `telegram_users`, or `staff`.
     */
    public function up(): void
    {
        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_admins');
    }
};
