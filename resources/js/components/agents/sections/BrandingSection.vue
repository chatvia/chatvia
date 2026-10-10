<script setup lang="ts">
/**
 * The Branding section's fields: theme color, avatar, logo, privacy-policy
 * link and the "Powered by" badge switch. Purely
 * presentational; the Setup page owns the forms and saves.
 */
import ColorThemePicker from '@/components/agents/ColorThemePicker.vue';
import ImageUploadField from '@/components/agents/ImageUploadField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { useBrand } from '@/composables/useBrand';
import { Link2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { brand } = useBrand();

withDefaults(
    defineProps<{
        colorTheme: string;
        colorShade: number;
        avatarPreview?: string | null;
        logoPreview?: string | null;
        showBranding?: boolean;
        // Optional link to the workspace's own privacy policy. Surfaces a "By
        // chatting, you agree to our privacy policy" consent notice in the chat
        // when set. Saved on its own (own Save button), not with the colors.
        privacyPolicyUrl?: string;
        privacyError?: string;
        savingPrivacy?: boolean;
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
    }>(),
    {
        avatarPreview: null,
        logoPreview: null,
        showBranding: false,
        privacyPolicyUrl: '',
        privacyError: undefined,
        savingPrivacy: false,
        errors: () => ({}),
        disabled: false,
    },
);

defineEmits<{
    'update:colorTheme': [value: string];
    'update:colorShade': [value: number];
    'update:showBranding': [value: boolean];
    'update:privacyPolicyUrl': [value: string];
    privacyUrlApply: [];
    avatarSelect: [file: File];
    avatarRemove: [];
    logoSelect: [file: File];
    logoRemove: [];
    colorApply: [];
}>();
</script>

<template>
    <div class="space-y-6">
        <div class="space-y-2">
            <Label>{{ t('agents.setup.sections.branding_color') }}</Label>
            <p class="text-muted-foreground mb-3 text-xs">{{ t('agents.setup.sections.branding_color_hint') }}</p>
            <ColorThemePicker
                :theme="colorTheme"
                :shade="colorShade"
                :disabled="disabled"
                @update:theme="$emit('update:colorTheme', $event)"
                @update:shade="$emit('update:colorShade', $event)"
                @apply="$emit('colorApply')"
            />
            <p v-if="errors?.color_theme" class="text-destructive text-sm">{{ errors.color_theme }}</p>
        </div>

        <Separator />

        <ImageUploadField
            :label="t('agents.setup.sections.branding_avatar')"
            :description="t('agents.setup.sections.branding_avatar_hint')"
            :upload-label="t('agents.setup.sections.branding_avatar_upload')"
            icon="user"
            :preview="avatarPreview"
            :error="errors?.avatar"
            :disabled="disabled"
            @select="$emit('avatarSelect', $event)"
            @remove="$emit('avatarRemove')"
        />

        <ImageUploadField
            :label="t('agents.setup.sections.branding_logo')"
            :description="t('agents.setup.sections.branding_logo_hint')"
            :upload-label="t('agents.setup.sections.branding_logo_upload')"
            icon="image"
            :preview="logoPreview"
            :error="errors?.logo"
            :disabled="disabled"
            @select="$emit('logoSelect', $event)"
            @remove="$emit('logoRemove')"
        />

        <Separator />

        <!-- Privacy policy link — shown to visitors as a "By chatting, you agree
             to our privacy policy" consent notice. Available to every workspace;
             empty = no notice. Its own Save button, separate from the colors. -->
        <div class="space-y-2">
            <Label for="privacy_policy_url">{{ t('agents.setup.sections.appearance_privacy_url') }}</Label>
            <p class="text-muted-foreground text-xs">{{ t('agents.setup.sections.appearance_privacy_url_hint') }}</p>
            <div class="flex items-start gap-2">
                <div class="relative flex-1">
                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                        <Link2 class="h-4 w-4" />
                    </div>
                    <Input
                        id="privacy_policy_url"
                        :model-value="privacyPolicyUrl"
                        type="url"
                        inputmode="url"
                        :placeholder="t('agents.setup.sections.appearance_privacy_url_placeholder')"
                        :disabled="disabled"
                        class="ps-9"
                        autocomplete="off"
                        data-1p-ignore
                        data-lpignore="true"
                        data-form-type="other"
                        @update:model-value="$emit('update:privacyPolicyUrl', String($event))"
                        @keyup.enter="$emit('privacyUrlApply')"
                    />
                </div>
                <Button type="button" size="sm" :disabled="disabled || savingPrivacy" @click="$emit('privacyUrlApply')">
                    {{ savingPrivacy ? t('agents.setup.saving') : t('common.save') }}
                </Button>
            </div>
            <p v-if="privacyError" class="text-destructive text-sm">{{ privacyError }}</p>
        </div>

        <!-- Show/hide the "Powered by" badge. Not shown at all when the
             install turns the badge off (brand.powered_by). -->
        <template v-if="brand.powered_by.enabled">
            <Separator />

            <div class="flex items-center justify-between gap-3">
                <div>
                    <Label>{{ t('agents.setup.sections.branding_badge') }}</Label>
                    <p class="text-muted-foreground mt-1 text-xs">{{ t('agents.setup.sections.branding_badge_hint') }}</p>
                </div>
                <Switch :model-value="showBranding" :disabled="disabled" @update:model-value="$emit('update:showBranding', $event)" />
            </div>
        </template>
    </div>
</template>
