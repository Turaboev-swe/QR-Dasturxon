<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            $table->unsignedTinyInteger('taste_spicy')->nullable()->after('discount_portions_remaining');
            $table->unsignedTinyInteger('taste_sweet')->nullable()->after('taste_spicy');
            $table->unsignedTinyInteger('taste_salty')->nullable()->after('taste_sweet');
        });
    }

    public function down(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            $table->dropColumn(['taste_spicy', 'taste_sweet', 'taste_salty']);
        });
    }
};
