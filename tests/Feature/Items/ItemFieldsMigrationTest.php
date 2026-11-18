<?php

namespace Tests\Feature\Items;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemFieldsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_items_get_the_default_condition_type_and_offer(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertExitCode(0);
        $user = User::factory()->create();
        DB::table('items')->insert([
            'user_id' => $user->id,
            'name' => 'Existing item',
            'status' => 'AVAILABLE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('migrate')->assertExitCode(0);

        $this->assertDatabaseHas('items', [
            'name' => 'Existing item',
            'item_condition' => 'GOOD',
            'type' => 'CLOTHES',
            'offer_type' => 'TRADE',
        ]);
    }
}
