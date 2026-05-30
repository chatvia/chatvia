<script setup lang="ts">
import ColorThemePicker from '@/components/agents/ColorThemePicker.vue';
import ImageUploadField from '@/components/agents/ImageUploadField.vue';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';

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
            <Label>Color Theme</Label>
            <p class="text-muted-foreground mb-3 text-xs">Click the color swatch to choose your primary color</p>
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
            label="Agent Avatar"
            description="Upload an avatar for your agent (256x256px recommended)"
            upload-label="Upload Avatar"
            icon="user"
            :preview="avatarPreview"
            :error="errors?.avatar"
            :disabled="disabled"
            @select="$emit('avatarSelect', $event)"
            @remove="$emit('avatarRemove')"
        />

        <ImageUploadField
            label="Logo Image"
            description="Upload a logo for your agent (256x256px recommended)"
            upload-label="Upload Logo"
            icon="image"
            :preview="logoPreview"
            :error="errors?.logo"
            :disabled="disabled"
            @select="$emit('logoSelect', $event)"
            @remove="$emit('logoRemove')"
        />
    </div>
</template>
