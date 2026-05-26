<?php

namespace App\Http\Controllers\Settings;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CreateAgentFunctionRequest;
use Domain\Agents\Actions\CreateAgentFunctionAction;
use Domain\Agents\Actions\DeleteAgentFunctionAction;
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
            ->get(['id', 'external_id', 'name', 'description', 'webhook_url', 'webhook_method', 'auth_type', 'enabled', 'created_at'])
            ->map(fn (AgentFunction $f): array => [
                'id' => $f->external_id,
                'name' => $f->name,
                'description' => $f->description,
                'webhook_url' => $f->webhook_url,
                'webhook_method' => $f->webhook_method,
                'auth_type' => $f->auth_type,
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

    public function destroy(Request $request, string $external_id, DeleteAgentFunctionAction $deleteFunction): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $deleteFunction->execute($user, $external_id);

        return back();
    }
}
