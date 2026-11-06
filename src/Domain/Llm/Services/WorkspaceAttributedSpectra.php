<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use App\Identity\Workspace\Models\Workspace;
use Illuminate\Support\Facades\Context;
use Spectra\Spectra;
use Spectra\Support\Tracking\RequestContext;

/**
 * Attributes every AI call Spectra records to the workspace it is made for, so
 * a workspace's dashboard shows its calls and nothing else. The workspace comes
 * from the hidden Context the request, or the job the request queued, already
 * carries, which covers every call of a trace: Spectra hides a trace from a
 * dashboard unless all its calls belong to that dashboard. A call with no
 * workspace (an admin's checks of the AI services) belongs to none.
 */
class WorkspaceAttributedSpectra extends Spectra
{
    /** @param  array<string, mixed>  $options */
    #[\Override]
    public function startRequest(string $provider, string $model, array $options = []): RequestContext
    {
        $workspaceId = Context::getHidden('workspace_id');

        if ($workspaceId !== null && ! isset($options['trackable_type'])) {
            $options = [
                ...$options,
                'trackable_type' => new Workspace()->getMorphClass(),
                'trackable_id' => (string) $workspaceId,
            ];
        }

        return parent::startRequest($provider, $model, $options);
    }
}
