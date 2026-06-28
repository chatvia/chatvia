<?php

namespace App\Http\Controllers\Agent\SubDomain;

use App\Hosting\Subdomain;
use Domain\Llm\Usage\TokenUsageContext;
use Illuminate\Http\Request;

class AgentsGenerateRandomSubDomainController
{
    public function __invoke(Request $request, Subdomain $subdomain): array
    {
        // Attribute the generation LLM call's tokens to the owning workspace.
        $name = app(TokenUsageContext::class)->track(
            $request->user()->workspace()->id,
            fn (): string => $subdomain->generateRandomSubDomain(),
            'subdomain',
        );

        return ['name' => $name];
    }
}
