<?php

use App\Auth\Models\User;
use App\Billing\Models\UserQuota;
use Domain\Auth\Actions\CreateChatterAction;
use Domain\Auth\Exceptions\ChatterQuotaExceededException;
use Illuminate\Support\Facades\Hash;
use Support\Enums\UserSource;
use Support\Enums\UserType;

beforeEach(function (): void {
    $this->owner = User::factory()->workspace()->create();
    UserQuota::query()->updateOrCreate(
        ['user_id' => $this->owner->id],
        ['members_limit' => 5],
    );
    $this->agent = createTemplateAgent($this->owner);
});

it('creates a chatter with email + password and grants agent access', function (): void {
    $chatter = app(CreateChatterAction::class)->execute(
        owner: $this->owner,
        agent: $this->agent,
        name: 'Chatty',
        email: 'chatty@example.test',
        password: 'pass-1234',
    );

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
    $chatter = app(CreateChatterAction::class)->execute(
        owner: $this->owner,
        agent: $this->agent,
        name: 'Anon',
    );

    expect($chatter->email)->toBeNull()
        ->and($chatter->type)->toBe(UserType::Chatter)
        ->and($chatter->password)->not->toBeEmpty();
});

it('throws ChatterQuotaExceededException when the workspace has hit its members_limit', function (): void {
    UserQuota::query()->updateOrCreate(
        ['user_id' => $this->owner->id],
        ['members_limit' => 0],
    );

    app(CreateChatterAction::class)->execute(
        owner: $this->owner,
        agent: $this->agent,
        name: 'Overflow',
    );
})->throws(ChatterQuotaExceededException::class);
