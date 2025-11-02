<?php

namespace Feature\App;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    #[Test]
    public function guest_can_register_and_should_verify_email(): void
    {
        $email = $this->faker()->email();

        $response = $this->from(route('register'))->post(route('register'), [
            'name' => $this->faker()->name(),
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
            'policy' => true,
        ]);

        $response->assertSessionDoesntHaveErrors();

        $response->assertRedirect(route('dashboard'));

        $user = User::whereEmail($email)->first();

        $this->assertAuthenticatedAs($user);

        $dashboard = $this->get(route('dashboard'));

        $dashboard->assertSee('Redirecting to');

        $dashboard->assertSee(route('verification.notice'));
    }
}
