<?php

namespace App\Http\Requests\Settings\Security;

use App\Rules\IpOrCidr;
use Domain\Conversation\Services\ConversationRetention;
use Domain\Security\Data\SecurityPolicyFormData;
use Domain\Security\Enums\GeoMode;
use Domain\Security\Enums\IpAllowlistMode;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Domain\Security\Services\NetworkAccessGuard;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Support\Localization\Country\Models\Country;

/**
 * Write path for the workspace security policy: the network-access gate (IP
 * allowlist + geo), the retention windows and the SIEM webhook. The
 * `account.security` team permission is enforced on the route ({@see routes/workspace.php}); here we validate the
 * input and guard against self-lockout. Which stored settings the form may
 * change is decided by UpdateSecurityPolicyAction.
 */
class UpdateSecurityPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Treat blank inputs as "not set" (null) so the nullable rules don't choke
     * on an empty string from the form.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'retention_conversations_days' => $this->blankToNull($this->input('retention_conversations_days')),
            'retention_leads_days' => $this->blankToNull($this->input('retention_leads_days')),
            'retention_audit_days' => $this->blankToNull($this->input('retention_audit_days')),
            'retention_analytics_days' => $this->blankToNull($this->input('retention_analytics_days')),
            'anonymize_after_days' => $this->blankToNull($this->input('anonymize_after_days')),
            'webhook_url' => $this->blankToNull($this->input('webhook_url')),
            'webhook_secret' => $this->blankToNull($this->input('webhook_secret')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Conversation retention may be shortened but never beyond the install's;
        // audit retention sits between the mandated floor and the config default.
        $conversationsCeiling = ConversationRetention::days();
        $conversationsFloor = (int) config('chatvia.retention.conversations_floor_days', 1);
        $auditFloor = (int) config('chatvia.security_audit.retention_floor_days', 30);
        $auditDefault = (int) config('chatvia.security_audit.retention_days', 365);
        $maxDays = (int) config('chatvia.retention.max_days', 3650);
        $leadsFloor = (int) config('chatvia.retention.leads_floor_days', 1);
        $analyticsFloor = (int) config('chatvia.retention.analytics_floor_days', 30);
        $anonymizeFloor = (int) config('chatvia.retention.anonymize_floor_days', 1);
        $anonymizeCeiling = $this->anonymizeCeiling($conversationsCeiling, $anonymizeFloor);

        return [
            'ip_allowlist_mode' => ['required', 'string', Rule::in(IpAllowlistMode::selectable())],
            'ip_allowlist' => ['array'],
            'ip_allowlist.*' => ['string', new IpOrCidr],
            'geo_mode' => ['required', 'string', Rule::enum(GeoMode::class)],
            'geo_countries' => ['array'],
            'geo_countries.*' => ['string', 'size:2'],
            'retention_conversations_days' => ['nullable', 'integer', "min:{$conversationsFloor}", "max:{$conversationsCeiling}"],
            'retention_audit_days' => ['nullable', 'integer', "min:{$auditFloor}", "max:{$auditDefault}"],
            'retention_leads_days' => ['nullable', 'integer', "min:{$leadsFloor}", "max:{$maxDays}"],
            'retention_analytics_days' => ['nullable', 'integer', "min:{$analyticsFloor}", "max:{$maxDays}"],
            'anonymize_after_days' => ['nullable', 'integer', "min:{$anonymizeFloor}", "max:{$anonymizeCeiling}"],
            // SIEM webhook. The deep SSRF check (DNS → internal IP) lives in the
            // Action; the controller maps its exception to a 422.
            'webhook_url' => ['nullable', 'string', 'url:https', 'max:2048'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'webhook_enabled' => ['boolean'],
        ];
    }

    /**
     * Anti-lockout: reject rules that would block the saver's own request, so a
     * workspace can never fence itself out of its dashboard through the UI. The IP
     * allowlist and the geo rule are checked separately so the message names the
     * actual cause — a country-only rule never talks about IPs (and vice versa).
     * Geo fails open when the country can't be resolved (so local/dev saves work).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $form = $this->toData();

                // The gate enforces the country rule even while the IP allowlist is
                // off, so a country rule alone must pass the lockout check too.
                if ($form->ip_allowlist_mode === IpAllowlistMode::Off && $form->geo_mode === GeoMode::Off) {
                    return;
                }

                $guard = app(NetworkAccessGuard::class);
                $ip = (string) $this->ip();
                $country = $form->geo_mode === GeoMode::Off ? null : $this->country();
                $enforcedAllowlist = $form->ip_allowlist_mode === IpAllowlistMode::Off ? [] : $form->ip_allowlist;

                $ipBlocks = ! $guard->ipAllowed($enforcedAllowlist, $ip);
                $geoBlocks = ! $guard->geoAllowed($form->geo_mode, $form->geo_countries, $country);

                if (! $ipBlocks && ! $geoBlocks) {
                    return;
                }

                // One shared "lockout" channel: the Security page renders
                // errors.ip_allowlist as a prominent alert regardless of which rule is
                // at fault — so the wording, not the key, has to reflect the cause.
                $validator->errors()->add('ip_allowlist', $this->lockoutMessage($ipBlocks, $geoBlocks, $form->geo_mode, $ip, $country));
            },

            // Enabling SIEM delivery needs a destination and a signing secret.
            function (Validator $validator): void {
                if (! $this->boolean('webhook_enabled')) {
                    return;
                }

                if (blank($this->input('webhook_url'))) {
                    $validator->errors()->add('webhook_url', __('A webhook URL is required to enable delivery.'));
                }

                if (blank($this->input('webhook_secret')) && ! $this->hasStoredWebhookSecret()) {
                    $validator->errors()->add('webhook_secret', __('A signing secret is required to enable delivery.'));
                }
            },
        ];
    }

    public function toData(): SecurityPolicyFormData
    {
        return new SecurityPolicyFormData(
            retention_conversations_days: $this->nullableInt('retention_conversations_days'),
            retention_leads_days: $this->nullableInt('retention_leads_days'),
            retention_audit_days: $this->nullableInt('retention_audit_days'),
            retention_analytics_days: $this->nullableInt('retention_analytics_days'),
            anonymize_after_days: $this->nullableInt('anonymize_after_days'),
            ip_allowlist_mode: IpAllowlistMode::from((string) $this->input('ip_allowlist_mode')),
            ip_allowlist: $this->cleanList('ip_allowlist'),
            geo_mode: GeoMode::from((string) $this->input('geo_mode')),
            // Lowercase to match the countries table + the geo-IP provider's codes.
            geo_countries: array_values(array_unique(array_map(Str::lower(...), $this->cleanList('geo_countries')))),
            webhook_url: $this->blankToNull($this->input('webhook_url')),
            // Write-only: a blank value leaves the stored secret untouched.
            webhook_secret: $this->blankToNull($this->input('webhook_secret')),
            webhook_enabled: $this->boolean('webhook_enabled'),
        );
    }

    /**
     * Phrase the self-lockout error for whichever rule would actually block the
     * saver. Geo-only never mentions the IP; IP-only never mentions the country.
     */
    private function lockoutMessage(bool $ipBlocks, bool $geoBlocks, GeoMode $geoMode, string $ip, ?string $country): string
    {
        if ($ipBlocks && $geoBlocks) {
            return __('These rules would lock you out — your address (:ip) is not in the allowed IPs and your location (:country) is blocked by the country rule. Fix both first.', [
                'ip' => $ip,
                'country' => $this->countryLabel($country),
            ]);
        }

        if ($ipBlocks) {
            return __('This IP allowlist would lock you out — your current address (:ip) is not on it. Add it first.', ['ip' => $ip]);
        }

        return $geoMode === GeoMode::Allow
            ? __('This country rule would lock you out — your current location (:country) is not in the allowed countries. Add it, or switch the rule, first.', ['country' => $this->countryLabel($country)])
            : __('This country rule would lock you out — your current location (:country) is in the blocked countries. Remove it, or switch the rule, first.', ['country' => $this->countryLabel($country)]);
    }

    /**
     * Friendly, localized country name for an ISO code, falling back to the
     * upper-cased code. Only hit on the rejection path, so the lookup is cheap.
     */
    private function countryLabel(?string $code): string
    {
        if (blank($code)) {
            return '';
        }

        // countries.code is stored lowercase; the geo-IP provider may return either case.
        $country = Country::query()->where('code', Str::lower($code))->first(['code', 'name']);

        return $country?->localizedName() ?? Str::upper($code);
    }

    /**
     * Visitor details may not be kept longer than the chats themselves: the
     * conversation retention sent with this form, else the install's.
     */
    private function anonymizeCeiling(int $conversationsCeiling, int $anonymizeFloor): int
    {
        $conversationsDays = $this->input('retention_conversations_days');
        $effective = is_numeric($conversationsDays) ? min((int) $conversationsDays, $conversationsCeiling) : $conversationsCeiling;

        return max($anonymizeFloor, $effective);
    }

    private function hasStoredWebhookSecret(): bool
    {
        return WorkspaceSecurityPolicy::query()
            ->where('workspace_id', $this->user()->workspace()->id)
            ->whereNotNull('webhook_secret')
            ->exists();
    }

    /**
     * Trimmed, non-empty, unique entries of a list input.
     *
     * @return list<string>
     */
    private function cleanList(string $key): array
    {
        $entries = array_map(fn (mixed $value): string => Str::trim((string) $value), (array) $this->input($key, []));

        return array_values(array_unique(array_filter($entries, filled(...))));
    }

    private function blankToNull(mixed $value): ?string
    {
        return blank($value) ? null : (string) $value;
    }

    private function nullableInt(string $key): ?int
    {
        return $this->filled($key) ? $this->integer($key) : null;
    }
}
