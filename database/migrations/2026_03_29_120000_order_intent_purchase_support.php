<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporary_ticket_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_intent_id')->constrained('order_intents')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained('ticket_types')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['order_intent_id', 'ticket_type_id'], 'tmp_tkt_res_intent_type_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('order_intent_id')->nullable()->constrained('order_intents')->nullOnDelete();
            $table->unique('order_intent_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_intent_id');
        });

        Schema::dropIfExists('temporary_ticket_reservations');
    }
};
