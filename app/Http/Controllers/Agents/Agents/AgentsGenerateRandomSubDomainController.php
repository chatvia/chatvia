<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Support\Subdomain;
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
