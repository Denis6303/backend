<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('fundraising_commissions');
        Schema::dropIfExists('fundraising_contributions');
        Schema::dropIfExists('fundraising_payment_intents');
        Schema::dropIfExists('fundraising_drafts');
        Schema::dropIfExists('fundraisings');
    }
};
