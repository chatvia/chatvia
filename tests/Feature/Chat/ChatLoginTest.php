<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The hosted-chat password form (/chat/login) signs in chatters only. Owners,
 * teammates and admins share the `web` guard with the dashboard and this form
 * has no 2FA step, so it must never sign them in.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create([
        'email' => 'owner@example.test',
        'password' => Hash::make('owner-pass'),
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_confirmed_at' => now(),
    ]);
    // Hosts are lower-cased, so the subdomain must be too.
    $this->agent = createAgent($this->owner, ['sub_domain' => Str::lower(Str::random(12))]);
    $this->host = $this->agent->sub_domain.'.chatvia.test';
});

it('does not sign in a 2FA-enabled owner through the hosted-chat password form', function (): void {
    $this->from('https://'.$this->host.'/chat/login')
        ->post('https://'.$this->host.'/chat/login', [
            'email' => 'owner@example.test',
            'password' => 'owner-pass',
        ])
        ->assertSessionHasErrors('email');

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and(session('login.id'))->toBeNull();

    // And so the dashboard stays closed.
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('does not sign in the owner through the apex ?agent= form either', function (): void {
    $this->post('/chat/login?agent='.$this->agent->sub_domain, [
        'email' => 'owner@example.test',
        'password' => 'owner-pass',
    ])->assertSessionHasErrors('email');

    expect(Auth::guard('web')->check())->toBeFalse();
});

it('still signs in a chatter who has access to the agent', function (): void {
    $chatter = User::factory()->chatter($this->owner)->create([
        'email' => 'chatter@example.test',
        'password' => Hash::make('chatter-pass'),
    ]);
    $chatter->accessibleAgents()->attach($this->agent);

    $this->post('https://'.$this->host.'/chat/login', [
        'email' => 'chatter@example.test',
        'password' => 'chatter-pass',
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect(Auth::guard('web')->id())->toBe($chatter->id);
});
