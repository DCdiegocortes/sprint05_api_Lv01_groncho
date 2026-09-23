<?php

namespace Tests\Feature\Items;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ItemCreateTest extends TestCase
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

    public function test_authenticated_user_can_create_an_item(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/items', [
                'name' => 'Denim Jacket',
                'description' => 'Lightly used',
                'size' => 'M',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('items', [
            'user_id' => $user->id,
            'name' => 'Denim Jacket',
            'status' => 'AVAILABLE',
        ]);
    }

    public function test_creating_item_requires_authentication(): void
    {
        $response = $this->postJson('/api/items', ['name' => 'Denim Jacket']);

        $response->assertStatus(401);
    }

    public function test_creating_item_requires_name(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/items', ['size' => 'M']);

        $response->assertStatus(422);
    }

    public function test_user_can_create_more_than_one_item(): void
    {
        $user = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/items', ['name' => 'First Item']);
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/items', ['name' => 'Second Item']);

        $response->assertStatus(201);
        $this->assertDatabaseCount('items', 2);
    }

    public function test_item_is_always_created_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/items', [
                'name' => 'Denim Jacket',
                'user_id' => $otherUser->id,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('items', [
            'name' => 'Denim Jacket',
            'user_id' => $user->id,
        ]);
    }

    public function test_item_status_cannot_be_set_via_request(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson('/api/items', [
                'name' => 'Denim Jacket',
                'status' => 'UNAVAILABLE',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('items', [
            'name' => 'Denim Jacket',
            'status' => 'AVAILABLE',
        ]);
    }
}
