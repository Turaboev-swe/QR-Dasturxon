<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff are now identified by their Telegram id (pre-registered by the
     * restaurant), exactly like customers — no phone/PIN login screen.
     */
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropUnique(['api_token_hash']);
            $table->dropColumn(['pin_code_hash', 'api_token_hash']);
            // Stays nullable — a NULL telegram_id simply never matches any
            // real Telegram user id, so it's an effective (if soft) "not
            // yet linked" state without needing to backfill existing rows.
            $table->unsignedBigInteger('telegram_id')->nullable()->unique()->change();
            $table->string('phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('pin_code_hash')->default('')->after('telegram_id');
            $table->string('api_token_hash')->nullable()->unique()->after('pin_code_hash');
            $table->unsignedBigInteger('telegram_id')->nullable()->change();
            $table->string('phone')->nullable(false)->change();
        });
    }
};
