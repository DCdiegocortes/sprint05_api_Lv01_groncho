<?php

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ItemEditTest extends TestCase
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

    public function test_owner_can_update_their_item(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/items/{$item->id}", ['name' => 'Updated Name']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'name' => 'Updated Name']);
    }

    public function test_admin_can_update_any_item(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = Item::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->putJson("/api/items/{$item->id}", ['name' => 'Updated By Admin']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'name' => 'Updated By Admin']);
    }

    public function test_other_user_cannot_update_someone_elses_item(): void
    {
        $intruder = User::factory()->create();
        $item = Item::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->putJson("/api/items/{$item->id}", ['name' => 'Hacked']);

        $response->assertStatus(403);
    }

    public function test_updating_item_requires_authentication(): void
    {
        $item = Item::factory()->create();

        $response = $this->putJson("/api/items/{$item->id}", ['name' => 'Nope']);

        $response->assertStatus(401);
    }

    public function test_item_status_cannot_be_changed_via_update(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->putJson("/api/items/{$item->id}", ['status' => 'UNAVAILABLE']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'status' => 'AVAILABLE']);
    }

    public function test_owner_can_delete_their_item(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->deleteJson("/api/items/{$item->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_admin_can_delete_any_item(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = Item::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->deleteJson("/api/items/{$item->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_other_user_cannot_delete_someone_elses_item(): void
    {
        $intruder = User::factory()->create();
        $item = Item::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->deleteJson("/api/items/{$item->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('items', ['id' => $item->id]);
    }

    public function test_deleting_item_requires_authentication(): void
    {
        $item = Item::factory()->create();

        $response = $this->deleteJson("/api/items/{$item->id}");

        $response->assertStatus(401);
    }
}
