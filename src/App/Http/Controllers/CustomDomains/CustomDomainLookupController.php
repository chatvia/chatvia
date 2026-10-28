<?php

declare(strict_types=1);

namespace App\Http\Controllers\CustomDomains;

use Domain\Agent\Services\CustomDomainLookup;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The question a web server with on-demand certificates asks before it gets
 * one for a new domain (Caddy's `on_demand_tls { ask … }`): `?domain=` is
 * answered 200 when it is an agent's saved custom domain, verified or not
 * (the check that verifies it needs the certificate), and 404 otherwise. That
 * keeps the server from asking for certificates for any name that points at
 * it. The answer names no agent or workspace.
 */
class CustomDomainLookupController
{
    public function __invoke(Request $request, CustomDomainLookup $domains): Response
    {
        $host = $request->string('domain')->toString();

        abort_unless(filled($host) && $domains->isSaved($host), 404);

        return response('', 200);
    }
}
