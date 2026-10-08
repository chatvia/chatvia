<script setup lang="ts">
/**
 * Confirmation step for destructive actions (delete, disconnect, clear).
 *
 * Built on AlertDialog so it can't be dismissed by a stray click outside and is
 * announced as an alert. The confirm button is a plain Button, not
 * AlertDialogAction: reka's AlertDialogAction closes the dialog before its
 * @click runs, so a parent that clears its "item to delete" when the dialog
 * closes would see an empty target in its confirm handler. Here `confirm` is
 * emitted first and the dialog closes after.
 *
 * Closing without confirming (Cancel or Escape) emits `cancel`, so parents
 * that bind `:open` without v-model can still reset their state.
 */
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    title?: string;
    description?: string;
    confirmText?: string;
    cancelText?: string;
    open?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
    (e: 'cancel'): void;
}>();

const { t } = useI18n();

const displayTitle = computed(() => props.title ?? t('shared.delete_confirmation_title'));
const displayDescription = computed(() => props.description ?? t('shared.delete_confirmation_description'));
const displayConfirmText = computed(() => props.confirmText ?? t('shared.yes_delete'));
const displayCancelText = computed(() => props.cancelText ?? t('common.cancel'));

const isOpen = ref(props.open);

watch(
    () => props.open,
    (value) => {
        isOpen.value = value;
    },
);

// Every close that is not a confirm (Cancel button, Escape) counts as cancel.
const onOpenChange = (value: boolean) => {
    isOpen.value = value;
    emit('update:open', value);
    if (!value) {
        emit('cancel');
    }
};

const confirm = () => {
    emit('confirm');
    isOpen.value = false;
    emit('update:open', false);
};
</script>

<template>
    <AlertDialog :open="isOpen" @update:open="onOpenChange">
        <AlertDialogTrigger v-if="$slots.trigger" as-child>
            <slot name="trigger"></slot>
        </AlertDialogTrigger>
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ displayTitle }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ displayDescription }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ displayCancelText }}</AlertDialogCancel>
                <Button variant="destructive" @click="confirm">
                    {{ displayConfirmText }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
