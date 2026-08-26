<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The customer-facing "pay now / pay later" choice is removed — payment
     * always happens with the waiter at the end. `payment_status` replaces
     * it as a staff-driven fact (defaults to `unpaid`; nothing sets it to
     * `paid` yet — that lands when the waiter's "hisob" flow is built).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid')->after('status');
            $table->dropColumn('payment_preference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_preference')->nullable()->after('status');
            $table->dropColumn('payment_status');
        });
    }
};
