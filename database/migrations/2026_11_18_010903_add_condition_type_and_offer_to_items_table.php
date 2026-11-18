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
        Schema::table('items', function (Blueprint $table) {
            $table->enum('item_condition', ['NEW', 'EXCELLENT', 'GOOD', 'FAIR'])->default('GOOD')->after('size');
            $table->enum('type', ['CLOTHES', 'ACCESSORIES'])->default('CLOTHES')->after('item_condition');
            $table->enum('offer_type', ['TRADE', 'GIFT', 'BOTH'])->default('TRADE')->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['item_condition', 'type', 'offer_type']);
        });
    }
};
