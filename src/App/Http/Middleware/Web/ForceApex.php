<?php

namespace App\Http\Middleware\Web;

use Closure;
use Illuminate\Http\Request;

class ForceApex
{
    public function handle(Request $request, Closure $next)
    {
        // Only canonicalize the host in production; local/staging hosts (chatvia.test, ...) are left alone.
        if (! app()->isProduction()) {
            return $next($request);
        }

        // The apex chatvia.ai is canonical — it is the passkey/WebAuthn Relying Party,
        // which rejects subdomain origins. Redirect the www subdomain to the apex so the
        // forced host matches the RP. Every other host (the apex itself, agent chat
        // subdomains, ...) passes through untouched.
        if ($request->getHost() === 'www.chatvia.ai') {
            return redirect()->to(
                $request->getScheme().'://chatvia.ai'.$request->getRequestUri(),
                301
            );
        }

        return $next($request);
    }
}
