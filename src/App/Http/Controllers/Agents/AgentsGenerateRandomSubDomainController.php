<?php

namespace App\Http\Controllers\Agents;

use App\Hosting\Subdomain;
use Illuminate\Http\Request;

class AgentsGenerateRandomSubDomainController
{
    public function __invoke(Request $request, Subdomain $subdomain)
    {
        return [
            'name' => $subdomain->generateRandomSubDomain(),
        ];
    }
}
