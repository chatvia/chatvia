<?php

use App\Identity\Workspace\Actions\Account\CreateAdminUserAction;
use App\Identity\Workspace\Data\Account\CreateAdminUserData;
use App\Identity\Workspace\Exceptions\EmailAlreadyTakenException;
use App\Identity\Workspace\Models\User;

/**
 * Creating an install admin refuses an email that already has an account, in
 * any letter case, so a second admin can never share a person's sign-in.
 */
it('refuses an email that already has an account, whatever its case', function (): void {
    withLicense();
    User::factory()->create(['email' => 'case-taken@example.test']);

    expect(fn () => app(CreateAdminUserAction::class)->execute(new CreateAdminUserData(
        name: 'Copy',
        email: 'Case-Taken@Example.test',
        password: 'a-long-password',
    )))->toThrow(EmailAlreadyTakenException::class);

    expect(User::query()->where('email', 'case-taken@example.test')->count())->toBe(1);
});
