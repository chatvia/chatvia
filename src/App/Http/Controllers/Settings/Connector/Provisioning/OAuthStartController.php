<?php

namespace App\Http\Controllers\Settings\Connector\Provisioning;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Connector\Services\OAuth\ConnectorMisconfiguredException;
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
        abort_unless($connector instanceof ConnectorData && $connector->authStrategy === 'oauth' && ! $connector->comingSoon, 404);

        /** @var User $user */
        $user = $request->user();

        try {
            $url = $this->flow->begin($user, $connector);
        } catch (ConnectorMisconfiguredException $e) {
            // Operator hasn't provisioned this connector's OAuth credentials.
            // Report it (so we get paged) but never leak the env-setup detail to
            // the visitor — they just see a generic failure. The underlying
            // message is only echoed when debugging locally.
            report($e);

            return redirect()
                ->route('integrations.connectors.edit')
                ->with('error', config('app.debug')
                    ? $e->getMessage()
                    : 'This connector is not available right now. Please try again later.');
        } catch (McpOAuthException $e) {
            return redirect()
                ->route('integrations.connectors.edit')
                ->with('error', $e->getMessage());
        }

        return redirect()->away($url);
    }
}
