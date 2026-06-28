<?php

namespace Domain\Llm\Usage;

use Closure;
use Domain\Llm\Events\LlmTokensConsumed;
use Illuminate\Http\Client\Response;
use Spectra\Facades\Spectra;
use Throwable;

/**
 * Request/job-scoped boundary that attributes AI usage to a workspace (User).
 *
 * Two consumers ride this scope:
 *
 *  1. INTERNAL analytics — every LLM call site (`AbstractLlmProvider::completion()`,
 *     the embeddings / rerank endpoints) pushes its token usage via {@see add()};
 *     the surrounding entry point (chat turn, ingestion job, analysis job) opens a
 *     scope via {@see track()} that flushes ONE {@see LlmTokensConsumed} event when
 *     the work finishes. Tokens never gate or bill — that's messages — this only
 *     feeds the "tokens consumed this cycle" figure on the usage dashboard.
 *
 *  2. SPECTRA observability — Spectra auto-attributes each intercepted AI request
 *     to `auth()->user()`, but queued jobs and console commands have no auth user,
 *     so their embed / rerank / OCR / transcription / analysis calls would be
 *     recorded UNATTRIBUTED. The outermost scope therefore binds the workspace as
 *     Spectra's global trackable via {@see Spectra::forUser()} and clears it on
 *     exit (so a worker can't leak one job's workspace onto the next). In an HTTP
 *     request with an authenticated user Spectra's auto-tracking still wins — see
 *     RequestContext::captureContext() — so this only fills the no-auth gap.
 *
 * Bound as a singleton (see DomainServiceProvider) so one chat turn / queued job
 * shares one scope. Scopes are a stack: nested track() calls flush independently
 * and restore the outer scope. add() outside any scope (an LLM call with no owner
 * in context, e.g. subdomain generation) is silently dropped.
 */
class TokenUsageContext
{
    /**
     * @var array<int, array{workspace: int|null, tokens: int, purpose: string|null}>
     */
    private array $stack = [];

    /**
     * Open a scope attributed to $workspaceId, run $work, and flush the
     * accumulated tokens (one event) when it returns OR throws. Returns
     * whatever $work returns.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function track(?int $workspaceId, Closure $work, ?string $purpose = null): mixed
    {
        // Only the outermost scope owns Spectra's global trackable: nested
        // track() calls share the same workspace, and clearing on every pop
        // would unbind it before the outer work finished.
        $isOutermost = $this->stack === [];

        $this->stack[] = ['workspace' => $workspaceId, 'tokens' => 0, 'purpose' => $purpose];

        if ($isOutermost) {
            $this->bindSpectraWorkspace($workspaceId);
        }

        try {
            return $work();
        } finally {
            $this->flush(array_pop($this->stack));

            if ($isOutermost) {
                $this->unbindSpectraWorkspace();
            }
        }
    }

    /**
     * Add tokens to the innermost open scope. No-op when no scope is open or the
     * count is non-positive.
     */
    public function add(int $tokens): void
    {
        if ($tokens <= 0 || $this->stack === []) {
            return;
        }

        $this->stack[array_key_last($this->stack)]['tokens'] += $tokens;
    }

    /**
     * Read the OpenAI-compatible `usage` block off an LLM / embeddings / rerank
     * response and {@see add()} its token total to the active scope: the billed
     * `total_tokens` when present, otherwise prompt+completion. The single place
     * that knows the provider usage shape — every call site (completion, embed,
     * rerank) just hands its response here. Analytics only, so a missing/garbled
     * payload is swallowed rather than allowed to break the LLM call.
     */
    public function addFromResponse(Response $response): void
    {
        try {
            $usage = $response->json('usage');

            if (! is_array($usage)) {
                return;
            }

            $this->add((int) ($usage['total_tokens']
                ?? (($usage['prompt_tokens'] ?? 0) + ($usage['completion_tokens'] ?? 0))));
        } catch (Throwable) {
            // Analytics only — never let usage bookkeeping break the call.
        }
    }

    /**
     * The workspace id of the innermost open scope, or null when none is open.
     */
    public function current(): ?int
    {
        return $this->stack === [] ? null : $this->stack[array_key_last($this->stack)]['workspace'];
    }

    /**
     * @param  array{workspace: int|null, tokens: int, purpose: string|null}  $frame
     */
    private function flush(array $frame): void
    {
        if ($frame['workspace'] === null || $frame['tokens'] <= 0) {
            return;
        }

        // Recording is analytics-only and must never break the operation that
        // produced the tokens; swallow any dispatch/DB hiccup.
        try {
            LlmTokensConsumed::dispatch($frame['workspace'], $frame['tokens'], $frame['purpose']);
        } catch (Throwable) {
            // ignore — a missed analytics increment is acceptable.
        }
    }

    /**
     * Bind the workspace as Spectra's global trackable so the AI requests made
     * inside this scope are attributed to it. Spectra (>=1.2) resolves the id to
     * the configured user model itself. Like all bookkeeping here, it must never
     * break the AI op.
     */
    private function bindSpectraWorkspace(?int $workspaceId): void
    {
        if ($workspaceId === null) {
            return;
        }

        try {
            Spectra::forUser($workspaceId);
        } catch (Throwable) {
            // Observability attribution must never break the AI operation.
        }
    }

    /**
     * Clear Spectra's globals when the outermost scope exits. Critical on a queue
     * worker, where the singleton persists across jobs — without this, one job's
     * workspace would leak onto the next job's AI requests.
     */
    private function unbindSpectraWorkspace(): void
    {
        try {
            Spectra::clearGlobals();
        } catch (Throwable) {
            // ignore — a stale global is corrected by the next scope's bind.
        }
    }
}
