<?php

namespace Tests\Feature\Seeders;

use App\Models\Universe;
use App\Services\StyleNormalizer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniverseSeederStyleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeded_universe_styles_are_already_clean_and_uppercase(): void
    {
        $this->seed(DatabaseSeeder::class);

        $universes = Universe::all();

        $this->assertNotEmpty($universes);

        foreach ($universes as $universe) {
            $this->assertSame(
                app(StyleNormalizer::class)->normalize($universe->style),
                $universe->style
            );
        }
    }
}
