<?php

namespace App\Http\Controllers\Settings\Account;

use App\Http\Requests\Settings\Account\CreateApiKeyRequest;
use App\Identity\ApiKey\Actions\CreateApiKeyAction;
use App\Identity\ApiKey\Actions\DeleteApiKeyAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * API keys (personal access tokens): list, create (the plain key is shown
 * once) and revoke.
 */
class ApiKeyController
{
    public function edit(Request $request): Response
    {
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

        return Inertia::render('workspace/integrations/ApiKeys', [
            'tokens' => $tokens,
            'created_token' => $request->session()->get('created_token'),
        ]);
    }

    public function store(CreateApiKeyRequest $request, CreateApiKeyAction $createApiKey): RedirectResponse
    {
        $token = $createApiKey->execute($request->toData());

        return back()->with('created_token', [
            'id' => $token->id,
            'name' => $token->name,
            'plaintext' => $token->plaintext,
        ]);
    }

    public function destroy(Request $request, int $tokenId, DeleteApiKeyAction $deleteApiKey): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Only the caller's own keys exist for them.
        abort_unless($user->tokens()->whereKey($tokenId)->exists(), 404);

        $deleteApiKey->execute($user, $tokenId);

        return back();
    }
}
