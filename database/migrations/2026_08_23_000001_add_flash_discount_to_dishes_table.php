<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            $table->unsignedTinyInteger('discount_percent')->nullable()->after('is_available');
            $table->timestamp('discount_ends_at')->nullable()->after('discount_percent');
            $table->unsignedInteger('discount_portions_total')->nullable()->after('discount_ends_at');
            $table->unsignedInteger('discount_portions_remaining')->nullable()->after('discount_portions_total');
        });
    }

    public function down(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discount_ends_at', 'discount_portions_total', 'discount_portions_remaining']);
        });
    }
};
