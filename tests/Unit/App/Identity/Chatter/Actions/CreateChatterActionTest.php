<?php

/**
 * CreateChatterAction: a chatter row in the workspace, granted the given
 * agents. An email is unique inside one workspace (without case) but the same
 * email may be a chatter in another workspace or belong to a staff account.
 */

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Chatter\Enums\ChatterSource;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Support\Exceptions\DomainValidationException;

beforeEach(function (): void {
    $this->owner = User::factory()->workspace()->create();
    $this->agent = createAgent($this->owner);
});

it('creates a chatter with email + password and grants agent access', function (): void {
    $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData(
        workspace: $this->owner->workspace(),
        agents: $this->agent,
        name: 'Chatty',
        email: 'chatty@example.test',
        password: 'pass-1234',
    ));

    expect($chatter)->toBeInstanceOf(Chatter::class)
        ->and($chatter->name)->toBe('Chatty')
        ->and($chatter->email)->toBe('chatty@example.test')
        ->and($chatter->source)->toBe(ChatterSource::Api)
        ->and($chatter->workspace_id)->toBe($this->owner->workspace()->id)
        ->and($chatter->email_verified_at)->not->toBeNull()
        ->and(Hash::check('pass-1234', $chatter->password))->toBeTrue()
        ->and($chatter->canAccessAgent($this->agent))->toBeTrue()
        ->and(User::query()->where('email', 'chatty@example.test')->exists())->toBeFalse();
});

it('stores the email in lower case', function (): void {
    $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData(
        workspace: $this->owner->workspace(),
        agents: $this->agent,
        name: 'Shouty',
        email: '  Shouty@Example.TEST ',
    ));

    expect($chatter->email)->toBe('shouty@example.test');
});

it('creates a token-only chatter when no email is supplied', function (): void {
    $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData(
        workspace: $this->owner->workspace(),
        agents: $this->agent,
        name: 'Anon',
    ));

    expect($chatter->email)->toBeNull()
        ->and($chatter->password)->toBeNull()
        ->and($chatter->workspace_id)->toBe($this->owner->workspace()->id);
});

it('allows several token-only chatters in one workspace', function (): void {
    $action = app(CreateChatterAction::class);

    $first = $action->execute(new CreateChatterData(workspace: $this->owner->workspace(), agents: $this->agent, name: 'One'));
    $second = $action->execute(new CreateChatterData(workspace: $this->owner->workspace(), agents: $this->agent, name: 'Two'));

    expect($first->id)->not->toBe($second->id);
});

it('grants the chatter access to every agent supplied as a collection', function (): void {
    $second = createAgent($this->owner);

    $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData(
        workspace: $this->owner->workspace(),
        agents: new Collection([$this->agent, $second]),
        name: 'Multi',
        email: 'multi@example.test',
        password: 'pass-1234',
    ));

    expect($chatter->agents()->pluck('agents.id')->all())
        ->toHaveCount(2)
        ->toContain($this->agent->id)
        ->toContain($second->id);
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
    });

    expect(Chatter::query()->where('workspace_id', $this->owner->workspace()->id)->whereRaw('lower(email) = ?', ['taken@example.test'])->count())->toBe(1);
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

it('creates no teammate, team or team member', function (): void {
    $workspace = $this->owner->workspace();
    $team = ownerTeam($this->owner);
    $before = [$workspace->members()->count(), Team::query()->count(), $team->members()->count()];

    app(CreateChatterAction::class)->execute(new CreateChatterData($workspace, $this->agent, 'Jane'));

    // Compared by count: a chatter's id comes from another table and can
    // equal a staff id.
    expect([$workspace->members()->count(), Team::query()->count(), $team->members()->count()])->toBe($before)
        ->and($workspace->chatters()->count())->toBe(1);
});
