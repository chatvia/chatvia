<?php

namespace App\Http\Controllers\Settings\Prompt;

use App\Http\Requests\Settings\Prompt\CreatePromptRequest;
use App\Http\Requests\Settings\Prompt\UpdatePromptRequest;
use Domain\Agent\Prompt\Actions\CreatePromptAction;
use Domain\Agent\Prompt\Actions\UpdatePromptAction;
use Domain\Agent\Prompt\Data\Resources\AgentPromptPickerData;
use Domain\Agent\Prompt\Data\Resources\AgentPromptPickerListData;
use Domain\Agent\Prompt\Data\Resources\AgentPromptRowData;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;

/**
 * Workspace-level reusable prompts dashboard page. A prompt is authored once
 * here, then attached to any agent from the agent's Persona page. Prompts are
 * owned by the workspace, so every query goes through `workspace()` — a
 * teammate sees and manages the same prompts as the owner.
 */
class PromptController
{
    /**
     * JSON list of the workspace's prompts — consumed by the agent Persona
     * editor's "Saved prompts" picker.
     */
    public function index(Request $request): AgentPromptPickerListData
    {
        $prompts = $request->user()->workspace()->prompts()
            ->latest()
            ->get()
            ->map(fn (AgentPrompt $prompt): AgentPromptPickerData => AgentPromptPickerData::fromModel($prompt));

        return new AgentPromptPickerListData(
            data: new DataCollection(AgentPromptPickerData::class, $prompts),
        );
    }

    /**
     * Settings → Prompts page — each row carries the per-prompt agent
     * attachment count.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('dashboard/workspace/pages/library/Prompts', [
            'prompts' => $request->user()->workspace()->prompts()
                ->withCount('agents')
                ->latest()
                ->get()
                ->map(fn (AgentPrompt $prompt): AgentPromptRowData => AgentPromptRowData::fromModel($prompt)),
        ]);
    }

    public function store(CreatePromptRequest $request, CreatePromptAction $createPrompt): RedirectResponse
    {
        $createPrompt->execute($request->toData());

        return back();
    }

    public function update(UpdatePromptRequest $request, AgentPrompt $prompt, UpdatePromptAction $updatePrompt): RedirectResponse
    {
        $updatePrompt->execute($prompt, $request->toData());

        return back();
    }

    public function destroy(AgentPrompt $prompt): RedirectResponse
    {
        // Library prompt delete. Agents referencing it have agent_prompt_id
        // nulled by the FK (nullOnDelete), falling back to their own instructions.
        $prompt->delete();

        return back();
    }
}
