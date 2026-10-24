<?php

use App\Identity\Workspace\Actions\Account\CreateAdminUserAction;
use App\Identity\Workspace\Data\Account\CreateAdminUserData;
use App\Identity\Workspace\Exceptions\EmailAlreadyTakenException;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Creating an install admin (the setup command and the admin panel): a
 * verified staff account with its own workspace and team. An email already
 * taken, in any case, is refused.
 */
it('creates a verified admin that owns and works in its default team', function (): void {
    $admin = app(CreateAdminUserAction::class)->execute(new CreateAdminUserData(
        name: 'Staff',
        email: 'staff@self-hosted.test',
        password: 'a-long-password',
    ));

    expect($admin->isAdmin())->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-long-password', $admin->password))->toBeTrue()
        ->and($admin->current_team_id)->toBe($admin->ownedTeams()->sole()->id);
});

it('refuses an email that already has an account, whatever its case', function (): void {
    User::factory()->create(['email' => 'case-taken@example.test']);

    expect(fn () => app(CreateAdminUserAction::class)->execute(new CreateAdminUserData(
        name: 'Copy',
        email: 'Case-Taken@Example.test',
        password: 'a-long-password',
    )))->toThrow(EmailAlreadyTakenException::class);

    expect(User::query()->where('email', 'case-taken@example.test')->count())->toBe(1);
});
