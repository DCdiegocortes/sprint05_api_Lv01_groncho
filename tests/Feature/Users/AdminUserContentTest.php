<?php

namespace Tests\Feature\Users;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\Universe;
use App\Models\UniverseImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class AdminUserContentTest extends TestCase
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

    public function test_the_admin_sees_the_user_with_the_universe_and_its_photos_in_order(): void
    {
        $user = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $user->id, 'style' => 'CHROME, TECH']);
        $second = UniverseImage::factory()->create(['universe_id' => $universe->id, 'position' => 2]);
        $first = UniverseImage::factory()->create(['universe_id' => $universe->id, 'position' => 1]);

        $response = $this->asUser($this->admin())->getJson("/api/users/{$user->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('id', $user->id);
        $response->assertJsonPath('universe.id', $universe->id);
        $response->assertJsonPath('universe.style', 'CHROME, TECH');
        $response->assertJsonPath('universe.images.0.id', $first->id);
        $response->assertJsonPath('universe.images.1.id', $second->id);
        $response->assertJsonPath('universe.images.0.url', Storage::disk('public')->url($first->path));
        $response->assertJsonMissingPath('counts');
    }

    public function test_a_user_without_universe_shows_a_null_universe(): void
    {
        $user = User::factory()->create();

        $response = $this->asUser($this->admin())->getJson("/api/users/{$user->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['universe']);
        $response->assertJsonPath('universe', null);
    }

    public function test_the_admin_sees_the_pieces_of_a_user_with_their_photos(): void
    {
        $user = User::factory()->create();
        $piece = Item::factory()->create(['user_id' => $user->id, 'name' => 'Silver moto boots']);
        $photo = ItemImage::factory()->create(['item_id' => $piece->id]);
        Item::factory()->create();

        $response = $this->asUser($this->admin())->getJson("/api/users/{$user->id}/items");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', 'Silver moto boots');
        $response->assertJsonPath('0.images.0.id', $photo->id);
        $response->assertJsonPath('0.images.0.url', Storage::disk('public')->url($photo->path));
    }

    public function test_a_regular_user_cannot_see_the_pieces_of_another_user(): void
    {
        $user = User::factory()->create();

        $this->asUser(User::factory()->create())->getJson("/api/users/{$user->id}/items")->assertStatus(403);
    }

    public function test_the_pieces_of_an_unknown_user_are_not_found(): void
    {
        $this->asUser($this->admin())->getJson('/api/users/999/items')->assertStatus(404);
    }
}
