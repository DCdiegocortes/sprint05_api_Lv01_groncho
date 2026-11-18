<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Test Personal Access Client'
        );
    }

    private function asUser(User $user)
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->accessToken);
    }

    private function notification(User $user, string $type, array $data = [], ?string $readAt = null, ?string $createdAt = null): string
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode($data),
            'read_at' => $readAt,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $createdAt ?? now(),
        ]);

        return $id;
    }

    public function test_a_user_sees_their_notifications_newest_first_with_the_actor(): void
    {
        $user = User::factory()->create();
        $actor = User::factory()->create(['name' => 'Kaeru']);
        $older = $this->notification($user, 'MATCH_CREATED', ['actor_id' => $actor->id, 'match_id' => 7], null, now()->subHour());
        $newer = $this->notification($user, 'EXCHANGE_REQUESTED', [
            'actor_id' => $actor->id,
            'exchange_id' => 3,
            'item_name' => 'Silver moto boots',
            'exchange_type' => 'TRADE',
        ]);

        $response = $this->asUser($user)->getJson('/api/notifications');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
        $response->assertJsonPath('0.id', $newer);
        $response->assertJsonPath('0.type', 'EXCHANGE_REQUESTED');
        $response->assertJsonPath('0.read_at', null);
        $response->assertJsonPath('0.actor.id', $actor->id);
        $response->assertJsonPath('0.actor.handle', 'kaeru');
        $response->assertJsonPath('0.exchange_id', 3);
        $response->assertJsonPath('0.item_name', 'Silver moto boots');
        $response->assertJsonPath('0.exchange_type', 'TRADE');
        $response->assertJsonMissingPath('0.actor_id');
        $response->assertJsonMissingPath('0.actor.email');
        $response->assertJsonPath('1.id', $older);
        $response->assertJsonPath('1.match_id', 7);
    }

    public function test_a_moderation_notification_has_no_actor(): void
    {
        $user = User::factory()->create();
        $this->notification($user, 'UNIVERSE_PHOTO_REMOVED', ['universe_id' => 4]);

        $response = $this->asUser($user)->getJson('/api/notifications');

        $response->assertJsonPath('0.type', 'UNIVERSE_PHOTO_REMOVED');
        $response->assertJsonPath('0.actor', null);
    }

    public function test_a_user_does_not_see_notifications_of_others(): void
    {
        $user = User::factory()->create();
        $this->notification(User::factory()->create(), 'MATCH_CREATED');

        $this->asUser($user)->getJson('/api/notifications')->assertExactJson([]);
    }

    public function test_the_unread_count_only_counts_unread_notifications(): void
    {
        $user = User::factory()->create();
        $this->notification($user, 'MATCH_CREATED');
        $this->notification($user, 'MATCH_CREATED');
        $this->notification($user, 'MATCH_CREATED', [], now()->toDateTimeString());
        $this->notification(User::factory()->create(), 'MATCH_CREATED');

        $this->asUser($user)->getJson('/api/notifications/unread-count')->assertExactJson(['unread' => 2]);
    }

    public function test_a_user_can_mark_one_notification_as_read(): void
    {
        $user = User::factory()->create();
        $id = $this->notification($user, 'MATCH_CREATED');
        $other = $this->notification($user, 'MATCH_CREATED');

        $response = $this->asUser($user)->patchJson("/api/notifications/{$id}/read");

        $response->assertStatus(200);
        $this->assertNotNull($response->json('read_at'));
        $this->assertNotNull(DB::table('notifications')->find($id)->read_at);
        $this->assertNull(DB::table('notifications')->find($other)->read_at);
    }

    public function test_a_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $this->notification($user, 'MATCH_CREATED');
        $this->notification($user, 'EXCHANGE_REQUESTED');
        $foreign = $this->notification(User::factory()->create(), 'MATCH_CREATED');

        $response = $this->asUser($user)->postJson('/api/notifications/read-all');

        $response->assertStatus(200);
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $user->id)->whereNull('read_at')->count());
        $this->assertNull(DB::table('notifications')->find($foreign)->read_at);
    }

    public function test_a_user_can_delete_a_notification(): void
    {
        $user = User::factory()->create();
        $id = $this->notification($user, 'MATCH_CREATED');

        $this->asUser($user)->deleteJson("/api/notifications/{$id}")->assertStatus(200);

        $this->assertDatabaseMissing('notifications', ['id' => $id]);
    }

    public function test_a_user_cannot_mark_as_read_a_notification_of_someone_else(): void
    {
        $foreign = $this->notification(User::factory()->create(), 'MATCH_CREATED');

        $this->asUser(User::factory()->create())->patchJson("/api/notifications/{$foreign}/read")->assertStatus(404);

        $this->assertNull(DB::table('notifications')->find($foreign)->read_at);
    }

    public function test_a_user_cannot_delete_a_notification_of_someone_else(): void
    {
        $foreign = $this->notification(User::factory()->create(), 'MATCH_CREATED');

        $this->asUser(User::factory()->create())->deleteJson("/api/notifications/{$foreign}")->assertStatus(404);

        $this->assertDatabaseHas('notifications', ['id' => $foreign]);
    }

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/notifications')->assertStatus(401);
    }
}
