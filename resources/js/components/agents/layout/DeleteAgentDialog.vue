<script setup lang="ts">
/**
 * Confirms deleting the agent. The owner must type the agent's exact name
 * before the delete button turns on.
 */
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { deleteMethod as deleteAgentRoute } from '@/routes/agents';
import { router } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{
    agentId: string;
    agentName: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();

const isDeleting = ref(false);
const nameConfirmation = ref('');
const canDelete = computed(() => nameConfirmation.value === props.agentName);

function deleteAgent(): void {
    if (!canDelete.value) return;

    isDeleting.value = true;
    router.delete(deleteAgentRoute.url(props.agentId), {
        onSuccess: () => {
            isDeleting.value = false;
            open.value = false;
            nameConfirmation.value = '';
            toast.success(t('agents.nav.delete_dialog.deleted_toast'));
        },
        onError: () => {
            isDeleting.value = false;
            toast.error(t('agents.nav.delete_dialog.delete_error_toast'));
        },
    });
}
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('agents.nav.delete_dialog.title') }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ t('agents.nav.delete_dialog.description', { name: agentName }) }}
                </AlertDialogDescription>
            </AlertDialogHeader>

            <div class="space-y-4">
                <div class="grid gap-2">
                    <Label for="agent-name-confirmation">{{ t('agents.nav.delete_dialog.confirm_label', { name: agentName }) }}</Label>
                    <Input
                        id="agent-name-confirmation"
                        v-model="nameConfirmation"
                        type="text"
                        :placeholder="t('agents.nav.delete_dialog.confirm_placeholder')"
                        :aria-invalid="!!nameConfirmation && !canDelete"
                    />
                    <p v-if="nameConfirmation && !canDelete" class="text-destructive text-sm">
                        {{ t('agents.nav.delete_dialog.name_mismatch') }}
                    </p>
                </div>
            </div>

            <AlertDialogFooter>
                <AlertDialogCancel>{{ t('common.cancel') }}</AlertDialogCancel>
                <Button variant="destructive" @click="deleteAgent" :disabled="isDeleting || !canDelete">
                    <LoaderCircle v-if="isDeleting" class="me-2 h-4 w-4 animate-spin" />
                    {{ t('agents.nav.delete_dialog.submit') }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
