<?php

namespace App\Http\Controllers;

use App\Support\Subdomain;
use Illuminate\Http\Request;

class GenerateRandomSubDomainController
{
    public function __invoke(Request $request, Subdomain $subdomain)
    {
        return [
            'name' => $subdomain->generateRandomSubDomain(),
        ];
    }
}
