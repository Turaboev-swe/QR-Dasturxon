<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            // Telegram group chat ids (signed — Telegram group/supergroup
            // ids are negative) that kitchen/waiter notifications are sent
            // to. Nullable: a restaurant without these set simply gets no
            // Telegram notifications, order/waiter-call creation still works.
            $table->bigInteger('kitchen_chat_id')->nullable()->after('badge_text');
            $table->bigInteger('waiter_chat_id')->nullable()->after('kitchen_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['kitchen_chat_id', 'waiter_chat_id']);
        });
    }
};
