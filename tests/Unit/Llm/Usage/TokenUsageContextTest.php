<?php

use App\Identity\Workspace\Models\User;
use Domain\Llm\Usage\TokenUsageContext;
use Spectra\Facades\Spectra;

/**
 * TokenUsageContext is the per-request / per-job scope that attributes AI
 * requests to a workspace in Spectra's request log. The surrounding entry
 * point opens a track() scope; nested scopes share the outer workspace.
 */
beforeEach(function (): void {
    $this->ctx = new TokenUsageContext;
});

it('returns what the work returns', function (): void {
    expect($this->ctx->track(42, fn (): string => 'done'))->toBe('done');
});

it('closes the scope even when the work throws', function (): void {
    expect(fn () => $this->ctx->track(7, function (): void {
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class);

    expect($this->ctx->current())->toBeNull();
});

it('reports the innermost workspace of nested scopes and restores the outer one', function (): void {
    $this->ctx->track(1, function (): void {
        $this->ctx->track(2, function (): void {
            expect($this->ctx->current())->toBe(2);
        });

        expect($this->ctx->current())->toBe(1);
    });
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
