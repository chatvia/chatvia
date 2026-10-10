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
                <div>
                    <label for="agent-name-confirmation" class="text-foreground mb-2 block text-sm font-medium">
                        {{ t('agents.nav.delete_dialog.confirm_label', { name: agentName }) }}
                    </label>
                    <input
                        id="agent-name-confirmation"
                        v-model="nameConfirmation"
                        type="text"
                        class="border-input bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring w-full rounded-md border px-3 py-2 focus:border-transparent focus:ring-2 focus:outline-none"
                        :placeholder="t('agents.nav.delete_dialog.confirm_placeholder')"
                        :class="{
                            'border-red-500 focus:ring-red-500': nameConfirmation && !canDelete,
                            'border-green-500 focus:ring-green-500': canDelete,
                        }"
                    />
                    <p v-if="nameConfirmation && !canDelete" class="mt-1 text-sm text-red-500">
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
