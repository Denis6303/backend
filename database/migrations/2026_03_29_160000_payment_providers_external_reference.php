<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payment_providers', 'external_reference')) {
            Schema::table('payment_providers', function (Blueprint $table) {
                $table->string('external_reference')->nullable()->after('provider_code');
            });
        }

        $legacy = [
            'wave_checkout_id',
            'djamo_charge_id',
            'paystack_reference',
            'hub2_reference',
            'intouch_reference',
        ];
        $toDrop = array_values(array_filter($legacy, fn (string $c) => Schema::hasColumn('payment_providers', $c)));
        if ($toDrop !== []) {
            Schema::table('payment_providers', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }
    }

    public function down(): void
    {
        Schema::table('payment_providers', function (Blueprint $table) {
            $table->string('wave_checkout_id')->nullable();
            $table->string('djamo_charge_id')->nullable();
            $table->string('paystack_reference')->nullable();
            $table->string('hub2_reference')->nullable();
            $table->string('intouch_reference')->nullable();
        });

        if (Schema::hasColumn('payment_providers', 'external_reference')) {
            Schema::table('payment_providers', function (Blueprint $table) {
                $table->dropColumn('external_reference');
            });
        }
    }
};
