<script lang="ts" setup>
// Links to payment methods, invoices and the Stripe portal, plus changing or
// cancelling the subscription. Hidden until the workspace subscribes or
// starts a trial.
import type { BillingOverview } from '@/api/billing';
import DeleteConfirmationDialog from '@/components/common/DeleteConfirmationDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Link, useForm } from '@inertiajs/vue3';
import { CreditCard, CreditCardIcon, Package, Receipt, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    billingData: BillingOverview;
}>();

const emit = defineEmits<{
    refresh: [];
}>();

const { t } = useI18n();

const cancelForm = useForm({
    immediately: false as boolean,
});

const cancelSubscription = (immediately = false): void => {
    cancelForm.immediately = immediately;
    cancelForm.post(route('billing.cancel'), {
        preserveScroll: true,
        onSuccess: () => emit('refresh'),
    });
};
</script>

<template>
    <Card v-show="billingData.subscribed || billingData.on_trial">
        <CardHeader>
            <CardTitle class="text-lg">{{ t('billing.index.management.title') }}</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="space-y-3">
                <Button variant="outline" class="w-full justify-start gap-2" as-child>
                    <Link :href="route('billing.payment-methods')">
                        <CreditCard class="h-4 w-4" />
                        {{ t('billing.index.management.manage_payment_methods') }}
                    </Link>
                </Button>
                <Button variant="outline" class="w-full justify-start gap-2" as-child>
                    <Link :href="route('billing.invoices')">
                        <Receipt class="h-4 w-4" />
                        {{ t('billing.index.management.view_invoices') }}
                    </Link>
                </Button>
                <Button variant="outline" class="w-full justify-start gap-2" as-child>
                    <a target="_blank" :href="route('billing.portal')">
                        <CreditCardIcon class="h-4 w-4" />
                        {{ t('billing.index.management.go_to_stripe') }}
                    </a>
                </Button>
                <Separator class="my-2" />
            </div>

            <template v-if="billingData.subscription && !billingData.subscription_canceled">
                <Button class="w-full justify-start gap-2" as-child>
                    <Link :href="route('billing.packages')">
                        <Package class="h-4 w-4" />
                        {{ t('billing.index.management.change_plan') }}
                    </Link>
                </Button>

                <DeleteConfirmationDialog
                    v-if="!billingData.subscription_canceled"
                    :title="t('billing.index.cancel_dialog.title')"
                    :description="t('billing.index.cancel_dialog.description')"
                    :confirm-text="t('billing.index.cancel_dialog.confirm')"
                    :cancel-text="t('billing.index.cancel_dialog.cancel')"
                    @confirm="cancelSubscription(false)"
                >
                    <template #trigger>
                        <Button variant="destructive" :disabled="cancelForm.processing" class="w-full justify-start gap-2">
                            <Trash2 class="h-4 w-4" />
                            {{ t('billing.index.management.cancel_subscription') }}
                        </Button>
                    </template>
                </DeleteConfirmationDialog>
            </template>
        </CardContent>
    </Card>
</template>
