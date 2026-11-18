<?php

namespace Tests\Feature\Notifications;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\Universe;
use App\Models\UniverseImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ModerationNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Test Personal Access Client'
        );

        Storage::fake('public');
    }

    private function asUser(User $user)
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->accessToken);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function notificationsOf(User $user): Collection
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->get()
            ->map(fn ($row) => ['type' => $row->type, ...json_decode($row->data, true)]);
    }

    public function test_the_owner_is_notified_when_a_moderator_removes_a_universe_photo(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id]);
        $image = UniverseImage::factory()->create(['universe_id' => $universe->id]);

        $this->asUser($this->admin())->deleteJson("/api/universes/{$universe->id}/images/{$image->id}")->assertStatus(200);

        $this->assertEquals(
            [['type' => 'UNIVERSE_PHOTO_REMOVED', 'universe_id' => $universe->id]],
            $this->notificationsOf($owner)->all()
        );
    }

    public function test_removing_your_own_universe_photo_does_not_notify(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id]);
        $image = UniverseImage::factory()->create(['universe_id' => $universe->id]);

        $this->asUser($owner)->deleteJson("/api/universes/{$universe->id}/images/{$image->id}")->assertStatus(200);

        $this->assertCount(0, $this->notificationsOf($owner));
    }

    public function test_the_owner_is_notified_when_a_moderator_removes_a_piece_photo(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id, 'name' => 'Melted chrome bag']);
        $image = ItemImage::factory()->create(['item_id' => $item->id]);

        $this->asUser($this->admin())->deleteJson("/api/items/{$item->id}/images/{$image->id}")->assertStatus(200);

        $this->assertEquals(
            [['type' => 'ITEM_PHOTO_REMOVED', 'item_id' => $item->id, 'item_name' => 'Melted chrome bag']],
            $this->notificationsOf($owner)->all()
        );
    }

    public function test_removing_your_own_piece_photo_does_not_notify(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);
        $image = ItemImage::factory()->create(['item_id' => $item->id]);

        $this->asUser($owner)->deleteJson("/api/items/{$item->id}/images/{$image->id}")->assertStatus(200);

        $this->assertCount(0, $this->notificationsOf($owner));
    }

    public function test_the_owner_is_notified_when_a_moderator_removes_a_whole_piece(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id, 'name' => 'Liquid metal trousers']);

        $this->asUser($this->admin())->deleteJson("/api/items/{$item->id}")->assertStatus(200);

        $this->assertEquals(
            [['type' => 'ITEM_REMOVED', 'item_name' => 'Liquid metal trousers']],
            $this->notificationsOf($owner)->all()
        );
    }

    public function test_removing_your_own_piece_does_not_notify(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);

        $this->asUser($owner)->deleteJson("/api/items/{$item->id}")->assertStatus(200);

        $this->assertCount(0, $this->notificationsOf($owner));
    }
}
