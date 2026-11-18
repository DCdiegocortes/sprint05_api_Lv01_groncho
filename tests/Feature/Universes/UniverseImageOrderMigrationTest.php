<?php

namespace Tests\Feature\Universes;

use App\Models\Universe;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UniverseImageOrderMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_18_113356_add_position_to_universe_images_table.php');
    }

    private function insertImage(Universe $universe, string $path): int
    {
        return DB::table('universe_images')->insertGetId([
            'universe_id' => $universe->id,
            'path' => $path,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_existing_photos_are_numbered_in_upload_order_within_each_universe(): void
    {
        $migration = $this->migration();
        $migration->down();
        $soft = Universe::factory()->create();
        $street = Universe::factory()->create();
        $softFirst = $this->insertImage($soft, 'universe-images/a.jpg');
        $streetFirst = $this->insertImage($street, 'universe-images/b.jpg');
        $softSecond = $this->insertImage($soft, 'universe-images/c.jpg');

        $migration->up();

        $this->assertDatabaseHas('universe_images', ['id' => $softFirst, 'position' => 1]);
        $this->assertDatabaseHas('universe_images', ['id' => $softSecond, 'position' => 2]);
        $this->assertDatabaseHas('universe_images', ['id' => $streetFirst, 'position' => 1]);
    }
}
