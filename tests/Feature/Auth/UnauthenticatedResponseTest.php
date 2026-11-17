<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnauthenticatedResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_without_token_and_without_accept_header_returns_401(): void
    {
        $response = $this->get('/api/items');

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_request_with_an_invalid_token_and_without_accept_header_returns_401(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid')->get('/api/items');

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }
}
