<?php

namespace App\Http\Controllers\Agent\SubDomain;

use App\Hosting\Subdomain;
use Illuminate\Http\Request;

class AgentsGenerateRandomSubDomainController
{
    public function __invoke(Request $request, Subdomain $subdomain): array
    {
        return [
            'name' => $subdomain->generateRandomSubDomain(),
        ];
    }
}
