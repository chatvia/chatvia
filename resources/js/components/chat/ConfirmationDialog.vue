<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ref } from 'vue';

interface Props {
    title: string;
    description: string;
    confirmText?: string;
    cancelText?: string;
}

withDefaults(defineProps<Props>(), {
    confirmText: 'Confirm',
    cancelText: 'Cancel',
});

const emit = defineEmits<{
    (e: 'confirm'): void;
    (e: 'cancel'): void;
}>();

const isOpen = ref(false);

const confirm = () => {
    emit('confirm');
    isOpen.value = false;
};

const cancel = () => {
    emit('cancel');
    isOpen.value = false;
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <slot name="trigger"></slot>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>
                    {{ description }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="sm:justify-start">
                <Button class="cursor-pointer" type="button" variant="secondary" @click="cancel">
                    {{ cancelText }}
                </Button>
                <Button class="cursor-pointer" type="button" variant="primary" @click="confirm">
                    {{ confirmText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
