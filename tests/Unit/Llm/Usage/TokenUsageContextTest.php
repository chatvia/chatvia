<?php

use App\Identity\Workspace\Models\User;
use Domain\Llm\Events\LlmTokensConsumed;
use Domain\Llm\Usage\TokenUsageContext;
use Illuminate\Support\Facades\Event;
use Spectra\Facades\Spectra;

/**
 * TokenUsageContext is the per-request / per-job accumulator that attributes LLM
 * token consumption to a workspace for analytics. Each LLM call site calls add();
 * the surrounding entry point opens a track() scope that flushes ONE
 * LlmTokensConsumed event with the per-scope total.
 */
beforeEach(function (): void {
    $this->ctx = new TokenUsageContext;
});

it('accumulates add() calls within a scope and flushes one event with the sum', function (): void {
    Event::fake([LlmTokensConsumed::class]);

    $result = $this->ctx->track(42, function (): string {
        $this->ctx->add(100);
        $this->ctx->add(50);

        return 'done';
    }, 'chat');

    expect($result)->toBe('done');

    Event::assertDispatchedTimes(LlmTokensConsumed::class, 1);
    Event::assertDispatched(
        LlmTokensConsumed::class,
        fn (LlmTokensConsumed $e): bool => $e->workspaceId === 42 && $e->tokens === 150 && $e->purpose === 'chat',
    );
});

it('does not flush when no tokens were added', function (): void {
    Event::fake([LlmTokensConsumed::class]);

    $this->ctx->track(42, fn (): null => null);

    Event::assertNotDispatched(LlmTokensConsumed::class);
});

it('drops tokens added outside any scope (no owner in context)', function (): void {
    Event::fake([LlmTokensConsumed::class]);

    $this->ctx->add(999);

    expect($this->ctx->current())->toBeNull();
    Event::assertNotDispatched(LlmTokensConsumed::class);
});

it('records nothing when the scope has a null workspace', function (): void {
    Event::fake([LlmTokensConsumed::class]);

    $this->ctx->track(null, function (): void {
        $this->ctx->add(500);
    });

    Event::assertNotDispatched(LlmTokensConsumed::class);
});

it('ignores non-positive token counts', function (): void {
    Event::fake([LlmTokensConsumed::class]);

    $this->ctx->track(1, function (): void {
        $this->ctx->add(0);
        $this->ctx->add(-10);
    });

    Event::assertNotDispatched(LlmTokensConsumed::class);
});

it('flushes even when the work throws', function (): void {
    Event::fake([LlmTokensConsumed::class]);

    expect(fn () => $this->ctx->track(7, function (): void {
        $this->ctx->add(80);
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class);

    Event::assertDispatched(
        LlmTokensConsumed::class,
        fn (LlmTokensConsumed $e): bool => $e->workspaceId === 7 && $e->tokens === 80,
    );
});

it('isolates nested scopes: inner flushes independently and the outer keeps its own total', function (): void {
    Event::fake([LlmTokensConsumed::class]);

    $this->ctx->track(1, function (): void {
        $this->ctx->add(10);                       // outer

        $this->ctx->track(2, function (): void {
            $this->ctx->add(200);                  // inner
            expect($this->ctx->current())->toBe(2);
        }, 'ingest');

        $this->ctx->add(5);                        // outer again, after inner restored
        expect($this->ctx->current())->toBe(1);
    }, 'chat');

    Event::assertDispatchedTimes(LlmTokensConsumed::class, 2);
    Event::assertDispatched(
        LlmTokensConsumed::class,
        fn (LlmTokensConsumed $e): bool => $e->workspaceId === 2 && $e->tokens === 200 && $e->purpose === 'ingest',
    );
    Event::assertDispatched(
        LlmTokensConsumed::class,
        fn (LlmTokensConsumed $e): bool => $e->workspaceId === 1 && $e->tokens === 15 && $e->purpose === 'chat',
    );
});

it('reports the innermost workspace from current() and null after the scope closes', function (): void {
    expect($this->ctx->current())->toBeNull();

    $this->ctx->track(99, function (): void {
        expect($this->ctx->current())->toBe(99);
    });

    expect($this->ctx->current())->toBeNull();
});

/**
 * The Spectra integration: a track() scope binds the workspace as Spectra's
 * global trackable so AI requests made inside it (in a queue/console context
 * with no auth user) are attributed to that workspace, then clears it on exit.
 */
it('binds the workspace as the Spectra trackable inside the scope and clears it on exit', function (): void {
    Spectra::clearGlobals();
    $ws = User::factory()->create();

    $inside = null;

    $this->ctx->track($ws->id, function () use (&$inside): void {
        $inside = Spectra::startRequest('scaleway', 'qwen3-embedding-8b');
    });

    expect($inside->trackableType)->toBe(User::class)
        ->and((int) $inside->trackableId)->toBe($ws->id);

    // Outermost scope closed → global cleared, so a fresh request is unbound.
    expect(Spectra::startRequest('scaleway', 'qwen3-embedding-8b')->trackableType)->toBeNull();
});

it('keeps the Spectra trackable bound across nested scopes until the outermost exits', function (): void {
    Spectra::clearGlobals();
    $ws = User::factory()->create();

    $this->ctx->track($ws->id, function () use ($ws): void {
        $this->ctx->track($ws->id, fn (): null => null); // inner scope opens and closes

        // The inner pop must NOT have cleared the outer binding.
        expect((int) Spectra::startRequest('scaleway', 'm')->trackableId)->toBe($ws->id);
    });

    expect(Spectra::startRequest('scaleway', 'm')->trackableType)->toBeNull();
});

it('does not bind a Spectra trackable when the workspace is null', function (): void {
    Spectra::clearGlobals();

    $this->ctx->track(null, function (): void {
        expect(Spectra::startRequest('scaleway', 'm')->trackableType)->toBeNull();
    });
});
