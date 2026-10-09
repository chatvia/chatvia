<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Supplies the browser-session secret that ties a connector OAuth flow to the
 * session that started it (see CompleteConnectorOAuthAction). The secret lives
 * only in the user's session, so an authorize link opened in another browser
 * or account cannot finish the flow. It is kept across session-id regeneration
 * because Laravel carries session data over when it rotates the id.
 */
trait BindsConnectorOAuthToSession
{
    protected function connectorOAuthSessionBinding(Request $request): string
    {
        $key = 'connectors.oauth.session_binding';
        $binding = $request->session()->get($key);

        if (! is_string($binding) || $binding === '') {
            $binding = Str::random(48);
            $request->session()->put($key, $binding);
        }

        return $binding;
    }
}
