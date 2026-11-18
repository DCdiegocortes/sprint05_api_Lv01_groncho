<?php

namespace Tests\Feature\Notifications;

use App\Enums\ExchangeStatus;
use App\Enums\ItemStatus;
use App\Models\Exchange;
use App\Models\Item;
use App\Models\MatchModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ExchangeNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $requester;

    private MatchModel $match;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Test Personal Access Client'
        );

        $this->owner = User::factory()->create();
        $this->requester = User::factory()->create();
        $this->match = MatchModel::factory()->create([
            'user_one_id' => $this->owner->id,
            'user_two_id' => $this->requester->id,
        ]);
        $this->item = Item::factory()->create(['user_id' => $this->owner->id, 'name' => 'Silver moto boots']);
    }

    private function asUser(User $user)
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->accessToken);
    }

    private function notificationsOf(User $user): Collection
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->get()
            ->map(fn ($row) => ['type' => $row->type, ...json_decode($row->data, true)]);
    }

    private function exchange(array $attributes = []): Exchange
    {
        return Exchange::factory()->create([
            'match_id' => $this->match->id,
            'requester_id' => $this->requester->id,
            'requested_item_id' => $this->item->id,
            ...$attributes,
        ]);
    }

    public function test_the_item_owner_is_notified_of_a_new_request(): void
    {
        $response = $this->asUser($this->requester)->postJson('/api/exchanges', [
            'match_id' => $this->match->id,
            'requested_item_id' => $this->item->id,
            'type' => 'GIFT',
        ]);

        $response->assertStatus(201);
        $this->assertEquals([[
            'type' => 'EXCHANGE_REQUESTED',
            'actor_id' => $this->requester->id,
            'exchange_id' => $response->json('id'),
            'item_name' => 'Silver moto boots',
            'exchange_type' => 'GIFT',
        ]], $this->notificationsOf($this->owner)->all());
        $this->assertCount(0, $this->notificationsOf($this->requester));
    }

    public function test_the_requester_is_notified_when_the_request_is_accepted(): void
    {
        $exchange = $this->exchange();

        $this->asUser($this->owner)->putJson("/api/exchanges/{$exchange->id}", ['status' => 'ACCEPTED'])->assertStatus(200);

        $this->assertEquals([[
            'type' => 'EXCHANGE_ACCEPTED',
            'actor_id' => $this->owner->id,
            'exchange_id' => $exchange->id,
            'item_name' => 'Silver moto boots',
            'exchange_type' => 'GIFT',
        ]], $this->notificationsOf($this->requester)->all());
        $this->assertCount(0, $this->notificationsOf($this->owner));
    }

    public function test_the_requester_is_notified_when_the_request_is_declined(): void
    {
        $exchange = $this->exchange();

        $this->asUser($this->owner)->putJson("/api/exchanges/{$exchange->id}", ['status' => 'REJECTED'])->assertStatus(200);

        $this->assertSame(['EXCHANGE_REJECTED'], $this->notificationsOf($this->requester)->pluck('type')->all());
        $this->assertSame($this->owner->id, $this->notificationsOf($this->requester)->first()['actor_id']);
    }

    public function test_the_requester_is_notified_when_the_exchange_is_finished(): void
    {
        $exchange = $this->exchange(['status' => ExchangeStatus::ACCEPTED]);
        $this->item->update(['status' => ItemStatus::UNAVAILABLE]);

        $this->asUser($this->owner)->putJson("/api/exchanges/{$exchange->id}", ['status' => 'FINISHED'])->assertStatus(200);

        $this->assertSame(['EXCHANGE_FINISHED'], $this->notificationsOf($this->requester)->pluck('type')->all());
        $this->assertSame('Silver moto boots', $this->notificationsOf($this->requester)->first()['item_name']);
    }

    public function test_other_pending_requests_declined_by_an_acceptance_are_notified(): void
    {
        $exchange = $this->exchange();
        $otherRequester = User::factory()->create();
        $otherMatch = MatchModel::factory()->create(['user_one_id' => $this->owner->id, 'user_two_id' => $otherRequester->id]);
        $otherExchange = Exchange::factory()->create([
            'match_id' => $otherMatch->id,
            'requester_id' => $otherRequester->id,
            'requested_item_id' => $this->item->id,
        ]);

        $this->asUser($this->owner)->putJson("/api/exchanges/{$exchange->id}", ['status' => 'ACCEPTED'])->assertStatus(200);

        $this->assertEquals([[
            'type' => 'EXCHANGE_REJECTED',
            'actor_id' => $this->owner->id,
            'exchange_id' => $otherExchange->id,
            'item_name' => 'Silver moto boots',
            'exchange_type' => 'GIFT',
        ]], $this->notificationsOf($otherRequester)->all());
    }

    public function test_the_item_owner_is_notified_when_the_requester_cancels(): void
    {
        $exchange = $this->exchange();

        $this->asUser($this->requester)->deleteJson("/api/exchanges/{$exchange->id}")->assertStatus(200);

        $this->assertEquals([[
            'type' => 'EXCHANGE_CANCELLED',
            'actor_id' => $this->requester->id,
            'exchange_id' => $exchange->id,
            'item_name' => 'Silver moto boots',
            'exchange_type' => 'GIFT',
        ]], $this->notificationsOf($this->owner)->all());
        $this->assertCount(0, $this->notificationsOf($this->requester));
    }

    public function test_an_admin_cancelling_an_exchange_does_not_notify_as_a_cancellation_by_the_requester(): void
    {
        $exchange = $this->exchange();

        $this->asUser(User::factory()->create(['role' => 'admin']))->deleteJson("/api/exchanges/{$exchange->id}")->assertStatus(200);

        $this->assertCount(0, $this->notificationsOf($this->owner));
        $this->assertCount(0, $this->notificationsOf($this->requester));
    }
}
