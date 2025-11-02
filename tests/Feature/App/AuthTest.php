<?php

namespace Feature\App;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    #[Test]
    public function user_should_login_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(302);

        $response->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function user_should_not_login_with_invalid_credentials(): void
    {
        $response = $this->from(route('login'))->post(route('login'), [
            'email' => $this->faker()->email,
            'password' => 'password',
        ]);

        $response->assertStatus(302);

        $response->assertRedirect(route('login'));

        $this->assertGuest();

        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->post(route('logout'));

        $response->assertStatus(302);

        $response->assertRedirect(route('home'));
    }
}
