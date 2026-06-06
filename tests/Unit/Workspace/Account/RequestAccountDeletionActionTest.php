<?php

use App\Identity\Workspace\Actions\Account\RequestAccountDeletionAction;
use App\Identity\Workspace\Events\AccountDeletionRequested;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Event;

it('dispatches AccountDeletionRequested with a signed confirmation link for the user', function (): void {
    Event::fake();

    $user = User::factory()->create();

    app(RequestAccountDeletionAction::class)->execute($user);

    Event::assertDispatched(
        AccountDeletionRequested::class,
        fn (AccountDeletionRequested $event): bool => $event->email === $user->email
            && $event->name === $user->name
            && str_contains($event->url, '/settings/delete-account/confirm/'.$user->id)
            && str_contains($event->url, 'signature=')
            && str_contains($event->url, 'expires='),
    );
});

it('mints a confirmation link that passes signature validation', function (): void {
    Event::fake();

    $user = User::factory()->create();

    app(RequestAccountDeletionAction::class)->execute($user);

    $url = null;
    Event::assertDispatched(AccountDeletionRequested::class, function (AccountDeletionRequested $event) use (&$url): bool {
        $url = $event->url;

        return true;
    });

    $request = Illuminate\Http\Request::create($url);

    expect(Illuminate\Support\Facades\URL::hasValidSignature($request))->toBeTrue();
});
