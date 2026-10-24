<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * The `users` provider (the `staff` driver) looks a staff account up by its
 * lowercased email, so the admin panel's guard checks a password whatever
 * case the email is typed in.
 */
it('checks credentials whatever the case of the typed email', function (): void {
    User::factory()->create(['email' => 'case-admin@example.test', 'password' => Hash::make('secret-pass')]);

    expect(Auth::guard('admin')->validate(['email' => 'CASE-Admin@example.test', 'password' => 'secret-pass']))->toBeTrue()
        ->and(Auth::guard('admin')->validate(['email' => 'CASE-Admin@example.test', 'password' => 'wrong-pass']))->toBeFalse();
});
