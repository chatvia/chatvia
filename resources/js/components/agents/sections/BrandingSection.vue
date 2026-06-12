<script setup lang="ts">
import ColorThemePicker from '@/components/agents/ColorThemePicker.vue';
import ImageUploadField from '@/components/agents/ImageUploadField.vue';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

withDefaults(
    defineProps<{
        colorTheme: string;
        colorShade: number;
        avatarPreview?: string | null;
        logoPreview?: string | null;
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
    }>(),
    { avatarPreview: null, logoPreview: null, errors: () => ({}), disabled: false },
);

defineEmits<{
    'update:colorTheme': [value: string];
    'update:colorShade': [value: number];
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
    </div>
</template>
