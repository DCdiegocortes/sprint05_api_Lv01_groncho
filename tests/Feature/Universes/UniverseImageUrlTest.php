<?php

namespace Tests\Feature\Universes;

use App\Models\Universe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UniverseImageUrlTest extends TestCase
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

    public function test_an_uploaded_universe_image_answers_with_its_url_and_keeps_the_path(): void
    {
        $owner = User::factory()->create();
        $universe = Universe::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($owner))
            ->postJson("/api/universes/{$universe->id}/images", [
                'images' => [UploadedFile::fake()->image('photo.jpg')],
            ]);

        $response->assertStatus(201);
        $path = $response->json('0.path');
        $this->assertNotEmpty($path);
        $response->assertJsonPath('0.url', Storage::disk('public')->url($path));
    }
}
