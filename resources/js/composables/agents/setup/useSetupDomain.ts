/**
 * The Domain section (Hosted Chat only): a custom hostname pointed at the
 * agent with a CNAME record. Saving an empty value removes the domain. The
 * number of domains a workspace may use comes from its plan / add-on quota.
 */
import { useForm } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { submitSetupForm, type SetupSectionDeps } from './submitSetupForm';

/** The domain context the Setup controller sends. */
export interface SetupDomainContext {
    value: string | null;
    cname_target: string;
    quota: { total: number; used: number; available: number };
    has_quota: boolean;
}

export function useSetupDomain({ agentId }: SetupSectionDeps, domain: () => SetupDomainContext) {
    const { t } = useI18n();

    const form = useForm({
        domain: domain().value ?? '',
    });
    const isSaving = ref(false);

    const save = (): void =>
        submitSetupForm(form, 'patch', route('agents.domain.update', { agent: agentId() }), {
            saving: isSaving,
            success: () => (form.domain ? t('agents.setup.toasts.domain_saved') : t('agents.setup.toasts.domain_removed')),
            error: () => (typeof form.errors.domain === 'string' ? form.errors.domain : t('agents.setup.toasts.domain_error')),
        });

    const clear = (): void => {
        form.domain = '';
        save();
    };

    // The quota's `used` leaves this agent out, so count its own domain back in.
    const usedCount = computed(() => domain().quota.used + (domain().value ? 1 : 0));

    const { copy, copied: isCnameCopied } = useClipboard({ legacy: true, copiedDuring: 2000 });
    const copyCnameTarget = async (): Promise<void> => {
        try {
            await copy(domain().cname_target);
        } catch {
            toast.error(t('agents.setup.toasts.copy_error'));
        }
    };

    return { form, isSaving, save, clear, usedCount, isCnameCopied, copyCnameTarget };
}

export type SetupDomain = ReturnType<typeof useSetupDomain>;
