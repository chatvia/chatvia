<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Chatter\Enums\ChatterSource;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Support\Exceptions\DomainValidationException;

/**
 * CreateChatterAction: a chatter row in the workspace, granted the given
 * agents. An email is unique inside one workspace (without case) but the same
 * email may be a chatter in another workspace or belong to a staff account.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->workspace()->create();
    $this->agent = createAgent($this->owner);
});

it('creates a chatter in the workspace, granted every agent supplied, and no teammate or team', function (): void {
    $second = createAgent($this->owner);
    $workspace = $this->owner->workspace();
    $team = ownerTeam($this->owner);
    $before = [$workspace->members()->count(), Team::query()->count(), $team->members()->count()];

    $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData(
        workspace: $workspace,
        agents: new Collection([$this->agent, $second]),
        name: 'Chatty',
        email: '  Chatty@Example.TEST ',
        password: 'pass-1234',
    ));

    expect($chatter)->toBeInstanceOf(Chatter::class)
        ->and($chatter->email)->toBe('chatty@example.test')
        ->and($chatter->source)->toBe(ChatterSource::Api)
        ->and($chatter->workspace_id)->toBe($workspace->id)
        ->and($chatter->email_verified_at)->not->toBeNull()
        ->and(Hash::check('pass-1234', $chatter->password))->toBeTrue()
        ->and($chatter->canAccessAgent($this->agent))->toBeTrue()
        ->and($chatter->canAccessAgent($second))->toBeTrue()
        ->and(User::query()->where('email', 'chatty@example.test')->exists())->toBeFalse()
        ->and([$workspace->members()->count(), Team::query()->count(), $team->members()->count()])->toBe($before);
});

it('rejects an email the workspace already uses, without case, on the email field', function (): void {
    Chatter::factory()->ofWorkspace($this->owner)->create(['email' => 'taken@example.test']);

    expect(fn () => app(CreateChatterAction::class)->execute(new CreateChatterData(
        workspace: $this->owner->workspace(),
        agents: $this->agent,
        name: 'Copy',
        email: 'TAKEN@Example.test',
    )))->toThrow(function (DomainValidationException $e): void {
        expect($e->field)->toBe('email');
    })
        ->and(Chatter::query()->where('workspace_id', $this->owner->workspace()->id)->whereRaw('lower(email) = ?', ['taken@example.test'])->count())->toBe(1);
});

it('creates a separate chatter for an email used elsewhere and leaves that account alone', function (string $elsewhere): void {
    $account = match ($elsewhere) {
        'chatter' => Chatter::factory()->ofWorkspace(User::factory()->workspace()->create())->create(['email' => 'shared@example.test']),
        'staff' => User::factory()->employee($this->owner)->create(['email' => 'shared@example.test']),
    };
    $before = $account->fresh()->getAttributes();

    $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData(
        workspace: $this->owner->workspace(),
        agents: $this->agent,
        name: 'Shared',
        email: 'shared@example.test',
        password: 'pass-1234',
    ));

    expect($chatter->workspace_id)->toBe($this->owner->workspace()->id)
        ->and($chatter->email)->toBe('shared@example.test')
        ->and($chatter->is($account))->toBeFalse()
        ->and($account->fresh()->getAttributes())->toBe($before);
})->with([
    'a chatter of another workspace' => 'chatter',
    'a staff account of this workspace' => 'staff',
]);
