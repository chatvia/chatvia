<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Illuminate\Http\Request;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

/**
 * The sign-in rules FortifyServiceProvider adds outside the password login: a
 * passkey proves who the user is, but a deactivated account still stays out.
 */
it('lets a passkey sign in only an active account', function (bool $deactivated, bool $allowed): void {
    $passkey = new Passkey;
    $passkey->setRelation('user', User::factory()->create(['deactivated_at' => $deactivated ? now() : null]));

    expect(Passkeys::allowsLogin(Request::create('/'), $passkey))->toBe($allowed);
})->with([
    'an active account' => [false, true],
    'a deactivated account' => [true, false],
]);
