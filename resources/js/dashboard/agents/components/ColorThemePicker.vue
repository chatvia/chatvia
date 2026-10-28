<script setup lang="ts">
/**
 * The Branding section's color swatch. Clicking it opens a dialog with the
 * full ColorPicker; "Apply" emits `apply` so the parent can save.
 */
import ColorPicker from '@/dashboard/agents/components/ColorPicker.vue';
import { Button } from '@/shared/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { nearestShade } from '@/shared/lib/colorShades';
import { Palette } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        theme: string;
        shade: number;
        disabled?: boolean;
    }>(),
    { disabled: false },
);

const emit = defineEmits<{
    'update:theme': [value: string];
    'update:shade': [value: number];
    apply: [];
}>();

const open = ref(false);

const activeShade = computed(() => nearestShade(props.shade));

function applyAndClose(): void {
    emit('apply');
    open.value = false;
}
</script>

<template>
    <button
        type="button"
        class="focus-visible:ring-ring/50 relative inline-block rounded-lg outline-none focus-visible:ring-[3px]"
        :class="props.disabled ? 'pointer-events-none opacity-60' : 'cursor-pointer'"
        :disabled="props.disabled"
        :aria-label="t('shared.color_theme_picker.title')"
        aria-haspopup="dialog"
        @click="open = true"
    >
        <span
            class="border-border block h-20 w-20 rounded-lg border-2 shadow-sm transition-all duration-200 hover:scale-105 hover:shadow-md"
            :style="{ backgroundColor: `var(--${props.theme}-${activeShade})` }"
        >
            <span class="flex h-full items-center justify-center">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white/90 shadow-lg">
                    <span class="h-6 w-6 rounded-full" :style="{ backgroundColor: `var(--${props.theme}-${activeShade})` }"></span>
                </span>
            </span>
        </span>
    </button>

    <Dialog v-model:open="open">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Palette class="h-5 w-5" />
                    {{ t('shared.color_theme_picker.title') }}
                </DialogTitle>
                <DialogDescription>{{ t('shared.color_theme_picker.description') }}</DialogDescription>
            </DialogHeader>

            <ColorPicker
                :theme="props.theme"
                :shade="props.shade"
                @update:theme="$emit('update:theme', $event)"
                @update:shade="$emit('update:shade', $event)"
            />

            <DialogFooter>
                <Button @click="applyAndClose">{{ t('shared.color_theme_picker.apply') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
