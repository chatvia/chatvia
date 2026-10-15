<script setup lang="ts">
/** Setup → Branding: theme color, avatar, logo and privacy-policy link. */
import BrandingSection from '@/components/agents/sections/BrandingSection.vue';
import { useSetupContext } from '@/composables/agents/setup/useSetupContext';

const { locked, branding } = useSetupContext();
const {
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
} = branding;
</script>

<template>
    <div class="space-y-6 p-6">
        <BrandingSection
            v-model:color-theme="brandingForm.color_theme"
            v-model:color-shade="brandingForm.color_shade"
            v-model:privacy-policy-url="privacyForm.privacy_policy_url"
            :avatar-preview="avatarPreview"
            :logo-preview="logoPreview"
            :errors="brandingForm.errors as Record<string, string>"
            :privacy-error="privacyForm.errors.privacy_policy_url"
            :saving-privacy="isSavingPrivacy"
            :disabled="locked || isSavingBranding"
            @color-apply="saveColors"
            @privacy-url-apply="savePrivacyUrl"
            @avatar-select="(file: File) => uploadImage('avatar', file)"
            @avatar-remove="() => removeImage('avatar')"
            @logo-select="(file: File) => uploadImage('logo', file)"
            @logo-remove="() => removeImage('logo')"
        />
    </div>
</template>
