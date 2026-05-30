<?php

namespace App\Http\Controllers\Settings\Prompts;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CreatePromptRequest;
use App\Http\Requests\Settings\UpdatePromptRequest;
use Domain\Agents\Actions\CreatePromptAction;
use Domain\Agents\Actions\DeletePromptAction;
use Domain\Agents\Actions\ListPromptsForManagementPageAction;
use Domain\Agents\Actions\ListPromptsForPickerAction;
use Domain\Agents\Actions\UpdatePromptAction;
use Domain\Agents\Data\Resources\AgentPromptPickerListData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Account-level reusable prompts dashboard page. The user authors a prompt
 * once here, then attaches it to any agent from the agent's Persona page.
 */
class PromptsController extends Controller
{
    /**
     * JSON list of the user's prompts — consumed by the agent Persona editor's
     * "Saved prompts" picker.
     */
    public function index(Request $request, ListPromptsForPickerAction $list): AgentPromptPickerListData
    {
        abort_unless(has_team_permission('account.prompts'), 404);

        return $list->execute($request->user());
    }

    public function edit(Request $request, ListPromptsForManagementPageAction $list): Response
    {
        abort_unless(has_team_permission('account.prompts'), 404);

        return Inertia::render('settings/Prompts', [
            'prompts' => $list->execute($request->user()),
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

    public function destroy(Request $request, string $external_id, DeletePromptAction $deletePrompt): RedirectResponse
    {
        abort_unless(has_team_permission('account.prompts'), 404);

        /** @var User $user */
        $user = $request->user();

        $deletePrompt->execute($user, $external_id);

        return back();
    }
}
