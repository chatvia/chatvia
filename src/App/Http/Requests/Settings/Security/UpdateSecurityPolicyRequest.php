<?php

namespace App\Http\Requests\Settings\Security;

use Domain\Security\Data\WorkspaceSecurityPolicyData;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Domain\Security\Services\NetworkAccessGuard;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Support\Localization\Country\Models\Country;

/**
 * Write path for the workspace security policy: the network-access gate (IP
 * allowlist + geo) plus conversation/audit retention. {@see self::toData()}
 * preserves the settings this form doesn't own (SIEM webhook, SCIM, and the
 * leads/analytics/anonymize retention fields) from the stored row. The `security`
 * entitlement is enforced on the route ({@see routes/workspace.php}); here we
 * authorize the team permission and guard against self-lockout.
 */
class UpdateSecurityPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return has_team_permission('account.security');
    }

    /**
     * Treat blank retention inputs as "inherit the default" (null) so the
     * nullable+integer rules don't choke on an empty string from the form.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'retention_conversations_days' => $this->blankToNull($this->input('retention_conversations_days')),
            'retention_audit_days' => $this->blankToNull($this->input('retention_audit_days')),
            'webhook_url' => $this->blankToNull($this->input('webhook_url')),
            'webhook_secret' => $this->blankToNull($this->input('webhook_secret')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Conversation retention may be shortened but never beyond the plan ceiling;
        // audit retention sits between the mandated floor and the config default.
        $conversationsCeiling = (int) $this->user()->workspace()->capabilities->retentionDays;
        $conversationsFloor = (int) config('chatvia.retention.conversations_floor_days', 1);
        $auditFloor = (int) config('chatvia.security_audit.retention_floor_days', 30);
        $auditDefault = (int) config('chatvia.security_audit.retention_days', 365);

        return [
            // dashboard_api is reserved for a later phase — not selectable yet.
            'ip_allowlist_mode' => ['required', 'string', 'in:off,dashboard'],
            'ip_allowlist' => ['array'],
            'ip_allowlist.*' => ['string', function (string $attribute, mixed $value, callable $fail): void {
                if (! is_string($value) || ! $this->isValidIpOrCidr($value)) {
                    $fail(__('Each entry must be a valid IP address or CIDR range.'));
                }
            }],
            'geo_mode' => ['required', 'string', 'in:off,allow,deny'],
            'geo_countries' => ['array'],
            'geo_countries.*' => ['string', 'size:2'],
            'retention_conversations_days' => ['nullable', 'integer', "min:{$conversationsFloor}", "max:{$conversationsCeiling}"],
            'retention_audit_days' => ['nullable', 'integer', "min:{$auditFloor}", "max:{$auditDefault}"],
            // SIEM webhook (Enterprise). The deep SSRF check (DNS → internal IP)
            // lives in the Action; the controller maps its exception to a 422.
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
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->string('ip_allowlist_mode')->value() === 'off') {
                return;
            }

            $guard = app(NetworkAccessGuard::class);
            $ip = (string) $this->ip();
            $geoMode = (string) $this->input('geo_mode', 'off');
            $country = $geoMode === 'off' ? null : request_country($this);

            $ipBlocks = ! $guard->ipAllowed($this->cleanIpAllowlist(), $ip);
            $geoBlocks = ! $guard->geoAllowed($geoMode, $this->cleanGeoCountries(), $country);

            if (! $ipBlocks && ! $geoBlocks) {
                return;
            }

            // One shared "lockout" channel: the Security page renders
            // errors.ip_allowlist as a prominent alert regardless of which rule is
            // at fault — so the wording, not the key, has to reflect the cause.
            $validator->errors()->add('ip_allowlist', $this->lockoutMessage($ipBlocks, $geoBlocks, $geoMode, $ip, $country));
        });

        // Enabling SIEM delivery needs a destination and a signing secret. Only
        // for workspaces with the siemWebhook capability (others can't set it).
        $validator->after(function (Validator $validator): void {
            if (! $this->user()->workspace()->capabilities->siemWebhook || ! $this->boolean('webhook_enabled')) {
                return;
            }

            if (blank($this->input('webhook_url'))) {
                $validator->errors()->add('webhook_url', __('A webhook URL is required to enable delivery.'));
            }

            if (blank($this->input('webhook_secret')) && ! $this->hasStoredWebhookSecret()) {
                $validator->errors()->add('webhook_secret', __('A signing secret is required to enable delivery.'));
            }
        });
    }

    public function toData(): WorkspaceSecurityPolicyData
    {
        $policy = WorkspaceSecurityPolicy::query()
            ->where('workspace_id', $this->user()->workspace()->id)
            ->first();

        // Base the DTO on the stored row, then override only what this form owns.
        $base = $policy ? WorkspaceSecurityPolicyData::fromModel($policy) : new WorkspaceSecurityPolicyData;

        // The SIEM webhook is gated by its own capability — preserve the stored
        // values for workspaces without it so a request can't enable or change it.
        $canSiem = (bool) $this->user()->workspace()->capabilities->siemWebhook;

        return new WorkspaceSecurityPolicyData(
            retention_conversations_days: $this->nullableIntInput('retention_conversations_days'),
            retention_leads_days: $base->retention_leads_days,
            retention_audit_days: $this->nullableIntInput('retention_audit_days'),
            retention_analytics_days: $base->retention_analytics_days,
            anonymize_after_days: $base->anonymize_after_days,
            ip_allowlist_mode: (string) $this->input('ip_allowlist_mode'),
            ip_allowlist: $this->cleanIpAllowlist(),
            geo_mode: (string) $this->input('geo_mode'),
            geo_countries: $this->cleanGeoCountries(),
            webhook_url: $canSiem ? $this->nullableStringInput('webhook_url') : $base->webhook_url,
            // Write-only: a blank value leaves the stored secret untouched.
            webhook_secret: $canSiem ? $this->nullableStringInput('webhook_secret') : null,
            webhook_enabled: $canSiem ? $this->boolean('webhook_enabled') : $base->webhook_enabled,
            webhook_events: $base->webhook_events,
            scim_enabled: $base->scim_enabled,
        );
    }

    /**
     * Phrase the self-lockout error for whichever rule would actually block the
     * saver. Geo-only never mentions the IP; IP-only never mentions the country.
     */
    private function lockoutMessage(bool $ipBlocks, bool $geoBlocks, string $geoMode, string $ip, ?string $country): string
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

        return $geoMode === 'allow'
            ? __('This country rule would lock you out — your current location (:country) is not in the allowed countries. Add it, or switch the rule, first.', ['country' => $this->countryLabel($country)])
            : __('This country rule would lock you out — your current location (:country) is in the blocked countries. Remove it, or switch the rule, first.', ['country' => $this->countryLabel($country)]);
    }

    /**
     * Friendly, localized country name for an ISO code, falling back to the
     * upper-cased code. Only hit on the rejection path, so the lookup is cheap.
     */
    private function countryLabel(?string $code): string
    {
        if ($code === null || $code === '') {
            return '';
        }

        // countries.code is stored lowercase; the geo-IP provider may return either case.
        $name = Country::query()->where('code', strtolower($code))->value('name');

        return $name !== null ? (string) __($name) : strtoupper($code);
    }

    private function hasStoredWebhookSecret(): bool
    {
        return WorkspaceSecurityPolicy::query()
            ->where('workspace_id', $this->user()->workspace()->id)
            ->whereNotNull('webhook_secret')
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    private function cleanIpAllowlist(): array
    {
        $entries = array_map(fn (mixed $v): string => trim((string) $v), (array) $this->input('ip_allowlist', []));

        return array_values(array_unique(array_filter($entries, fn (string $v): bool => $v !== '')));
    }

    /**
     * @return array<int, string>
     */
    private function cleanGeoCountries(): array
    {
        // Lowercase to match the countries table + the geo-IP provider's codes.
        $entries = array_map(fn (mixed $v): string => strtolower(trim((string) $v)), (array) $this->input('geo_countries', []));

        return array_values(array_unique(array_filter($entries, fn (string $v): bool => $v !== '')));
    }

    private function blankToNull(mixed $value): ?string
    {
        return ($value === null || $value === '') ? null : (string) $value;
    }

    private function nullableIntInput(string $key): ?int
    {
        $value = $this->input($key);

        return ($value === null || $value === '') ? null : (int) $value;
    }

    private function nullableStringInput(string $key): ?string
    {
        $value = $this->input($key);

        return ($value === null || $value === '') ? null : (string) $value;
    }

    private function isValidIpOrCidr(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if (! str_contains($value, '/')) {
            return false;
        }

        [$ip, $prefix] = explode('/', $value, 2);

        if (filter_var($ip, FILTER_VALIDATE_IP) === false || ! ctype_digit($prefix)) {
            return false;
        }

        $max = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 128 : 32;

        return (int) $prefix >= 0 && (int) $prefix <= $max;
    }
}
