<?php

namespace App\Http\Controllers\Settings\Account;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Account\CreateApiKeyRequest;
use App\Auth\Actions\ApiKeys\CreateApiKeyAction;
use App\Auth\Actions\ApiKeys\DeleteApiKeyAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiKeysController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless(has_team_permission('account.api_keys'), 404);

        $tokens = $request->user()->tokens()
            ->latest()
            ->get(['id', 'name', 'abilities', 'last_used_at', 'created_at'])
            ->map(fn ($t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'abilities' => $t->abilities,
                'last_used_at' => $t->last_used_at?->diffForHumans(),
                'created_at' => $t->created_at?->toFormattedDateString(),
            ])
            ->all();

        return Inertia::render('settings/ApiKeys', [
            'tokens' => $tokens,
            'created_token' => $request->session()->get('created_token'),
        ]);
    }

    public function store(CreateApiKeyRequest $request, CreateApiKeyAction $createApiKey): RedirectResponse
    {
        abort_unless(has_team_permission('account.api_keys'), 404);

        $token = $createApiKey->execute($request->toData());

        return back()->with('created_token', [
            'id' => $token->id,
            'name' => $token->name,
            'plaintext' => $token->plaintext,
        ]);
    }

    public function destroy(Request $request, int $tokenId, DeleteApiKeyAction $deleteApiKey): RedirectResponse
    {
        abort_unless(has_team_permission('account.api_keys'), 404);

        /** @var User $user */
        $user = $request->user();

        $deleteApiKey->execute($user, $tokenId);

        return back();
    }
}
