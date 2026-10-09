/**
 * The workspace security policy form: network access (IP allowlist + country
 * rule), data retention and the SIEM webhook, all saved together. Retention
 * days are edited as strings, where '' means "use the plan default".
 */
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

export interface SecurityPolicy {
    ip_allowlist_mode: string;
    ip_allowlist: string[];
    geo_mode: string;
    geo_countries: string[];
    retention_conversations_days: number | null;
    retention_audit_days: number | null;
    webhook_url: string | null;
    webhook_enabled: boolean;
}

export interface RetentionLimits {
    conversations_max: number;
    conversations_floor: number;
    audit_default: number;
    audit_floor: number;
}

export function useSecurityPolicyForm(policy: SecurityPolicy) {
    const { t } = useI18n();

    const form = useForm({
        ip_allowlist_mode: policy.ip_allowlist_mode === 'off' ? 'off' : 'dashboard',
        ip_allowlist: [...(policy.ip_allowlist ?? [])],
        geo_mode: policy.geo_mode ?? 'off',
        geo_countries: [...(policy.geo_countries ?? [])],
        // Kept as strings for the number inputs; '' means "inherit the default".
        retention_conversations_days: policy.retention_conversations_days?.toString() ?? '',
        retention_audit_days: policy.retention_audit_days?.toString() ?? '',
        webhook_url: policy.webhook_url ?? '',
        webhook_secret: '', // write-only; blank leaves the stored secret untouched
        webhook_enabled: policy.webhook_enabled ?? false,
    });

    form.transform((d) => ({
        ip_allowlist_mode: d.ip_allowlist_mode,
        ip_allowlist: d.ip_allowlist.map((v) => v.trim()).filter(Boolean),
        geo_mode: d.geo_mode,
        geo_countries: d.geo_countries.map((v) => v.trim().toLowerCase()).filter(Boolean),
        retention_conversations_days: d.retention_conversations_days === '' ? null : Number(d.retention_conversations_days),
        retention_audit_days: d.retention_audit_days === '' ? null : Number(d.retention_audit_days),
        webhook_url: d.webhook_url,
        webhook_secret: d.webhook_secret,
        webhook_enabled: d.webhook_enabled,
    }));

    const save = () => {
        form.put(route('security.update'), {
            preserveScroll: true,
            onSuccess: () => toast.success(t('workspace.security.saved')),
            onError: () => toast.error(t('workspace.security.save_error')),
        });
    };

    return { form, save };
}
