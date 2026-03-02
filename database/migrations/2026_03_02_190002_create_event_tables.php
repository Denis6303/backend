<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();

            $table->string('slug')->unique();
            $table->string('group')->nullable();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->json('images')->nullable();

            $table->string('country_code', 5)->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('online_link')->nullable();

            $table->char('currency', 3)->nullable();
            $table->decimal('price_min', 14, 2)->nullable();

            $table->string('status')->default('saved'); // upcoming/completed/cancelled/saved
            $table->boolean('is_private')->default(false);
            $table->boolean('is_verified')->default(false);

            $table->decimal('commission_percentage', 6, 3)->nullable();
            $table->decimal('commission_amount', 14, 2)->nullable();
            $table->unsignedInteger('order_priority')->default(0);

            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedBigInteger('nb_visites')->default(0);

            $table->boolean('invitations_is_free')->default(false);
            $table->boolean('print_is_free')->default(false);

            $table->unsignedBigInteger('category_level_1')->nullable();
            $table->unsignedBigInteger('category_level_2')->nullable();
            $table->unsignedBigInteger('category_level_3')->nullable();

            $table->string('timezone_name')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_private', 'is_verified']);
        });

        Schema::create('item_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();

            $table->string('subtitle')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->string('status')->default('upcoming'); // upcoming/completed/cancelled
            $table->boolean('free_event')->default(false);
            $table->unsignedBigInteger('nb_visites')->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'status']);
        });

        Schema::create('ticket_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_occurrence_id')->constrained('item_occurrences')->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('general_conditions')->nullable();

            $table->decimal('price', 14, 2)->default(0);
            $table->decimal('last_price', 14, 2)->nullable();
            $table->unsignedInteger('total_quantity')->default(0);
            $table->unsignedInteger('remaining_quantity')->default(0);
            $table->unsignedInteger('real_remaining_quantity')->default(0);
            $table->unsignedInteger('printed_quantity')->default(0);

            $table->string('tag')->nullable();
            $table->foreignId('tag_id')->nullable()->constrained('ticket_tags')->nullOnDelete();

            $table->string('status')->default('active'); // active/disabled
            $table->timestamps();

            $table->index(['item_occurrence_id', 'status']);
        });

        Schema::create('ticket_type_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_type_id')->constrained('ticket_types')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('status')->default('active'); // active/stopped
            $table->timestamps();
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_occurrence_id')->constrained('item_occurrences')->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('percentage'); // percentage/fixed
            $table->decimal('value', 14, 2);
            $table->string('status')->default('active'); // active/disabled
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')->constrained('discounts')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('status')->default('active'); // active/disabled/expired
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('validators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_occurrence_id')->constrained('item_occurrences')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->json('permissions')->nullable();
            $table->timestamps();

            $table->unique(['item_occurrence_id', 'user_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('claim_code')->nullable()->index();

            $table->foreignId('item_occurrence_id')->constrained('item_occurrences')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('distributor_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('amount', 14, 2);
            $table->decimal('fees', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->char('currency', 3);

            $table->string('type')->default('online'); // online/invitation/print
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('full_name')->nullable();

            $table->string('status')->default('confirmed'); // confirmed/cancelled/failed
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_intents', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('claim_code')->nullable()->index();

            $table->decimal('price', 14, 2);
            $table->decimal('fees', 14, 2)->default(0);
            $table->char('currency', 3);

            $table->string('status')->default('pending'); // pending/processing/confirming/confirmed/failed/expired/pending_approval
            $table->foreignId('discount_id')->nullable()->constrained('discounts')->nullOnDelete();
            $table->foreignId('payment_provider_id')->nullable()->constrained('payment_providers')->nullOnDelete();

            $table->foreignId('item_occurrence_id')->constrained('item_occurrences')->cascadeOnDelete();
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('delivery_method')->nullable();

            $table->json('meta')->nullable(); // cart lines, conversions, provider payloads, etc.
            $table->dateTime('expired_at')->nullable();
            $table->timestamps();

            $table->index(['item_occurrence_id', 'status']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_key')->unique();
            $table->string('ticket_number')->unique();

            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('ticket_type_id')->constrained('ticket_types')->cascadeOnDelete();
            $table->foreignId('item_occurrence_id')->constrained('item_occurrences')->cascadeOnDelete();

            $table->decimal('price', 14, 2)->default(0);
            $table->string('status')->default('active'); // active/validated/cancelled/expired/transferred
            $table->boolean('is_cancellable')->default(true);

            $table->timestamp('validated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('transferred_at')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('distributor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('full_name')->nullable();
            $table->timestamps();

            $table->index(['item_occurrence_id', 'status']);
        });

        Schema::create('ticket_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('user_wallet_transaction_id')->nullable()->constrained('user_wallet_transactions')->nullOnDelete();
            $table->char('currency', 3);
            $table->decimal('amount', 14, 2);
            $table->decimal('rate', 6, 3)->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('item_occurrence_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_occurrence_id')->constrained('item_occurrences')->cascadeOnDelete();
            $table->decimal('commission_percentage', 6, 3)->nullable();
            $table->decimal('commission_amount', 14, 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('item_occurrence_service_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_occurrence_id')->constrained('item_occurrences')->cascadeOnDelete();
            $table->string('label');
            $table->decimal('amount', 14, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('item_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_occurrence_id')->constrained('item_occurrences')->cascadeOnDelete();
            $table->decimal('gross_revenue', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('fees_total', 14, 2)->default(0);
            $table->decimal('commission_total', 14, 2)->default(0);
            $table->decimal('net_revenue', 14, 2)->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('item_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('data')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_drafts');
        Schema::dropIfExists('item_earnings');
        Schema::dropIfExists('item_occurrence_service_costs');
        Schema::dropIfExists('item_occurrence_commissions');
        Schema::dropIfExists('ticket_refunds');
        Schema::dropIfExists('ticket_transfers');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('order_intents');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('validators');
        Schema::dropIfExists('discount_codes');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('ticket_type_promotions');
        Schema::dropIfExists('ticket_types');
        Schema::dropIfExists('ticket_tags');
        Schema::dropIfExists('item_occurrences');
        Schema::dropIfExists('items');
    }
};

