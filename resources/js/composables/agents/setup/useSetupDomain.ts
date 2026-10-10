/**
 * The Domain section (Hosted Chat only): a custom hostname pointed at the
 * agent with a CNAME record. Saving an empty value removes the domain.
 */
import { update as updateDomain } from '@/routes/agents/domain';
import { useForm } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { submitSetupForm, type SetupSectionDeps } from './submitSetupForm';

/** The domain context the Setup controller sends. */
export interface SetupDomainContext {
    value: string | null;
    cname_target: string;
}

export function useSetupDomain({ agentId }: SetupSectionDeps, domain: () => SetupDomainContext) {
    const { t } = useI18n();

    const form = useForm({
        domain: domain().value ?? '',
    });
    const isSaving = ref(false);

    const save = (): void =>
        submitSetupForm(form, 'patch', updateDomain.url(agentId()), {
            saving: isSaving,
            success: () => (form.domain ? t('agents.setup.toasts.domain_saved') : t('agents.setup.toasts.domain_removed')),
            error: () => (typeof form.errors.domain === 'string' ? form.errors.domain : t('agents.setup.toasts.domain_error')),
        });

    const clear = (): void => {
        form.domain = '';
        save();
    };

    const { copy, copied: isCnameCopied } = useClipboard({ legacy: true, copiedDuring: 2000 });
    const copyCnameTarget = async (): Promise<void> => {
        try {
            await copy(domain().cname_target);
        } catch {
            toast.error(t('agents.setup.toasts.copy_error'));
        }
    };

    return { form, isSaving, save, clear, isCnameCopied, copyCnameTarget };
}

export type SetupDomain = ReturnType<typeof useSetupDomain>;
