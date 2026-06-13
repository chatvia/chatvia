<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    title: string;
    description: string;
    confirmText?: string;
    cancelText?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'confirm'): void;
    (e: 'cancel'): void;
}>();

const { t } = useI18n();

const displayConfirmText = computed(() => props.confirmText ?? t('common.confirm'));
const displayCancelText = computed(() => props.cancelText ?? t('common.cancel'));

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
                    {{ displayCancelText }}
                </Button>
                <Button class="cursor-pointer" type="button" variant="default" @click="confirm">
                    {{ displayConfirmText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
