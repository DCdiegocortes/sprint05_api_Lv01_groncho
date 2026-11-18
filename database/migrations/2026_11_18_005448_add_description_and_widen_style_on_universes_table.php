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
        Schema::table('universes', function (Blueprint $table) {
            $table->string('style', 255)->nullable()->change();
            $table->string('description', 255)->nullable()->after('style');
        });

        DB::table('universes')
            ->whereNotNull('style')
            ->update(['style' => DB::raw('UPPER(style)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('universes', function (Blueprint $table) {
            $table->dropColumn('description');
            $table->string('style', 100)->nullable()->change();
        });
    }
};
