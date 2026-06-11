<?php

namespace App\Http\Controllers\Agent\Setup;

use App\Http\Requests\Agent\UpdateAgentDomainRequest;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;

class UpdateAgentDomainController
{
    public function __invoke(
        UpdateAgentDomainRequest $request,
        Agent $agent,
    ): RedirectResponse {
        abort_if($request->user()->cannot('update', $agent), 404);

        $newValue = $request->toData()->domain;

        // Quota gate: setting a (new) domain requires available capacity.
        // Clearing the domain is always allowed.
        if ($newValue !== null && $newValue !== $agent->domain) {
            $user = $request->user();
            $total = (int) ($user->quota?->totalCustomDomainsLimit() ?? 0);
            $used = (int) $user->agents()
                ->whereNotNull('domain')
                ->where('domain', '!=', '')
                ->where('id', '!=', $agent->id)
                ->count();

            if ($total === 0) {
                return back()->withErrors([
                    'domain' => 'Custom domains require a Pro plan or an active Custom Domain subscription.',
                ]);
            }

            if ($used >= $total) {
                return back()->withErrors([
                    'domain' => 'You\'ve used all your custom domains. Increase your Custom Domain subscription to add another.',
                ]);
            }
        }

        $agent->update(['domain' => $newValue]);

        return back()->with('success', $newValue ? 'Custom domain updated.' : 'Custom domain removed.');
    }
}
