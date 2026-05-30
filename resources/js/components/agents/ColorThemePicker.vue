<script setup lang="ts">
import ColorPicker from '@/components/agents/ColorPicker.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Palette } from 'lucide-vue-next';
import { ref } from 'vue';

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

function applyAndClose(): void {
    emit('apply');
    open.value = false;
}
</script>

<template>
    <div
        class="relative inline-block"
        :class="props.disabled ? 'pointer-events-none opacity-60' : 'cursor-pointer'"
        @click="props.disabled || (open = true)"
    >
        <div
            class="border-border h-20 w-20 rounded-lg border-2 shadow-sm transition-all duration-200 hover:scale-105 hover:shadow-md"
            :style="{ backgroundColor: `var(--${props.theme}-${props.shade})` }"
        >
            <div class="flex h-full items-center justify-center">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/90 shadow-lg">
                    <div class="h-6 w-6 rounded-full" :style="{ backgroundColor: `var(--${props.theme}-${props.shade})` }"></div>
                </div>
            </div>
        </div>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Palette class="h-5 w-5" />
                    Choose Color Theme
                </DialogTitle>
                <DialogDescription>Select a color and shade for your agent's primary theme</DialogDescription>
            </DialogHeader>

            <ColorPicker
                :theme="props.theme"
                :shade="props.shade"
                @update:theme="$emit('update:theme', $event)"
                @update:shade="$emit('update:shade', $event)"
            />

            <DialogFooter>
                <Button @click="applyAndClose">Apply</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
