<?php

namespace App\Http\Controllers\Settings\Connectors\Provisioning;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use Domain\Connectors\Services\ConnectorCatalog;
use Domain\Connectors\Services\OAuth\McpOAuthException;
use Domain\Connectors\Services\OAuth\McpOAuthFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OAuthStartController extends Controller
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
        private readonly McpOAuthFlow $flow,
    ) {}

    public function __invoke(Request $request, string $slug): RedirectResponse
    {
        abort_unless(has_team_permission('account.connectors'), 404);

        $connector = $this->catalog->find($slug);
        abort_unless($connector && $connector->authStrategy === 'oauth' && ! $connector->comingSoon, 404);

        /** @var User $user */
        $user = $request->user();

        try {
            $url = $this->flow->begin($user, $connector);
        } catch (McpOAuthException $e) {
            return redirect()
                ->route('settings.connectors.edit')
                ->with('error', $e->getMessage());
        }

        return redirect()->away($url);
    }
}
