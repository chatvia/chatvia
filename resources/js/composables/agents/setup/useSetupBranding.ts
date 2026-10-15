/**
 * The Branding section: theme color, avatar and logo images, and the
 * privacy-policy link (all in the `appearance` group). The privacy link has
 * its own form and Save button so it doesn't ride the color writes. Images upload on pick with
 * an optimistic preview that is rolled back if the upload fails.
 */
import { deleteAppearanceImage, uploadAppearanceImage, type AppearanceImage } from '@/api/agentSetup';
import { update as updateSettings } from '@/routes/agents/settings';
import { router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { asString, type SettingsGroup, type SettingsReader } from './settingValues';
import { submitSetupForm, type SetupSectionDeps } from './submitSetupForm';

export function useSetupBranding({ agentId, reloadPreview }: SetupSectionDeps, appearance: SettingsReader, latestAppearance: () => SettingsGroup) {
    const { t } = useI18n();
    const settingsUrl = () => updateSettings.url({ agent: agentId(), group: 'appearance' });

    const brandingForm = useForm({
        color_theme: appearance.string('color_theme', 'black'),
        color_shade: appearance.number('color_shade', 700),
    });
    const isSavingBranding = ref(false);

    const saveColors = (): void =>
        submitSetupForm(brandingForm, 'post', settingsUrl(), {
            saving: isSavingBranding,
            success: t('agents.setup.toasts.colors_saved'),
            error: t('agents.setup.toasts.colors_error'),
            onSuccess: reloadPreview,
        });

    // When set, the chat surfaces show a "By chatting, you agree to our privacy
    // policy" notice linking here.
    const privacyForm = useForm({
        privacy_policy_url: appearance.string('privacy_policy_url'),
    });
    const isSavingPrivacy = ref(false);
    const savePrivacyUrl = (): void =>
        submitSetupForm(privacyForm, 'post', settingsUrl(), {
            saving: isSavingPrivacy,
            success: t('agents.setup.toasts.privacy_saved'),
            error: t('agents.setup.toasts.privacy_error'),
            onSuccess: reloadPreview,
        });

    const avatarPreview = ref<string | null>(asString(appearance.saved.avatar) || null);
    const logoPreview = ref<string | null>(asString(appearance.saved.logo) || null);
    const previewOf = (key: AppearanceImage) => (key === 'avatar' ? avatarPreview : logoPreview);

    // After an upload the page reloads `appearanceSettings`; mirror the stored
    // URLs back into the previews. Every upload gets a unique hashed path, so
    // the URL always changes and needs no cache-busting.
    watch(latestAppearance, (next) => {
        avatarPreview.value = asString(next.avatar) || null;
        logoPreview.value = asString(next.logo) || null;
    });

    // A JSON call rather than a form visit: the settings endpoint answers with no
    // Inertia response, so a form's onSuccess would never run.
    const uploadImage = async (key: AppearanceImage, file: File): Promise<void> => {
        // Show the picked image right away, then settle on the stored URL once saved.
        const preview = previewOf(key);
        const previousPreview = preview.value;
        const objectUrl = URL.createObjectURL(file);
        preview.value = objectUrl;
        reloadPreview();

        try {
            await uploadAppearanceImage(agentId(), key, file);
            toast.success(key === 'avatar' ? t('agents.setup.toasts.avatar_updated') : t('agents.setup.toasts.logo_updated'));
            router.reload({ only: ['appearanceSettings'] });
            reloadPreview();
        } catch {
            preview.value = previousPreview;
            URL.revokeObjectURL(objectUrl);
            reloadPreview();
            toast.error(key === 'avatar' ? t('agents.setup.toasts.avatar_upload_error') : t('agents.setup.toasts.logo_upload_error'));
        }
    };

    const removeImage = async (key: AppearanceImage): Promise<void> => {
        try {
            await deleteAppearanceImage(agentId(), key);
            previewOf(key).value = null;
            toast.success(key === 'avatar' ? t('agents.setup.toasts.avatar_removed') : t('agents.setup.toasts.logo_removed'));
            reloadPreview();
        } catch {
            toast.error(key === 'avatar' ? t('agents.setup.toasts.avatar_remove_error') : t('agents.setup.toasts.logo_remove_error'));
        }
    };

    return {
        brandingForm,
        isSavingBranding,
        saveColors,
        privacyForm,
        isSavingPrivacy,
        savePrivacyUrl,
        avatarPreview,
        logoPreview,
        uploadImage,
        removeImage,
    };
}

export type SetupBranding = ReturnType<typeof useSetupBranding>;
