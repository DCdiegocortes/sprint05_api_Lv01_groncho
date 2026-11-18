<?php

namespace Tests\Feature\Universes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UniverseStyleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migration_converts_the_existing_styles_to_uppercase(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertExitCode(0);
        $user = User::factory()->create();
        DB::table('universes')->insert([
            'user_id' => $user->id,
            'name' => 'Existing universe',
            'style' => 'minimal, neutral',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('migrate')->assertExitCode(0);

        $this->assertDatabaseHas('universes', ['user_id' => $user->id, 'style' => 'MINIMAL, NEUTRAL']);
    }

    public function test_the_migration_keeps_an_empty_style_untouched(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertExitCode(0);
        $user = User::factory()->create();
        DB::table('universes')->insert([
            'user_id' => $user->id,
            'name' => 'Existing universe',
            'style' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('migrate')->assertExitCode(0);

        $this->assertDatabaseHas('universes', ['user_id' => $user->id, 'style' => null, 'description' => null]);
    }
}
