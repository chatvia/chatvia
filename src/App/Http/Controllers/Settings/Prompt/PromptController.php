<?php

namespace App\Http\Controllers\Settings\Prompt;

use App\Http\Controllers\Controller;
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
 * Account-level reusable prompts dashboard page. The user authors a prompt
 * once here, then attaches it to any agent from the agent's Persona page.
 */
class PromptController extends Controller
{
    /**
     * JSON list of the user's prompts — consumed by the agent Persona editor's
     * "Saved prompts" picker.
     */
    public function index(Request $request): AgentPromptPickerListData
    {
        abort_unless(has_team_permission('account.prompts'), 404);

        $prompts = $request->user()->prompts()
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
        abort_unless(has_team_permission('account.prompts'), 404);

        return Inertia::render('settings/Prompts', [
            'prompts' => $request->user()->prompts()
                ->withCount('agents')
                ->latest()
                ->get()
                ->map(fn (AgentPrompt $prompt): AgentPromptRowData => AgentPromptRowData::fromModel($prompt)),
        ]);
    }

    public function store(CreatePromptRequest $request, CreatePromptAction $createPrompt): RedirectResponse
    {
        abort_unless(has_team_permission('account.prompts'), 404);

        $createPrompt->execute($request->toData());

        return back();
    }

    public function update(UpdatePromptRequest $request, string $external_id, UpdatePromptAction $updatePrompt): RedirectResponse
    {
        abort_unless(has_team_permission('account.prompts'), 404);

        /** @var User $user */
        $user = $request->user();

        $updatePrompt->execute($user, $external_id, $request->toData());

        return back();
    }

    public function destroy(Request $request, string $external_id): RedirectResponse
    {
        abort_unless(has_team_permission('account.prompts'), 404);

        /** @var User $user */
        $user = $request->user();

        // Library prompt delete. Agents referencing it have agent_prompt_id
        // nulled by the FK (nullOnDelete), falling back to their own instructions.
        $user->prompts()->where('external_id', $external_id)->delete();

        return back();
    }
}
