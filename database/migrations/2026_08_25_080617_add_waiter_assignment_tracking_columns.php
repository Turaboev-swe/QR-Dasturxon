<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sticky "which waiter is currently handling this table" tracking —
     * see CLAUDE.md's waiter-call assignment section. `assigned_waiter_*`
     * lives on the table (cleared on every new order); `handled_by_*` is a
     * per-call snapshot of whoever actually resolved that specific call.
     */
    public function up(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->bigInteger('assigned_waiter_telegram_id')->nullable()->after('is_active');
            $table->string('assigned_waiter_name')->nullable()->after('assigned_waiter_telegram_id');
        });

        Schema::table('waiter_calls', function (Blueprint $table) {
            $table->bigInteger('handled_by_telegram_id')->nullable()->after('status');
            $table->string('handled_by_name')->nullable()->after('handled_by_telegram_id');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropColumn(['assigned_waiter_telegram_id', 'assigned_waiter_name']);
        });

        Schema::table('waiter_calls', function (Blueprint $table) {
            $table->dropColumn(['handled_by_telegram_id', 'handled_by_name']);
        });
    }
};
