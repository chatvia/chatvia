<script setup lang="ts">
/**
 * Confirms dismissing a topic suggestion. The label lives in the parent apart
 * from the open state: reka's AlertDialogAction closes the dialog before its
 * click handler runs, so the parent must not clear the label on close.
 */
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useI18n } from 'vue-i18n';

defineProps<{
    label: string | null;
    dismissing: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{ confirm: [] }>();

const { t } = useI18n();
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('agents.topics_index.dismiss_dialog_title') }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ t('agents.topics_index.dismiss_dialog_description', { label }) }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="dismissing">{{ t('common.cancel') }}</AlertDialogCancel>
                <AlertDialogAction :disabled="dismissing" @click="emit('confirm')">{{ t('agents.topics_index.dismiss') }}</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
