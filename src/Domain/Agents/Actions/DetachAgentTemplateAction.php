<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;
use Illuminate\Support\Facades\DB;

class DetachAgentTemplateAction
{
    /**
     * Detach the agent's template by copying the template's persona-level
     * config (prompt + public flag) onto the agent and clearing
     * `agent_template_id`. Per-surface settings are deliberately not copied:
     * with three independent surfaces, copying per-surface JSON into the
     * agent's `model_settings` groups requires the Setup UI restructure
     * (Task #6) — until then, detaching reverts the agent to defaults for
     * Hosted Chat / Widget / Bar so the user re-configures explicitly.
     */
    public function execute(Agent $agent): void
    {
        $template = $agent->agentTemplate;

        if (! $template) {
            return;
        }

        DB::transaction(function () use ($agent, $template): void {
            $agent->agent_prompt_id = $template->agent_prompt_id;
            // Access control lives on the agent column directly; the template's
            // is_public read-through stops once it detaches, so copy explicitly.
            $agent->is_public = $template->is_public;
            $agent->agent_template_id = null;
            $agent->save();
        });
    }
}
