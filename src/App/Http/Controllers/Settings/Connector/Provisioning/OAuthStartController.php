<?php

namespace App\Http\Controllers\Settings\Connector\Provisioning;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Connector\Services\OAuth\McpOAuthException;
use Domain\Integration\Connector\Services\OAuth\McpOAuthFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OAuthStartController
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
        private readonly McpOAuthFlow $flow,
    ) {}

    public function __invoke(Request $request, string $slug): RedirectResponse
    {
        $connector = $this->catalog->find($slug);
        abort_unless($connector && $connector->authStrategy === 'oauth' && ! $connector->comingSoon, 404);

        /** @var User $user */
        $user = $request->user();

        try {
            $url = $this->flow->begin($user, $connector);
        } catch (McpOAuthException $e) {
            return redirect()
                ->route('integrations.connectors.edit')
                ->with('error', $e->getMessage());
        }

        return redirect()->away($url);
    }
}
