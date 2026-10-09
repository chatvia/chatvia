<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Chatter\Exceptions\ChatterQuotaExceededException;
use App\Identity\Workspace\Enums\UserSource;
use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserQuota;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->owner = User::factory()->workspace()->create();
    UserQuota::query()->updateOrCreate(
        ['user_id' => $this->owner->id],
        ['workspace_users_limit' => 5],
    );
    $this->agent = createAgent($this->owner);
});

it('creates a chatter with email + password and grants agent access', function (): void {
    $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData(
        owner: $this->owner,
        agents: $this->agent,
        name: 'Chatty',
        email: 'chatty@example.test',
        password: 'pass-1234',
    ));

    expect($chatter)->toBeInstanceOf(User::class)
        ->and($chatter->name)->toBe('Chatty')
        ->and($chatter->email)->toBe('chatty@example.test')
        ->and($chatter->type)->toBe(UserType::Chatter)
        ->and($chatter->source)->toBe(UserSource::Api)
        ->and($chatter->workspace_id)->toBe($this->owner->id)
        ->and($chatter->email_verified_at)->not->toBeNull()
        ->and(Hash::check('pass-1234', $chatter->password))->toBeTrue()
        ->and($chatter->accessibleAgents()->whereKey($this->agent->id)->exists())->toBeTrue();
});

it('creates a token-only chatter when no email is supplied', function (): void {
    $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData(
        owner: $this->owner,
        agents: $this->agent,
        name: 'Anon',
    ));

    expect($chatter->email)->toBeNull()
        ->and($chatter->type)->toBe(UserType::Chatter)
        ->and($chatter->password)->not->toBeEmpty();
});

it('throws ChatterQuotaExceededException when the workspace has hit its workspace_users_limit', function (): void {
    UserQuota::query()->updateOrCreate(
        ['user_id' => $this->owner->id],
        ['workspace_users_limit' => 0],
    );

    app(CreateChatterAction::class)->execute(new CreateChatterData(
        owner: $this->owner,
        agents: $this->agent,
        name: 'Overflow',
    ));
})->throws(ChatterQuotaExceededException::class);

it('grants the chatter access to every agent supplied as a collection', function (): void {
    $second = createAgent($this->owner);

    $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData(
        owner: $this->owner,
        agents: new Collection([$this->agent, $second]),
        name: 'Multi',
        email: 'multi@example.test',
        password: 'pass-1234',
    ));

    expect($chatter->accessibleAgents()->pluck('agents.id')->all())
        ->toHaveCount(2)
        ->toContain($this->agent->id)
        ->toContain($second->id);
});

it('counts the Members add-on seats in the limit it reports', function (): void {
    UserQuota::query()->updateOrCreate(
        ['user_id' => $this->owner->id],
        ['workspace_users_limit' => 1, 'extra_members_limit' => 1],
    );

    foreach (['First', 'Second'] as $name) {
        app(CreateChatterAction::class)->execute(new CreateChatterData($this->owner, $this->agent, $name));
    }

    expect(fn () => app(CreateChatterAction::class)->execute(new CreateChatterData($this->owner, $this->agent, 'Third')))
        ->toThrow(ChatterQuotaExceededException::class, 'workspace user limit (2)');

    expect($this->owner->chatters()->count())->toBe(2);
});
