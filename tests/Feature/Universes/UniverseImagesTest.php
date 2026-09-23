<?php

namespace Tests\Feature\Universes;

use App\Models\Universe;
use App\Models\UniverseImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UniverseImagesTest extends TestCase
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

    private function tokenFor(User $user): string
    {
        return $user->createToken('test-token')->accessToken;
    }

    public function test_owner_can_upload_images_to_their_universe(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->postJson("/api/universes/{$universe->id}/images", [
                'images' => [
                    UploadedFile::fake()->image('photo1.jpg'),
                    UploadedFile::fake()->image('photo2.jpg'),
                ],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('universe_images', 2);
    }

    public function test_uploading_images_requires_authentication(): void
    {
        $universe = Universe::factory()->create();

        $response = $this->postJson("/api/universes/{$universe->id}/images", [
            'images' => [UploadedFile::fake()->image('photo1.jpg')],
        ]);

        $response->assertStatus(401);
    }

    public function test_other_user_cannot_upload_images_to_someone_elses_universe(): void
    {
        $intruder = User::factory()->create();
        $universe = Universe::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->postJson("/api/universes/{$universe->id}/images", [
                'images' => [UploadedFile::fake()->image('photo1.jpg')],
            ]);

        $response->assertStatus(403);
    }

    public function test_uploading_images_requires_at_least_one_image(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->postJson("/api/universes/{$universe->id}/images", ['images' => []]);

        $response->assertStatus(422);
    }

    public function test_owner_can_delete_an_image(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id]);
        $image = UniverseImage::factory()->create(['universe_id' => $universe->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->deleteJson("/api/universes/{$universe->id}/images/{$image->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('universe_images', ['id' => $image->id]);
    }

    public function test_admin_can_delete_any_image(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $universe = Universe::factory()->create();
        $image = UniverseImage::factory()->create(['universe_id' => $universe->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->deleteJson("/api/universes/{$universe->id}/images/{$image->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('universe_images', ['id' => $image->id]);
    }

    public function test_other_user_cannot_delete_someone_elses_image(): void
    {
        $intruder = User::factory()->create();
        $universe = Universe::factory()->create();
        $image = UniverseImage::factory()->create(['universe_id' => $universe->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($intruder))
            ->deleteJson("/api/universes/{$universe->id}/images/{$image->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('universe_images', ['id' => $image->id]);
    }

    public function test_deleting_image_requires_authentication(): void
    {
        $universe = Universe::factory()->create();
        $image = UniverseImage::factory()->create(['universe_id' => $universe->id]);

        $response = $this->deleteJson("/api/universes/{$universe->id}/images/{$image->id}");

        $response->assertStatus(401);
    }
}
