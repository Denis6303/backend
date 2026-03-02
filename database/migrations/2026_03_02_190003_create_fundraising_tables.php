<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fundraisings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();

            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('slug')->unique();

            $table->string('beneficiary_type')->nullable();
            $table->json('beneficiary')->nullable(); // beneficiary_* normalized into JSON
            $table->string('beneficiary_display_name')->nullable();

            $table->char('currency', 3);
            $table->decimal('target_amount', 14, 2)->nullable();
            $table->decimal('current_amount', 14, 2)->default(0);
            $table->boolean('is_amount_visible')->default(true);

            $table->boolean('is_private')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->string('status')->default('open'); // open/closed/stopped

            $table->string('country_code', 5)->nullable();
            $table->unsignedInteger('order_priority')->default(0);
            $table->unsignedBigInteger('nb_visites')->default(0);
            $table->unsignedInteger('likes_count')->default(0);

            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_private', 'is_verified']);
        });

        Schema::create('fundraising_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fundraising_id')->nullable()->constrained('fundraisings')->nullOnDelete();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->json('data')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('fundraising_payment_intents', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->foreignId('fundraising_id')->constrained('fundraisings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('amount', 14, 2);
            $table->decimal('fees', 14, 2)->default(0);
            $table->char('currency', 3);

            $table->foreignId('payment_provider_id')->nullable()->constrained('payment_providers')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('status')->default('pending'); // pending/processing/confirming/paid/failed/cancelled/expired

            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();

            $table->dateTime('expires_at')->nullable();
            $table->string('idempotency_key')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['fundraising_id', 'status']);
        });

        Schema::create('fundraising_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fundraising_id')->constrained('fundraisings')->cascadeOnDelete();
            $table->foreignId('payment_intent_id')->nullable()->constrained('fundraising_payment_intents')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();

            $table->foreignId('payer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('name')->nullable();

            $table->decimal('amount', 14, 2);
            $table->decimal('fees', 14, 2)->default(0);
            $table->boolean('is_amount_visible')->default(true);
            $table->text('message')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fundraising_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fundraising_id')->constrained('fundraisings')->cascadeOnDelete();
            $table->string('type')->default('percentage'); // percentage/fixed
            $table->decimal('value', 14, 2);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fundraising_commissions');
        Schema::dropIfExists('fundraising_contributions');
        Schema::dropIfExists('fundraising_payment_intents');
        Schema::dropIfExists('fundraising_drafts');
        Schema::dropIfExists('fundraisings');
    }
};

