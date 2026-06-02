<?php

namespace App\Http\Controllers\Agent\Identity;

use App\Http\Controllers\Controller;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Mint a fresh `auth_secret` for this agent — invalidates every sign-in
 * link/token previously issued for it (chat `?token=` + widget `data-auth`).
 */
class RegenerateAgentAuthSecretController extends Controller
{
    public function __invoke(Request $request, Agent $agent): RedirectResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        $agent->regenerateAuthSecret();

        return back();
    }
}
