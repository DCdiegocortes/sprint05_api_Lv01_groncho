<?php

namespace Tests\Feature\Swipes;

use App\Models\MatchModel;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class SwipeMatchedTest extends TestCase
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

    private function swipe(User $swiper, User $target, bool $liked)
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($swiper))
            ->postJson('/api/swipes', [
                'target_user_id' => $target->id,
                'liked' => $liked,
            ]);
    }

    public function test_a_mutual_like_answers_matched_true_with_the_match_id(): void
    {
        $swiper = User::factory()->create();
        $target = User::factory()->create();
        Swipe::factory()->create([
            'swiper_user_id' => $target->id,
            'target_user_id' => $swiper->id,
            'liked' => true,
        ]);

        $response = $this->swipe($swiper, $target, true);

        $response->assertStatus(201);
        $response->assertJsonPath('matched', true);
        $response->assertJsonPath('match_id', MatchModel::first()->id);
    }

    public function test_a_one_sided_like_answers_matched_false_without_a_match_id(): void
    {
        $swiper = User::factory()->create();
        $target = User::factory()->create();

        $response = $this->swipe($swiper, $target, true);

        $response->assertStatus(201);
        $response->assertJsonPath('matched', false);
        $response->assertJsonPath('match_id', null);
    }

    public function test_a_dislike_answers_matched_false_without_a_match_id(): void
    {
        $swiper = User::factory()->create();
        $target = User::factory()->create();
        Swipe::factory()->create([
            'swiper_user_id' => $target->id,
            'target_user_id' => $swiper->id,
            'liked' => true,
        ]);

        $response = $this->swipe($swiper, $target, false);

        $response->assertStatus(201);
        $response->assertJsonPath('matched', false);
        $response->assertJsonPath('match_id', null);
    }

    public function test_the_response_does_not_expose_the_target_user(): void
    {
        $swiper = User::factory()->create();
        $target = User::factory()->create();

        $response = $this->swipe($swiper, $target, true);

        $response->assertStatus(201);
        $response->assertJsonMissingPath('target');
        $response->assertJsonMissing(['email' => $target->email]);
    }

    public function test_the_response_does_not_expose_the_target_user_on_a_match(): void
    {
        $swiper = User::factory()->create();
        $target = User::factory()->create();
        Swipe::factory()->create([
            'swiper_user_id' => $target->id,
            'target_user_id' => $swiper->id,
            'liked' => true,
        ]);

        $response = $this->swipe($swiper, $target, true);

        $response->assertStatus(201);
        $response->assertJsonMissingPath('target');
        $response->assertJsonMissing(['email' => $target->email]);
    }
}
