<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_endpoints_require_a_logged_in_user(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->getJson('/api/plants')->assertUnauthorized();
        $this->getJson('/api/alerts')->assertUnauthorized();
    }

    public function test_a_user_can_log_in_from_the_spa(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);

        // An Origin from SANCTUM_STATEFUL_DOMAINS makes the request stateful,
        // exactly like the browser on the Vue dev server.
        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertOk()
            ->assertJson(['id' => $user->id, 'email' => $user->email]);

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest('web');
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'nope'])->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'nope'])->assertTooManyRequests();
    }

    public function test_the_current_user_is_returned(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertExactJson(['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
    }

    public function test_a_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('Origin', 'http://localhost')
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->assertGuest('web');
    }
}
