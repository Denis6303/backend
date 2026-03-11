<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_drafts', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('user_id')->constrained('categories')->nullOnDelete();
            $table->unsignedTinyInteger('current_step')->default(1)->after('data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_drafts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['current_step']);
        });
    }
};
