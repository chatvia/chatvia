<?php

namespace Domain\Llm\Usage;

use App\Identity\Workspace\Models\Workspace;
use Closure;
use Spectra\Facades\Spectra;
use Throwable;

/**
 * Request/job-scoped boundary that attributes AI usage to a {@see Workspace}
 * in Spectra's request log.
 *
 * Queued jobs and console commands have no signed-in user, so their embed /
 * rerank / OCR / transcription / analysis calls would be recorded
 * UNATTRIBUTED. The outermost {@see track()} scope therefore binds the
 * workspace as Spectra's global trackable and clears it on exit (so a worker
 * can't leak one job's workspace onto the next). Spectra's own user
 * auto-tracking is off (`spectra.tracking.auto_track_user`), so a signed-in
 * teammate or chatter never takes the cost away from the workspace.
 *
 * Bound as a singleton (see DomainServiceProvider) so one chat turn / queued
 * job shares one scope. Scopes are a stack: nested track() calls share the
 * outer scope's workspace.
 */
class TokenUsageContext
{
    /** @var list<int|null> */
    private array $stack = [];

    /**
     * Open a scope attributed to $workspaceId and run $work. Returns whatever
     * $work returns.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function track(?int $workspaceId, Closure $work): mixed
    {
        // Only the outermost scope owns Spectra's global trackable: nested
        // track() calls share the same workspace, and clearing on every pop
        // would unbind it before the outer work finished.
        $isOutermost = $this->stack === [];

        $this->stack[] = $workspaceId;

        if ($isOutermost) {
            $this->bindSpectraWorkspace($workspaceId);
        }

        try {
            return $work();
        } finally {
            array_pop($this->stack);

            if ($isOutermost) {
                $this->unbindSpectraWorkspace();
            }
        }
    }

    /**
     * The workspace id of the innermost open scope, or null when none is open.
     */
    public function current(): ?int
    {
        return $this->stack === [] ? null : array_last($this->stack);
    }

    /**
     * Bind the workspace as Spectra's global trackable so the AI requests made
     * inside this scope are attributed to it. It must never break the AI op.
     */
    private function bindSpectraWorkspace(?int $workspaceId): void
    {
        if ($workspaceId === null) {
            return;
        }

        try {
            $workspace = Workspace::query()->find($workspaceId);

            if ($workspace instanceof Workspace) {
                Spectra::forTrackable($workspace);
            }
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
