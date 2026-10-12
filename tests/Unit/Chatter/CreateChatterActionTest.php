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

it('rejects an email the workspace already uses, without case', function (): void {
    Chatter::factory()->ofWorkspace($this->owner)->create(['email' => 'taken@example.test']);

    expect(fn () => app(CreateChatterAction::class)->execute(new CreateChatterData(
        workspace: $this->owner->workspace(),
        agents: $this->agent,
        name: 'Copy',
        email: 'TAKEN@Example.test',
    )))->toThrow(DomainValidationException::class);

    expect(Chatter::query()->where('workspace_id', $this->owner->workspace()->id)->whereRaw('lower(email) = ?', ['taken@example.test'])->count())->toBe(1);
});

it('reports the duplicate on the email field', function (): void {
    Chatter::factory()->ofWorkspace($this->owner)->create(['email' => 'taken@example.test']);

    try {
        app(CreateChatterAction::class)->execute(new CreateChatterData(
            workspace: $this->owner->workspace(),
            agents: $this->agent,
            name: 'Copy',
            email: 'taken@example.test',
        ));
        $this->fail('Expected a DomainValidationException.');
    } catch (DomainValidationException $e) {
        expect($e->field)->toBe('email');
    }
});

it('creates a separate chatter for an email used in another workspace', function (): void {
    $otherOwner = User::factory()->workspace()->create();
    $elsewhere = Chatter::factory()->ofWorkspace($otherOwner)->create(['email' => 'shared@example.test']);

    $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData(
        workspace: $this->owner->workspace(),
        agents: $this->agent,
        name: 'Shared',
        email: 'shared@example.test',
        password: 'pass-1234',
    ));

    expect($chatter->id)->not->toBe($elsewhere->id)
        ->and($chatter->workspace_id)->toBe($this->owner->workspace()->id)
        ->and(Chatter::query()->whereRaw('lower(email) = ?', ['shared@example.test'])->count())->toBe(2)
        // The other workspace's chatter is untouched and still has no grant here.
        ->and($elsewhere->fresh()->workspace_id)->toBe($otherOwner->workspace()->id)
        ->and($elsewhere->fresh()->canAccessAgent($this->agent))->toBeFalse();
});

it('creates a chatter with the email of a staff account', function (): void {
    $staff = User::factory()->employee($this->owner)->create(['email' => 'teammate@example.test']);

    $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData(
        workspace: $this->owner->workspace(),
        agents: $this->agent,
        name: 'Teammate as chatter',
        email: 'teammate@example.test',
        password: 'pass-1234',
    ));

    expect($chatter)->toBeInstanceOf(Chatter::class)
        ->and($chatter->email)->toBe('teammate@example.test')
        // The staff account keeps its own password and its teammate role.
        ->and(Hash::check('pass-1234', $staff->fresh()->password))->toBeFalse()
        ->and($staff->fresh()->isTeammate())->toBeTrue();
});
