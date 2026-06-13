<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WidgetAuthSessionService;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Http\Request;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->agent = createAgent($this->owner);
    $this->service = app(WidgetAuthSessionService::class);

    // A stable fingerprint baseline — issue and resolve from the same
    // (ip, ua) tuple so the IP/UA pinning check (SEC-W-10) never trips
    // inside the round-trip tests.
    $this->request = Request::create('/widget', 'POST', server: [
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Test) Chrome/123 Safari/537',
    ]);
});

it('round-trips the agent owner (not just chatters)', function (): void {
    // Regression: the exchange mints sessions via AgentSignInTokenService::verify,
    // whose valid subject includes the owner — so resolve() must not filter by
    // user type, or the owner's own session never resolves.
    $session = $this->service->issue($this->owner, $this->agent, $this->request);

    $resolved = $this->service->resolve($session['token'], $this->agent, $this->request);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($this->owner->id);
});

it('round-trips a chatter', function (): void {
    $chatter = app(CreateChatterAction::class)->execute($this->owner, $this->agent, 'Jane');

    $session = $this->service->issue($chatter, $this->agent, $this->request);

    expect($this->service->resolve($session['token'], $this->agent, $this->request)?->id)
        ->toBe($chatter->id);
});

it('rejects a still-cached token the moment the user is deactivated', function (): void {
    $session = $this->service->issue($this->owner, $this->agent, $this->request);

    // Resolves fine while active.
    expect($this->service->resolve($session['token'], $this->agent, $this->request)?->id)->toBe($this->owner->id);

    app(\App\Identity\Workspace\Actions\Account\DeactivateAccountAction::class)->execute($this->owner, 'blocked by admin');

    // Same token, now rejected per-request (no need to wait for the TTL).
    expect($this->service->resolve($session['token'], $this->agent, $this->request))->toBeNull();
});

it('rejects a token minted for a different agent', function (): void {
    $other = createAgent($this->owner);
    $session = $this->service->issue($this->owner, $this->agent, $this->request);

    expect($this->service->resolve($session['token'], $other, $this->request))->toBeNull();
});

it('returns null for an unknown token', function (): void {
    expect($this->service->resolve('nope', $this->agent, $this->request))->toBeNull();
});

it('rejects a token replayed from a different ip / user-agent', function (): void {
    // Defence-in-depth (SEC-W-10): the cached payload pins the token to the
    // (ip, ua) it was issued under. A different machine reusing the token
    // (XSS exfiltration scenario) must NOT resolve.
    $session = $this->service->issue($this->owner, $this->agent, $this->request);

    $stranger = Request::create('/widget', 'POST', server: [
        'REMOTE_ADDR' => '198.51.100.250',
        'HTTP_USER_AGENT' => 'curl/8.0',
    ]);

    expect($this->service->resolve($session['token'], $this->agent, $stranger))->toBeNull();
});

it('carries session meta through resolveWithMeta', function (): void {
    $session = $this->service->issue(
        $this->owner,
        $this->agent,
        $this->request,
        ['is_playground' => true],
    );

    $payload = $this->service->resolveWithMeta($session['token'], $this->agent, $this->request);

    expect($payload)->not->toBeNull()
        ->and($payload['user']->id)->toBe($this->owner->id)
        ->and($payload['meta']['is_playground'])->toBeTrue();
});

it('revoke() invalidates a previously valid token', function (): void {
    $session = $this->service->issue($this->owner, $this->agent, $this->request);

    $this->service->revoke($session['token']);

    expect($this->service->resolve($session['token'], $this->agent, $this->request))->toBeNull();
});
