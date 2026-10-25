/**
 * The Domain section (Hosted Chat only): a custom hostname pointed at the
 * agent with a CNAME record. A saved domain stays pending until a TXT record
 * proves the workspace controls it; then "Verify" turns it on. Saving an
 * empty value removes the domain.
 */
import { update as updateDomain, verify as verifyDomain } from '@/routes/agents/domain';
import { useForm } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { submitSetupForm, type SetupSectionDeps } from './submitSetupForm';

/** The domain context the Setup controller sends. */
export interface SetupDomainContext {
    /** The domain in use. */
    value: string | null;
    /** The domain waiting for its TXT record. */
    pending: string | null;
    cname_target: string;
    /** The TXT record that proves the pending domain (null while none is pending). */
    txt_name: string | null;
    txt_value: string | null;
}

export function useSetupDomain({ agentId }: SetupSectionDeps, domain: () => SetupDomainContext) {
    const { t } = useI18n();

    const form = useForm({
        domain: domain().pending ?? domain().value ?? '',
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

    const verifyForm = useForm({});
    const isVerifying = ref(false);

    const verify = (): void =>
        submitSetupForm(verifyForm, 'post', verifyDomain.url(agentId()), {
            saving: isVerifying,
            success: () => t('agents.setup.toasts.domain_verified'),
            error: () => {
                const errors = verifyForm.errors as Record<string, string | undefined>;
                return typeof errors.domain === 'string' ? errors.domain : t('agents.setup.toasts.domain_verify_error');
            },
        });

    const { copy, copied: isCnameCopied } = useClipboard({ legacy: true, copiedDuring: 2000 });
    const copyCnameTarget = async (): Promise<void> => {
        try {
            await copy(domain().cname_target);
        } catch {
            toast.error(t('agents.setup.toasts.copy_error'));
        }
    };

    const { copy: copyTxt, copied: isTxtCopied } = useClipboard({ legacy: true, copiedDuring: 2000 });
    const copyTxtValue = async (): Promise<void> => {
        try {
            await copyTxt(domain().txt_value ?? '');
        } catch {
            toast.error(t('agents.setup.toasts.copy_error'));
        }
    };

    return { form, isSaving, save, clear, verifyForm, isVerifying, verify, isCnameCopied, copyCnameTarget, isTxtCopied, copyTxtValue };
}

export type SetupDomain = ReturnType<typeof useSetupDomain>;
