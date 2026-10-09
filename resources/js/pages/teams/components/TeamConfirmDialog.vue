<script setup lang="ts">
// Confirmation for a destructive action on the team page. The confirm button
// is a plain Button (not AlertDialogAction) so the dialog stays open, with a
// spinner, until the request finishes and the parent clears its target.
import { Loader2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';

defineProps<{
    open: boolean;
    title: string;
    description: string;
    confirmLabel: string;
    processing: boolean;
}>();

const emit = defineEmits<{
    confirm: [];
    close: [];
}>();

const { t } = useI18n();
</script>

<template>
    <AlertDialog :open="open" @update:open="(value) => !value && emit('close')">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ description }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ t('teams.common.cancel') }}</AlertDialogCancel>
                <Button variant="destructive" @click="emit('confirm')" :disabled="processing">
                    <Loader2 v-if="processing" class="me-2 size-4 animate-spin" />
                    {{ confirmLabel }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
