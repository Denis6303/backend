<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedInteger('order_priority')->default(0);
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // wave, card, mobile_money, djamo, api, ...
            $table->string('label_fr');
            $table->string('label_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payment_providers', function (Blueprint $table) {
            $table->id();
            $table->string('provider_code'); // wave, djamo, paystack, hub2, intouch, ...

            $table->string('wave_checkout_id')->nullable();
            $table->string('djamo_charge_id')->nullable();
            $table->string('paystack_reference')->nullable();
            $table->string('hub2_reference')->nullable();
            $table->string('intouch_reference')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['provider_code']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('favoritable_type', 50);
            $table->unsignedBigInteger('favoritable_id');
            $table->timestamps();

            $table->unique(['user_id', 'favoritable_type', 'favoritable_id'], 'favorites_unique');
            $table->index(['favoritable_type', 'favoritable_id'], 'favorites_morph_index');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subscribable_type', 50);
            $table->unsignedBigInteger('subscribable_id');
            $table->string('channel', 20)->default('push'); // push/email/sms/...
            $table->timestamps();

            $table->unique(['user_id', 'subscribable_type', 'subscribable_id', 'channel'], 'subscriptions_unique');
            $table->index(['subscribable_type', 'subscribable_id'], 'subscriptions_morph_index');
        });

        Schema::create('email_wallet_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->char('currency', 3);
            $table->decimal('amount', 14, 2);
            $table->string('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_wallet_reservation_id')->nullable()->constrained('email_wallet_reservations')->nullOnDelete();

            $table->char('currency', 3);
            $table->decimal('amount', 14, 2);
            $table->string('type'); // credit/debit/refund/...
            $table->string('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();

            $table->morphs('model');
            $table->uuid('uuid')->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->nullableTimestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
        Schema::dropIfExists('user_wallet_transactions');
        Schema::dropIfExists('email_wallet_reservations');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('payment_providers');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('categories');
    }
};

