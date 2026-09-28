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
        Schema::create('exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('offered_item_id')->nullable()->constrained('items')->restrictOnDelete();
            $table->enum('type', ['TRADE', 'GIFT']);
            $table->enum('status', ['PENDING', 'ACCEPTED', 'REJECTED', 'FINISHED'])->default('PENDING');
            $table->string('message', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchanges');
    }
};
