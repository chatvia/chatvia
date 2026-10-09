<?php

namespace App\Http\Controllers\Settings\Connector\Provisioning;

use App\Http\Controllers\Concerns\BindsConnectorOAuthToSession;
use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\OAuth\BeginConnectorOAuthAction;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Domain\Integration\Connector\Exceptions\ConnectorMisconfiguredException;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Starts the OAuth + PKCE flow for a catalog connector and redirects the
 * browser to the provider's consent screen. The flow is bound to this user
 * and browser session; the callback checks both.
 */
class OAuthStartController
{
    use BindsConnectorOAuthToSession;

    public function __invoke(Request $request, string $slug, ConnectorCatalog $catalog, BeginConnectorOAuthAction $begin): RedirectResponse
    {
        $connector = $catalog->find($slug);
        abort_unless($connector instanceof ConnectorData && $connector->authStrategy === ConnectorAuthStrategy::Oauth && ! $connector->comingSoon, 404);

        /** @var User $user */
        $user = $request->user();

        try {
            $url = $begin->execute($user, $connector, $this->connectorOAuthSessionBinding($request));
        } catch (ConnectorMisconfiguredException $e) {
            // The operator hasn't provisioned this connector's OAuth
            // credentials. Report it, but never show the visitor the
            // environment detail — only when debugging locally.
            report($e);

            return $this->backWithError(config('app.debug')
                ? $e->getMessage()
                : __('This connector is not available right now. Please try again later.'));
        } catch (McpOAuthException $e) {
            return $this->backWithError($e->getMessage());
        }

        return redirect()->away($url);
    }

    private function backWithError(string $message): RedirectResponse
    {
        return redirect()->route('integrations.connectors.edit')->with('error', $message);
    }
}
