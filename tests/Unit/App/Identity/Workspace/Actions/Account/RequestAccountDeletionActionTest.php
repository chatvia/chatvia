<?php

use App\Identity\Workspace\Actions\Account\RequestAccountDeletionAction;
use App\Identity\Workspace\Events\AccountDeletionRequested;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Uri;

/**
 * The first step of deleting an account: a signed confirmation link for the
 * account, valid for 30 minutes, handed to the email listener through
 * AccountDeletionRequested.
 */
it('hands the email listener a signed confirmation link for the user, valid for 30 minutes', function (): void {
    Event::fake([AccountDeletionRequested::class]);
    $this->freezeTime();
    $user = User::factory()->create();

    app(RequestAccountDeletionAction::class)->execute($user);

    $event = Event::dispatched(AccountDeletionRequested::class)->sole()[0];
    $expectedExpiry = now()->addMinutes(30)->getTimestamp();
    $validNow = URL::hasValidSignature(Request::create($event->url));
    $this->travel(31)->minutes();
    $validLater = URL::hasValidSignature(Request::create($event->url));

    expect($event->email)->toBe($user->email)
        ->and($event->url)->toContain('/my/account/delete-account/confirm/'.$user->id)
        ->and((int) Uri::of($event->url)->query()->all()['expires'])->toBe($expectedExpiry)
        ->and($validNow)->toBeTrue()
        ->and($validLater)->toBeFalse();
});
