<script lang="ts" setup>
// A plan change scheduled for the end of the billing period (usually a
// downgrade), with a way to call it off before it takes effect.
import DeleteConfirmationDialog from '@/components/common/DeleteConfirmationDialog.vue';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/vue3';
import { Calendar, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { useBillingTimestamp } from '../composables/useBillingTimestamp';

defineProps<{
    change: Domain.Billing.Data.Resources.Subscription.BillingPendingChangeData;
}>();

const emit = defineEmits<{
    refresh: [];
}>();

const { t } = useI18n();
const { formatTimestamp } = useBillingTimestamp();

const cancelScheduleForm = useForm({});

const cancelScheduledPlanChange = (): void => {
    cancelScheduleForm.post(route('billing.schedule.cancel'), {
        preserveScroll: true,
        onSuccess: () => emit('refresh'),
    });
};
</script>

<template>
    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950/20">
        <div class="flex items-start gap-3">
            <Calendar class="mt-0.5 h-5 w-5 text-blue-500" />
            <div>
                <h4 class="font-medium text-blue-800 dark:text-blue-200">
                    {{ t('billing.index.pending_change.heading') }}
                </h4>
                <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                    <template v-if="change.from_package && change.to_package">
                        {{ t('billing.index.pending_change.change_from') }}
                        <span class="font-medium">{{ change.from_package }}</span>
                        {{ t('billing.index.pending_change.to') }}
                        <span class="font-medium">{{ change.to_package }}</span>
                        {{ change.is_yearly ? t('billing.index.pending_change.cycle_yearly') : t('billing.index.pending_change.cycle_monthly') }}
                        {{ t('billing.index.pending_change.on_date', { date: formatTimestamp(change.effective_date) }) }}
                    </template>
                    <template v-else>
                        {{ t('billing.index.pending_change.scheduled_for', { date: formatTimestamp(change.effective_date) }) }}
                    </template>
                </p>
                <p class="mt-2 text-xs text-blue-600 dark:text-blue-400">
                    {{ t('billing.index.pending_change.access_note') }}
                </p>
                <div class="mt-3">
                    <DeleteConfirmationDialog
                        :title="t('billing.index.pending_change.cancel_dialog.title')"
                        :description="t('billing.index.pending_change.cancel_dialog.description')"
                        :confirm-text="t('billing.index.pending_change.cancel_dialog.confirm')"
                        :cancel-text="t('billing.index.pending_change.cancel_dialog.cancel')"
                        @confirm="cancelScheduledPlanChange"
                    >
                        <template #trigger>
                            <Button variant="outline" size="sm" :disabled="cancelScheduleForm.processing" class="gap-2">
                                <Trash2 class="h-4 w-4" />
                                {{ t('billing.index.pending_change.cancel_schedule') }}
                            </Button>
                        </template>
                    </DeleteConfirmationDialog>
                </div>
            </div>
        </div>
    </div>
</template>
