<?php

namespace Tests\Feature\Universes;

use App\Models\Universe;
use App\Models\UniverseImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UniverseImageFileCleanupTest extends TestCase
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

    public function test_deleting_a_universe_image_also_removes_the_file_from_disk(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id]);
        Storage::disk('public')->put('universe-images/photo.jpg', 'content');
        $image = UniverseImage::factory()->create(['universe_id' => $universe->id, 'path' => 'universe-images/photo.jpg']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->deleteJson("/api/universes/{$universe->id}/images/{$image->id}");

        $response->assertStatus(200);
        Storage::disk('public')->assertMissing('universe-images/photo.jpg');
    }
}
