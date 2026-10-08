<?php

namespace App\Http\Controllers\Settings\Prompt;

use App\Http\Requests\Settings\Prompt\CreatePromptRequest;
use App\Http\Requests\Settings\Prompt\UpdatePromptRequest;
use App\Identity\Workspace\Models\User;
use Domain\Prompt\Actions\CreatePromptAction;
use Domain\Prompt\Actions\UpdatePromptAction;
use Domain\Prompt\Data\Resources\AgentPromptPickerData;
use Domain\Prompt\Data\Resources\AgentPromptPickerListData;
use Domain\Prompt\Data\Resources\AgentPromptRowData;
use Domain\Prompt\Models\AgentPrompt;
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
        return Inertia::render('workspace/library/Prompts', [
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

    public function update(UpdatePromptRequest $request, string $external_id, UpdatePromptAction $updatePrompt): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updatePrompt->execute($user, $external_id, $request->toData());

        return back();
    }

    public function destroy(Request $request, string $external_id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Library prompt delete. Agents referencing it have agent_prompt_id
        // nulled by the FK (nullOnDelete), falling back to their own instructions.
        $user->workspace()->prompts()->where('external_id', $external_id)->delete();

        return back();
    }
}
