<?php

namespace Tests\Feature\Universes;

use App\Models\Universe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UniverseDecorationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Test Personal Access Client'
        );
    }

    private function ownUniverse(array $attributes = []): array
    {
        $user = User::factory()->create();

        return [$user, Universe::factory()->create(['user_id' => $user->id, ...$attributes])];
    }

    private function saveDecorations(User $user, Universe $universe, mixed $decorations)
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->accessToken)
            ->putJson("/api/universes/{$universe->id}", ['decorations' => $decorations]);
    }

    private function emoji(array $overrides = []): array
    {
        return ['type' => 'EMOJI', 'emoji' => '🖤', 'x' => 70, 'y' => 85, 'size' => 12, ...$overrides];
    }

    private function sticker(array $overrides = []): array
    {
        return ['type' => 'STICKER', 'sticker' => 'UFO', 'x' => 40, 'y' => 12, 'size' => 20, ...$overrides];
    }

    private function text(array $overrides = []): array
    {
        return [
            'type' => 'TEXT',
            'text' => 'quiet armour.',
            'font' => 'ITALIANA',
            'color' => 'BLACK',
            'x' => 5,
            'y' => 30,
            'size' => 18,
            ...$overrides,
        ];
    }

    public function test_the_owner_can_save_emojis_and_texts(): void
    {
        [$user, $universe] = $this->ownUniverse();

        $response = $this->saveDecorations($user, $universe, [$this->emoji(), $this->text()]);

        $response->assertStatus(200);
        $response->assertJsonPath('decorations', [$this->emoji(), $this->text()]);
        $this->assertSame([$this->emoji(), $this->text()], $universe->fresh()->decorations);
    }

    public function test_the_owner_can_place_any_of_the_eight_stickers(): void
    {
        [$user, $universe] = $this->ownUniverse();

        foreach (['UFO', 'JP_LETS_SWAP', 'GAME_OVER', 'CD', 'LIFE_HEARTS', 'CARD', 'STAR', 'HEADPHONES'] as $sticker) {
            $response = $this->saveDecorations($user, $universe, [$this->sticker(['sticker' => $sticker])]);

            $response->assertStatus(200);
            $response->assertJsonPath('decorations', [$this->sticker(['sticker' => $sticker])]);
        }
    }

    public function test_an_unknown_sticker_is_rejected(): void
    {
        [$user, $universe] = $this->ownUniverse();

        foreach (['HEART', 'NO_MONEY', null] as $sticker) {
            $response = $this->saveDecorations($user, $universe, [$this->sticker(['sticker' => $sticker])]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors('decorations.0.sticker');
        }
    }

    public function test_stickers_emojis_and_texts_can_be_mixed(): void
    {
        [$user, $universe] = $this->ownUniverse();

        $response = $this->saveDecorations($user, $universe, [$this->sticker(), $this->emoji(), $this->text()]);

        $response->assertStatus(200);
        $this->assertSame([$this->sticker(), $this->emoji(), $this->text()], $universe->fresh()->decorations);
    }

    public function test_the_suggested_emojis_from_the_design_are_accepted(): void
    {
        [$user, $universe] = $this->ownUniverse();

        foreach (['🖤', '✨', '🪩', '🥋', '🗡️', '🫀', '🧷', '🌙'] as $emoji) {
            $this->saveDecorations($user, $universe, [$this->emoji(['emoji' => $emoji])])->assertStatus(200);
        }
    }

    public function test_positions_and_sizes_can_have_decimals(): void
    {
        [$user, $universe] = $this->ownUniverse();

        $response = $this->saveDecorations($user, $universe, [$this->emoji(['x' => 12.5, 'y' => 1.75, 'size' => 8.25])]);

        $response->assertStatus(200);
        $response->assertJsonPath('decorations.0.x', 12.5);
        $response->assertJsonPath('decorations.0.y', 1.75);
        $response->assertJsonPath('decorations.0.size', 8.25);
    }

    public function test_positions_and_sizes_sent_as_text_are_saved_as_numbers(): void
    {
        [$user, $universe] = $this->ownUniverse();

        $response = $this->saveDecorations($user, $universe, [$this->emoji(['x' => '70', 'y' => '1.5', 'size' => '12'])]);

        $response->assertStatus(200);
        $response->assertJsonPath('decorations.0.x', 70);
        $response->assertJsonPath('decorations.0.y', 1.5);
        $response->assertJsonPath('decorations.0.size', 12);
    }

    public function test_any_keyboard_emoji_is_accepted(): void
    {
        [$user, $universe] = $this->ownUniverse();

        foreach (['🍕', '❤️', '👩‍🎤', '🇦🇷', '👍🏽', '🏴󠁧󠁢󠁥󠁮󠁧󠁿'] as $emoji) {
            $response = $this->saveDecorations($user, $universe, [$this->emoji(['emoji' => $emoji])]);

            $response->assertStatus(200);
            $response->assertJsonPath('decorations.0.emoji', $emoji);
        }
    }

    public function test_an_emoji_decoration_only_accepts_a_single_emoji(): void
    {
        [$user, $universe] = $this->ownUniverse();

        foreach (['a', 'hello', '7', '🖤🖤', '🖤a', ''] as $notAnEmoji) {
            $response = $this->saveDecorations($user, $universe, [$this->emoji(['emoji' => $notAnEmoji])]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors('decorations.0.emoji');
        }
    }

    public function test_a_text_must_use_one_of_the_five_fonts(): void
    {
        [$user, $universe] = $this->ownUniverse();

        foreach (['ITALIANA', 'INSTRUMENT', 'FRAUNCES', 'DM_MONO', 'BODONI'] as $font) {
            $this->saveDecorations($user, $universe, [$this->text(['font' => $font])])->assertStatus(200);
        }

        $response = $this->saveDecorations($user, $universe, [$this->text(['font' => 'COMIC_SANS'])]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('decorations.0.font');
    }

    public function test_a_text_must_use_one_of_the_three_colors(): void
    {
        [$user, $universe] = $this->ownUniverse();

        foreach (['BLACK', 'PINK', 'WHITE'] as $color) {
            $this->saveDecorations($user, $universe, [$this->text(['color' => $color])])->assertStatus(200);
        }

        $response = $this->saveDecorations($user, $universe, [$this->text(['color' => '#FFFF00'])]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('decorations.0.color');
    }

    public function test_a_text_needs_content_of_at_most_a_hundred_characters(): void
    {
        [$user, $universe] = $this->ownUniverse();

        $this->saveDecorations($user, $universe, [$this->text(['text' => str_repeat('a', 100)])])->assertStatus(200);

        foreach (['', str_repeat('a', 101)] as $text) {
            $response = $this->saveDecorations($user, $universe, [$this->text(['text' => $text])]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors('decorations.0.text');
        }
    }

    public function test_unknown_decoration_types_are_rejected(): void
    {
        [$user, $universe] = $this->ownUniverse();

        foreach (['IMAGE', 'VIDEO'] as $type) {
            $response = $this->saveDecorations($user, $universe, [$this->emoji(['type' => $type])]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors('decorations.0.type');
        }
    }

    public function test_the_horizontal_position_stays_inside_the_universe(): void
    {
        [$user, $universe] = $this->ownUniverse();

        foreach ([0, 100] as $x) {
            $this->saveDecorations($user, $universe, [$this->emoji(['x' => $x])])->assertStatus(200);
        }

        foreach ([-1, 100.5] as $x) {
            $response = $this->saveDecorations($user, $universe, [$this->emoji(['x' => $x])]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors('decorations.0.x');
        }
    }

    public function test_the_vertical_position_cannot_be_negative(): void
    {
        [$user, $universe] = $this->ownUniverse();

        $this->saveDecorations($user, $universe, [$this->emoji(['y' => 0])])->assertStatus(200);

        $response = $this->saveDecorations($user, $universe, [$this->emoji(['y' => -0.5])]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('decorations.0.y');
    }

    public function test_the_size_goes_from_one_to_a_hundred(): void
    {
        [$user, $universe] = $this->ownUniverse();

        foreach ([1, 100] as $size) {
            $this->saveDecorations($user, $universe, [$this->text(['size' => $size])])->assertStatus(200);
        }

        foreach ([0, 101] as $size) {
            $response = $this->saveDecorations($user, $universe, [$this->text(['size' => $size])]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors('decorations.0.size');
        }
    }

    public function test_positions_and_sizes_are_required_numbers(): void
    {
        [$user, $universe] = $this->ownUniverse();

        $response = $this->saveDecorations($user, $universe, [['type' => 'EMOJI', 'emoji' => '🖤', 'x' => 'left']]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['decorations.0.x', 'decorations.0.y', 'decorations.0.size']);
    }

    public function test_a_universe_can_have_up_to_fifty_decorations(): void
    {
        [$user, $universe] = $this->ownUniverse();

        $this->saveDecorations($user, $universe, array_fill(0, 50, $this->emoji()))->assertStatus(200);

        $response = $this->saveDecorations($user, $universe, array_fill(0, 51, $this->emoji()));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('decorations');
    }

    public function test_fields_that_do_not_belong_to_the_decoration_are_not_saved(): void
    {
        [$user, $universe] = $this->ownUniverse();

        $response = $this->saveDecorations($user, $universe, [
            $this->emoji(['font' => 'ITALIANA', 'rotation' => 45]),
            $this->text(['emoji' => '🖤', 'onclick' => 'alert(1)']),
            $this->sticker(['emoji' => '🖤', 'text' => 'hello', 'color' => 'PINK']),
        ]);

        $response->assertStatus(200);
        $this->assertSame([$this->emoji(), $this->text(), $this->sticker()], $universe->fresh()->decorations);
    }

    public function test_sending_an_empty_list_removes_every_decoration(): void
    {
        [$user, $universe] = $this->ownUniverse(['decorations' => [$this->emoji()]]);

        $response = $this->saveDecorations($user, $universe, []);

        $response->assertStatus(200);
        $response->assertJsonPath('decorations', []);
    }

    public function test_updating_other_fields_keeps_the_decorations(): void
    {
        [$user, $universe] = $this->ownUniverse(['decorations' => [$this->emoji()]]);

        $response = $this->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->accessToken)
            ->putJson("/api/universes/{$universe->id}", ['name' => 'Renamed']);

        $response->assertStatus(200);
        $response->assertJsonPath('decorations', [$this->emoji()]);
    }

    public function test_another_user_cannot_change_the_decorations(): void
    {
        $universe = Universe::factory()->create();

        $response = $this->saveDecorations(User::factory()->create(), $universe, [$this->emoji()]);

        $response->assertStatus(403);
        $this->assertNull($universe->fresh()->decorations);
    }

    public function test_viewing_a_universe_shows_its_decorations(): void
    {
        $universe = Universe::factory()->create(['decorations' => [$this->text()]]);

        $response = $this->withHeader('Authorization', 'Bearer '.User::factory()->create()->createToken('test-token')->accessToken)
            ->getJson("/api/universes/{$universe->id}");

        $response->assertJsonPath('decorations', [$this->text()]);
    }
}
