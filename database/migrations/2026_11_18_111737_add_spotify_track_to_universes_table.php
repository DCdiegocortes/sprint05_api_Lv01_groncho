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
            $table->string('spotify_track_id', 22)->nullable()->after('layout');
            $table->string('spotify_track_title')->nullable()->after('spotify_track_id');
            $table->string('spotify_track_cover_url')->nullable()->after('spotify_track_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('universes', function (Blueprint $table) {
            $table->dropColumn(['spotify_track_id', 'spotify_track_title', 'spotify_track_cover_url']);
        });
    }
};
