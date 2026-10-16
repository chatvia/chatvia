<?php

use App\Identity\Workspace\Actions\Account\RequestAccountDeletionAction;
use App\Identity\Workspace\Events\AccountDeletionRequested;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

it('dispatches AccountDeletionRequested with a signed confirmation link for the user', function (): void {
    Event::fake();

    $user = User::factory()->create();

    app(RequestAccountDeletionAction::class)->execute($user);

    Event::assertDispatched(
        AccountDeletionRequested::class,
        fn (AccountDeletionRequested $event): bool => $event->email === $user->email
            && $event->name === $user->name
            && str_contains($event->url, '/my/account/delete-account/confirm/'.$user->id)
            && str_contains($event->url, 'signature=')
            && str_contains($event->url, 'expires='),
    );
});

it('mints a confirmation link that is valid for 30 minutes', function (): void {
    Event::fake();
    $this->freezeTime();
    $user = User::factory()->create();

    app(RequestAccountDeletionAction::class)->execute($user);

    $url = Event::dispatched(AccountDeletionRequested::class)->sole()[0]->url;
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect((int) $query['expires'])->toBe(now()->addMinutes(30)->getTimestamp())
        ->and(URL::hasValidSignature(Request::create($url)))->toBeTrue();

    $this->travel(31)->minutes();

    expect(URL::hasValidSignature(Request::create($url)))->toBeFalse();
});
