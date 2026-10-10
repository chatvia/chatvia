<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Workspace\Enums\UserSource;
use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->owner = User::factory()->workspace()->create();
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
