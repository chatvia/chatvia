<?php

namespace App\Http\Controllers\Settings;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CreatePromptRequest;
use App\Http\Requests\Settings\UpdatePromptRequest;
use Domain\Agents\Actions\CreatePromptAction;
use Domain\Agents\Actions\DeletePromptAction;
use Domain\Agents\Actions\UpdatePromptAction;
use Domain\Agents\Models\AgentPrompt;
use Illuminate\Http\JsonResponse;
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
    public function edit(Request $request): Response
    {
        $prompts = $request->user()->prompts()
            ->withCount('agents')
            ->latest()
            ->get()
            ->map(fn (AgentPrompt $p): array => [
                'id' => $p->external_id,
                'name' => $p->name,
                'prompt' => $p->prompt,
                'agents_count' => $p->agents_count,
                'created_at' => $p->created_at?->toFormattedDateString(),
            ])
            ->all();

        return Inertia::render('settings/Prompts', ['prompts' => $prompts]);
    }

    /**
     * JSON list of the user's prompts — used by the agent Persona/Instructions
     * editor to populate the "Saved prompts" picker.
     */
    public function list(Request $request): JsonResponse
    {
        $prompts = $request->user()->prompts()
            ->latest()
            ->get()
            ->map(fn (AgentPrompt $p): array => [
                'id' => $p->external_id,
                'name' => $p->name,
                'prompt' => $p->prompt,
            ])
            ->all();

        return response()->json(['data' => $prompts]);
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

    public function destroy(Request $request, string $external_id, DeletePromptAction $deletePrompt): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $deletePrompt->execute($user, $external_id);

        return back();
    }
}
