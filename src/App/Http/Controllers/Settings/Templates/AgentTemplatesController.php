<?php

namespace App\Http\Controllers\Settings\Templates;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CreateTemplateRequest;
use App\Http\Requests\Settings\UpdateTemplateRequest;
use Domain\Agents\Actions\BuildAgentTemplateEditorPropsAction;
use Domain\Agents\Actions\CreateTemplateAction;
use Domain\Agents\Actions\DeleteTemplateAction;
use Domain\Agents\Actions\ListTemplatesForManagementPageAction;
use Domain\Agents\Actions\UpdateTemplateAction;
use Domain\Agents\Data\Resources\AgentTemplateEditorData;
use Domain\Agents\Models\AgentTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Account-level reusable agent templates (Settings → Templates). The user
 * authors a template once here, then attaches it to any agent; while attached
 * the agent reads its config from the template at runtime.
 */
class AgentTemplatesController extends Controller
{
    public function index(Request $request, ListTemplatesForManagementPageAction $list): Response
    {
        abort_unless(has_team_permission('account.templates'), 404);

        return Inertia::render('settings/Templates', [
            'templates' => $list->execute($request->user()),
        ]);
    }

    public function create(Request $request, BuildAgentTemplateEditorPropsAction $editorProps): Response
    {
        abort_unless(has_team_permission('account.templates'), 404);

        return Inertia::render('settings/templates/Editor', [
            'template' => null,
            ...$editorProps->execute($request->user())->toArray(),
        ]);
    }

    public function store(CreateTemplateRequest $request, CreateTemplateAction $createTemplate): RedirectResponse
    {
        abort_unless(has_team_permission('account.templates'), 404);

        $createTemplate->execute($request->toData());

        return to_route('settings.templates.index');
    }

    public function edit(Request $request, string $external_id, BuildAgentTemplateEditorPropsAction $editorProps): Response
    {
        abort_unless(has_team_permission('account.templates'), 404);

        /** @var AgentTemplate $template */
        $template = $request->user()->templates()
            ->with('agentPrompt')
            ->where('external_id', $external_id)
            ->firstOrFail();

        return Inertia::render('settings/templates/Editor', [
            'template' => AgentTemplateEditorData::fromModel($template),
            ...$editorProps->execute($request->user())->toArray(),
        ]);
    }

    public function update(UpdateTemplateRequest $request, string $external_id, UpdateTemplateAction $updateTemplate): RedirectResponse
    {
        abort_unless(has_team_permission('account.templates'), 404);

        /** @var User $user */
        $user = $request->user();

        $updateTemplate->execute($user, $external_id, $request->toData());

        return to_route('settings.templates.index');
    }

    public function destroy(Request $request, string $external_id, DeleteTemplateAction $deleteTemplate): RedirectResponse
    {
        abort_unless(has_team_permission('account.templates'), 404);

        /** @var User $user */
        $user = $request->user();

        $deleteTemplate->execute($user, $external_id);

        return back();
    }
}
