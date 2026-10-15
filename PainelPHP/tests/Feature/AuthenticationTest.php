<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_valid_credentials_authenticate_user(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'secret-password']);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_do_not_authenticate_user(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }
}
