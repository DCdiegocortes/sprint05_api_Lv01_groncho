<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('universe_images', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('path');
        });

        $positions = [];

        DB::table('universe_images')->orderBy('id')->get(['id', 'universe_id'])->each(function ($image) use (&$positions) {
            $positions[$image->universe_id] = ($positions[$image->universe_id] ?? 0) + 1;

            DB::table('universe_images')->where('id', $image->id)->update(['position' => $positions[$image->universe_id]]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('universe_images', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
