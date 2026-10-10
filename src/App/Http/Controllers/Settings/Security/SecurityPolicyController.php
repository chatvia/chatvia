<?php

namespace App\Http\Controllers\Settings\Security;

use App\Http\Requests\Settings\Security\UpdateSecurityPolicyRequest;
use Domain\Conversation\Services\ConversationRetention;
use Domain\Security\Actions\UpdateSecurityPolicyAction;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Data\WorkspaceSecurityPolicyData;
use Domain\Security\Exceptions\UnsafeWebhookUrlException;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Support\Localization\Country\Models\Country;

/**
 * Workspace Security & Compliance settings: the network access control (IP
 * allowlist and country rules), retention and the SIEM webhook. Viewing and
 * saving require the `account.security` team permission.
 */
class SecurityPolicyController
{
    public function edit(Request $request): Response
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
            // Retention bounds for the form: conversations cap at the install's
            // retention (shorten only); audit sits between the mandated floor
            // and default.
            'limits' => [
                'conversations_max' => ConversationRetention::days(),
                'conversations_floor' => (int) config('chatvia.retention.conversations_floor_days', 1),
                'audit_default' => (int) config('chatvia.security_audit.retention_days', 365),
                'audit_floor' => (int) config('chatvia.security_audit.retention_floor_days', 30),
            ],
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
