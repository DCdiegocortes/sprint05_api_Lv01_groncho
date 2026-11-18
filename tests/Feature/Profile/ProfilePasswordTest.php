<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class ProfilePasswordTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT = 'old-secret-1';

    private const NEW = 'new-secret-2';

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Test Personal Access Client'
        );
    }

    private function updateProfile(User $user, array $data)
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->accessToken)
            ->putJson('/api/profile', $data);
    }

    private function user(): User
    {
        return User::factory()->create(['password' => self::CURRENT]);
    }

    public function test_a_user_can_change_the_password_with_the_current_one(): void
    {
        $user = $this->user();

        $response = $this->updateProfile($user, [
            'current_password' => self::CURRENT,
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ]);

        $response->assertStatus(200);
        $response->assertJsonMissingPath('password');
        $this->assertTrue(Hash::check(self::NEW, $user->fresh()->password));
    }

    public function test_the_current_password_is_required_to_change_it(): void
    {
        $user = $this->user();

        $response = $this->updateProfile($user, [
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('current_password');
        $this->assertTrue(Hash::check(self::CURRENT, $user->fresh()->password));
    }

    public function test_a_wrong_current_password_is_rejected(): void
    {
        $user = $this->user();

        $response = $this->updateProfile($user, [
            'current_password' => 'not-my-password',
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('current_password');
        $this->assertTrue(Hash::check(self::CURRENT, $user->fresh()->password));
    }

    public function test_the_new_password_must_be_confirmed(): void
    {
        $user = $this->user();

        $response = $this->updateProfile($user, [
            'current_password' => self::CURRENT,
            'password' => self::NEW,
            'password_confirmation' => 'something-else',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_the_new_password_needs_at_least_eight_characters(): void
    {
        $user = $this->user();

        $response = $this->updateProfile($user, [
            'current_password' => self::CURRENT,
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_changing_the_name_does_not_need_the_current_password(): void
    {
        $user = $this->user();

        $response = $this->updateProfile($user, ['name' => 'Kaeru']);

        $response->assertStatus(200);
        $response->assertJsonPath('name', 'Kaeru');
        $this->assertTrue(Hash::check(self::CURRENT, $user->fresh()->password));
    }
}
