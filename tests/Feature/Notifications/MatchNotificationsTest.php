<?php

namespace Tests\Feature\Notifications;

use App\Models\MatchModel;
use App\Models\Swipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class MatchNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Test Personal Access Client'
        );
    }

    private function swipe(User $swiper, User $target, bool $liked)
    {
        return $this->withHeader('Authorization', 'Bearer '.$swiper->createToken('test-token')->accessToken)
            ->postJson('/api/swipes', ['target_user_id' => $target->id, 'liked' => $liked]);
    }

    private function notificationsOf(User $user): Collection
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->get()
            ->map(fn ($row) => ['type' => $row->type, ...json_decode($row->data, true)]);
    }

    public function test_both_users_are_notified_of_a_new_match(): void
    {
        $swiper = User::factory()->create();
        $target = User::factory()->create();
        Swipe::factory()->create(['swiper_user_id' => $target->id, 'target_user_id' => $swiper->id, 'liked' => true]);

        $this->swipe($swiper, $target, true)->assertStatus(201);

        $matchId = MatchModel::first()->id;
        $this->assertEquals(
            [['type' => 'MATCH_CREATED', 'actor_id' => $target->id, 'match_id' => $matchId]],
            $this->notificationsOf($swiper)->all()
        );
        $this->assertEquals(
            [['type' => 'MATCH_CREATED', 'actor_id' => $swiper->id, 'match_id' => $matchId]],
            $this->notificationsOf($target)->all()
        );
    }

    public function test_a_one_sided_like_does_not_notify(): void
    {
        $swiper = User::factory()->create();
        $target = User::factory()->create();

        $this->swipe($swiper, $target, true)->assertStatus(201);

        $this->assertCount(0, $this->notificationsOf($swiper));
        $this->assertCount(0, $this->notificationsOf($target));
    }

    public function test_an_existing_match_is_not_notified_again(): void
    {
        $swiper = User::factory()->create();
        $target = User::factory()->create();
        Swipe::factory()->create(['swiper_user_id' => $target->id, 'target_user_id' => $swiper->id, 'liked' => true]);
        MatchModel::factory()->create([
            'user_one_id' => min($swiper->id, $target->id),
            'user_two_id' => max($swiper->id, $target->id),
        ]);

        $this->swipe($swiper, $target, true)->assertStatus(201);

        $this->assertCount(0, $this->notificationsOf($swiper));
        $this->assertCount(0, $this->notificationsOf($target));
    }
}
