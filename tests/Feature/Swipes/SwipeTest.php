<?php

namespace Tests\Feature\Swipes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class SwipeTest extends TestCase
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

    public function test_authenticated_user_can_swipe_another_user(): void
    {
        $swiper = User::factory()->create();
        $target = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($swiper))
            ->postJson('/api/swipes', [
                'target_user_id' => $target->id,
                'liked' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('swipes', [
            'swiper_user_id' => $swiper->id,
            'target_user_id' => $target->id,
            'liked' => true,
        ]);
    }

    public function test_swiping_requires_authentication(): void
    {
        $target = User::factory()->create();

        $response = $this->postJson('/api/swipes', [
            'target_user_id' => $target->id,
            'liked' => true,
        ]);

        $response->assertStatus(401);
    }

    public function test_user_cannot_swipe_themselves(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/swipes', [
                'target_user_id' => $user->id,
                'liked' => true,
            ]);

        $response->assertStatus(422);
    }

    public function test_user_cannot_swipe_the_same_target_twice(): void
    {
        $swiper = User::factory()->create();
        $target = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($swiper))
            ->postJson('/api/swipes', ['target_user_id' => $target->id, 'liked' => true]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($swiper))
            ->postJson('/api/swipes', ['target_user_id' => $target->id, 'liked' => false]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('swipes', 1);
    }

    public function test_mutual_like_creates_a_match(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($userA))
            ->postJson('/api/swipes', ['target_user_id' => $userB->id, 'liked' => true])
            ->assertStatus(201);

        $this->assertDatabaseCount('matches', 0);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($userB))
            ->postJson('/api/swipes', ['target_user_id' => $userA->id, 'liked' => true])
            ->assertStatus(201);

        $this->assertDatabaseCount('matches', 1);
        $this->assertDatabaseHas('matches', ['status' => 'ACTIVE']);
    }

    public function test_one_sided_like_does_not_create_a_match(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($userA))
            ->postJson('/api/swipes', ['target_user_id' => $userB->id, 'liked' => true]);

        $this->assertDatabaseCount('matches', 0);
    }

    public function test_dislike_does_not_create_a_match_even_if_mutual(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($userA))
            ->postJson('/api/swipes', ['target_user_id' => $userB->id, 'liked' => false]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($userB))
            ->postJson('/api/swipes', ['target_user_id' => $userA->id, 'liked' => false]);

        $this->assertDatabaseCount('matches', 0);
    }
}
