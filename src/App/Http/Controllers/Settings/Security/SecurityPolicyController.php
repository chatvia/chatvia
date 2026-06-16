<?php

namespace App\Http\Controllers\Settings\Security;

use App\Http\Requests\Settings\Security\UpdateSecurityPolicyRequest;
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
use Laravel\Pennant\Feature;
use Support\Localization\Country\Models\Country;

/**
 * Workspace Security & Compliance settings — Phase 1 surfaces the network access
 * control (IP allowlist + geo). Viewing requires the `account.security` team
 * permission; non-Enterprise workspaces see an upsell teaser (the `entitled`
 * flag). Saving additionally requires the `security` entitlement, enforced by
 * the `features:security` middleware on the update route.
 */
class SecurityPolicyController
{
    public function edit(Request $request): Response
    {
        $workspace = $request->user()->workspace();
        $entitled = Feature::for($request->user())->active('security');

        // Read without firstOrCreate so merely viewing (incl. the teaser) never
        // writes a policy row; fall back to defaults when none exists yet.
        $policy = WorkspaceSecurityPolicy::query()
            ->where('workspace_id', $workspace->id)
            ->first();

        // The viewer's own country (lowercase ISO code) so the page can warn —
        // before they ever hit Save — that a geo rule would lock them out. Prefers
        // Cloudflare's CF-IPCountry (the real visitor, not CF's edge IP); null when
        // unresolved ⇒ no warning, mirroring the gate's geo fail-open.
        $currentCountry = $entitled ? request_country($request) : null;

        return Inertia::render('workspace/security/Security', [
            'entitled' => $entitled,
            // SIEM is its own capability (Pro+); the card shows only with it.
            'siem' => (bool) $workspace->capabilities->siemWebhook,
            'policy' => $policy ? WorkspaceSecurityPolicyData::fromModel($policy) : new WorkspaceSecurityPolicyData,
            // Country picker options for the geo rules — only needed when the form
            // (not the teaser) renders. Codes are the lowercase ISO-3166 alpha-2
            // values the geo gate stores and matches against.
            'countries' => $entitled ? $this->countryOptions() : [],
            'current_country' => $currentCountry,
            // Retention bounds for the form: conversations cap at the plan ceiling
            // (shorten only); audit sits between the mandated floor and default.
            'limits' => $entitled ? [
                'conversations_max' => (int) $workspace->capabilities->retentionDays,
                'conversations_floor' => (int) config('chatvia.retention.conversations_floor_days', 1),
                'audit_default' => (int) config('chatvia.security_audit.retention_days', 365),
                'audit_floor' => (int) config('chatvia.security_audit.retention_floor_days', 30),
            ] : null,
        ]);
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    private function countryOptions(): array
    {
        return Country::query()
            ->select('code', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn (Country $country): array => ['code' => $country->code, 'name' => __($country->name)])
            ->all();
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
