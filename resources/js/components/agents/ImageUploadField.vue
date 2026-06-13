<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Image as ImageIcon, Trash2, Upload, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        label: string;
        description?: string;
        uploadLabel?: string;
        preview?: string | null;
        icon?: 'user' | 'image';
        accept?: string;
        disabled?: boolean;
        error?: string;
    }>(),
    { description: '', preview: null, icon: 'image', accept: 'image/*', disabled: false },
);

const displayUploadLabel = computed(() => props.uploadLabel ?? t('shared.image_upload.upload'));

const emit = defineEmits<{
    select: [file: File];
    remove: [];
}>();

const fileInput = ref<HTMLInputElement | null>(null);

function trigger(): void {
    fileInput.value?.click();
}

function onChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) emit('select', file);
}
</script>

<template>
    <div class="space-y-2">
        <Label>{{ label }}</Label>
        <p v-if="description" class="text-muted-foreground mb-3 text-xs">{{ description }}</p>

        <div class="flex items-center gap-4">
            <div class="bg-muted h-20 w-20 overflow-hidden rounded-full border">
                <img v-if="preview" :src="preview" :alt="label" class="h-full w-full object-cover" />
                <div v-else class="text-muted-foreground flex h-full w-full items-center justify-center">
                    <User v-if="icon === 'user'" class="h-10 w-10" />
                    <ImageIcon v-else class="h-10 w-10" />
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <input ref="fileInput" :accept="accept" class="hidden" type="file" @change="onChange" />
                <Button type="button" variant="outline" size="sm" :disabled="disabled" @click="trigger">
                    <Upload class="me-2 h-4 w-4" />
                    {{ displayUploadLabel }}
                </Button>
                <Button
                    v-if="preview && !disabled"
                    class="text-destructive hover:text-destructive/80"
                    size="sm"
                    type="button"
                    variant="ghost"
                    @click="emit('remove')"
                >
                    <Trash2 class="me-2 h-4 w-4" />
                    {{ t('shared.image_upload.remove') }}
                </Button>
            </div>
        </div>

        <p v-if="error" class="text-destructive text-sm">{{ error }}</p>
    </div>
</template>
