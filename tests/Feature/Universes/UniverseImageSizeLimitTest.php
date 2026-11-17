<?php

namespace Tests\Feature\Universes;

use App\Models\Universe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UniverseImageSizeLimitTest extends TestCase
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

    public function test_a_universe_image_of_8_mb_is_accepted(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->postJson("/api/universes/{$universe->id}/images", [
                'images' => [UploadedFile::fake()->image('big.jpg')->size(8192)],
            ]);

        $response->assertStatus(201);
    }

    public function test_a_universe_image_over_10_mb_is_rejected(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->postJson("/api/universes/{$universe->id}/images", [
                'images' => [UploadedFile::fake()->image('huge.jpg')->size(10241)],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('images.0');
    }
}
