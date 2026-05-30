<?php

namespace Domain\Agents\Actions;

use App\Auth\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteTemplateAction
{
    public function __construct(
        private DetachAgentTemplateAction $detach,
    ) {}

    /**
     * Delete a template. Each attached agent is first detached (which copies the
     * template's current config onto the agent) so the agent keeps its
     * appearance/widget/models/starters/prompt instead of silently reverting to
     * defaults via the nullOnDelete FK.
     */
    public function execute(User $user, string $externalId): void
    {
        $template = $user->templates()->where('external_id', $externalId)->first();

        if (! $template) {
            return;
        }

        DB::transaction(function () use ($template): void {
            foreach ($template->agents as $agent) {
                $this->detach->execute($agent);
            }

            $template->delete();
        });
    }
}
