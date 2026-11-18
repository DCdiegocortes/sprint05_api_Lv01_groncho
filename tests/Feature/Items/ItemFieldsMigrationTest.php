<?php

namespace Tests\Feature\Items;

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemFieldsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_18_010903_add_condition_type_and_offer_to_items_table.php');
    }

    public function test_existing_items_get_the_default_condition_type_and_offer(): void
    {
        $migration = $this->migration();
        $migration->down();
        $user = User::factory()->create();
        DB::table('items')->insert([
            'user_id' => $user->id,
            'name' => 'Existing item',
            'status' => 'AVAILABLE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertDatabaseHas('items', [
            'name' => 'Existing item',
            'item_condition' => 'GOOD',
            'type' => 'CLOTHES',
            'offer_type' => 'TRADE',
        ]);
    }
}
