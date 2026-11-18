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
        Schema::table('universes', function (Blueprint $table) {
            $table->enum('layout', [
                'MASONRY',
                'ACCORDION',
                'CAROUSEL',
                'ROTUNDA',
                'FLOATING',
                'IMAGE_GRID',
                'INFINITE',
                'LIQUID_GLASS',
                'SCROLL_BULGE',
                'SLIDER',
            ])->default('MASONRY')->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('universes', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }
};
