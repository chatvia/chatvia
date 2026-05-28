<?php

namespace App\Http\Controllers\Settings;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CreateAgentFunctionRequest;
use App\Http\Requests\Settings\UpdateAgentFunctionRequest;
use Domain\Agents\Actions\CreateAgentFunctionAction;
use Domain\Agents\Actions\DeleteAgentFunctionAction;
use Domain\Agents\Actions\ToggleAgentFunctionAction;
use Domain\Agents\Actions\UpdateAgentFunctionAction;
use Domain\Agents\Models\AgentFunction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Account-level webhook functions dashboard page. Mirrors `/api/v1/functions`.
 */
class FunctionsController extends Controller
{
    public function edit(Request $request): Response
    {
        $functions = $request->user()->functions()
            ->latest()
            ->get()
            ->map(fn (AgentFunction $f): array => [
                'id' => $f->external_id,
                'name' => $f->name,
                'description' => $f->description,
                'parameters_schema' => json_encode($f->parameters_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'webhook_url' => $f->webhook_url,
                'webhook_method' => $f->webhook_method,
                'auth_type' => $f->auth_type,
                // Header name isn't a secret, so prefill it; the value never leaves the server.
                'auth_header_name' => $f->auth_type === 'header' ? ($f->auth_config['name'] ?? '') : '',
                'has_auth_value' => $f->auth_config !== null,
                'enabled' => $f->enabled,
                'created_at' => $f->created_at?->toFormattedDateString(),
                'agents_count' => $f->agents()->count(),
            ])
            ->all();

        return Inertia::render('settings/Functions', ['functions' => $functions]);
    }

    public function store(CreateAgentFunctionRequest $request, CreateAgentFunctionAction $createFunction): RedirectResponse
    {
        $createFunction->execute($request->toData());

        return back();
    }

    public function update(UpdateAgentFunctionRequest $request, string $external_id, UpdateAgentFunctionAction $updateFunction): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateFunction->execute($user, $external_id, $request->toData());

        return back();
    }

    public function toggle(Request $request, string $external_id, ToggleAgentFunctionAction $toggleFunction): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $toggleFunction->execute($user, $external_id);

        return back();
    }

    public function destroy(Request $request, string $external_id, DeleteAgentFunctionAction $deleteFunction): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $deleteFunction->execute($user, $external_id);

        return back();
    }
}
