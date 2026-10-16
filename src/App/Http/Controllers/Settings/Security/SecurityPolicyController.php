<?php

namespace App\Http\Controllers\Settings\Security;

use App\Http\Requests\Settings\Security\UpdateSecurityPolicyRequest;
use Domain\Security\Actions\UpdateSecurityPolicyAction;
use Domain\Security\Data\BlockedVisitorRowData;
use Domain\Security\Data\RetentionLimitsData;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Data\WorkspaceSecurityPolicyData;
use Domain\Security\Exceptions\UnsafeWebhookUrlException;
use Domain\Security\Models\BlockedVisitor;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Domain\Security\Queries\ActiveVisitorBlocksQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Support\Localization\Country\Models\Country;

/**
 * Workspace Security & Compliance settings: the network access control (IP
 * allowlist and country rules), retention, the SIEM webhook and the visitors
 * blocked from chatting (made from an agent's Guardrails activity). Viewing
 * and saving require the `account.security` team permission.
 */
class SecurityPolicyController
{
    public function edit(Request $request, ActiveVisitorBlocksQuery $blocks): Response
    {
        $workspace = $request->user()->workspace();

        // Read without firstOrCreate so merely viewing never writes a policy
        // row; fall back to defaults when none exists yet.
        $policy = WorkspaceSecurityPolicy::query()
            ->where('workspace_id', $workspace->id)
            ->first();

        // The viewer's own country (lowercase ISO code) so the page can warn —
        // before they ever hit Save — that a geo rule would lock them out. Prefers
        // Cloudflare's CF-IPCountry (the real visitor, not CF's edge IP); null when
        // unresolved ⇒ no warning, mirroring the gate's geo fail-open.
        $currentCountry = $request->country();

        return Inertia::render('workspace/security/Security', [
            'policy' => $policy ? WorkspaceSecurityPolicyData::fromModel($policy) : new WorkspaceSecurityPolicyData,
            // Country picker options for the geo rules. Codes are the lowercase
            // ISO-3166 alpha-2 values the geo gate stores and matches against.
            'countries' => Country::options(),
            'current_country' => $currentCountry,
            'blocked_visitors' => $blocks->forWorkspace($workspace->id)
                ->map(fn (BlockedVisitor $block): BlockedVisitorRowData => BlockedVisitorRowData::fromModel($block)),
            'limits' => RetentionLimitsData::fromConfig(),
        ]);
    }

    public function update(UpdateSecurityPolicyRequest $request, UpdateSecurityPolicyAction $action): RedirectResponse
    {
        try {
            $action->execute(
                $request->user()->workspace(),
                $request->toData(),
                SecurityActorData::forUser($request->user(), $request->ip(), $request->userAgent()),
            );
        } catch (UnsafeWebhookUrlException $e) {
            // Deep SSRF guard (DNS → internal address) — surface as a field error.
            throw ValidationException::withMessages(['webhook_url' => $e->getMessage()]);
        }

        return back();
    }
}
