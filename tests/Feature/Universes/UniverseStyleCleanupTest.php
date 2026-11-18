<?php

namespace Tests\Feature\Universes;

use App\Models\Universe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UniverseStyleCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Test Personal Access Client'
        );
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test-token')->accessToken;
    }

    private function createWithStyle(mixed $style)
    {
        $user = User::factory()->create();

        return $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/universes', ['name' => 'My Universe', 'style' => $style]);
    }

    private function updateWithData(array $data, ?string $style = 'MINIMAL')
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id, 'style' => $style]);

        return $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/universes/{$universe->id}", $data);
    }

    public function test_styles_are_trimmed_uppercased_and_deduplicated_when_creating(): void
    {
        $response = $this->createWithStyle(' minimal , Minimal,, boho ');

        $response->assertStatus(201);
        $response->assertJsonPath('style', 'MINIMAL, BOHO');
    }

    public function test_accented_styles_are_uppercased_correctly(): void
    {
        $response = $this->createWithStyle('rosé, éclectic');

        $response->assertStatus(201);
        $response->assertJsonPath('style', 'ROSÉ, ÉCLECTIC');
    }

    public function test_duplicates_are_detected_regardless_of_case_for_accented_styles(): void
    {
        $response = $this->createWithStyle('éclectic, ÉCLECTIC');

        $response->assertStatus(201);
        $response->assertJsonPath('style', 'ÉCLECTIC');
    }

    public function test_styles_with_a_slash_are_kept_as_one_style(): void
    {
        $response = $this->createWithStyle('minimal / zen, y2k / pop');

        $response->assertStatus(201);
        $response->assertJsonPath('style', 'MINIMAL / ZEN, Y2K / POP');
    }

    public function test_a_style_made_only_of_separators_is_stored_as_empty(): void
    {
        $response = $this->createWithStyle(' , , ');

        $response->assertStatus(201);
        $response->assertJsonPath('style', null);
    }

    public function test_a_cleaned_style_of_255_characters_is_accepted(): void
    {
        $response = $this->createWithStyle(str_repeat('a', 255));

        $response->assertStatus(201);
    }

    public function test_a_cleaned_style_over_255_characters_is_rejected(): void
    {
        $response = $this->createWithStyle(str_repeat('a', 256));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('style');
    }

    public function test_the_length_is_counted_after_the_cleanup(): void
    {
        $response = $this->createWithStyle('minimal'.str_repeat(' ', 300).', boho');

        $response->assertStatus(201);
        $response->assertJsonPath('style', 'MINIMAL, BOHO');
    }

    public function test_a_style_that_is_not_text_is_rejected(): void
    {
        $response = $this->createWithStyle(['minimal']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('style');
    }

    public function test_styles_are_cleaned_when_updating(): void
    {
        $response = $this->updateWithData(['style' => ' dark , DARK, gothic ']);

        $response->assertStatus(200);
        $response->assertJsonPath('style', 'DARK, GOTHIC');
    }

    public function test_updating_without_a_style_keeps_the_current_one(): void
    {
        $response = $this->updateWithData(['name' => 'Renamed'], 'MINIMAL');

        $response->assertStatus(200);
        $response->assertJsonPath('style', 'MINIMAL');
    }

    public function test_the_style_can_be_removed_when_updating(): void
    {
        $response = $this->updateWithData(['style' => null], 'MINIMAL');

        $response->assertStatus(200);
        $response->assertJsonPath('style', null);
    }
}
