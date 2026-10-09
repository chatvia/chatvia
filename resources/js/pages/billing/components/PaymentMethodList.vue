<script lang="ts" setup>
// The saved cards: brand, last four digits, expiry and holder, with buttons to
// make a card the default or remove it (after confirmation). Shows an empty
// state with an "add" button when there are no cards.
import DeleteConfirmationDialog from '@/components/common/DeleteConfirmationDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useForm } from '@inertiajs/vue3';
import { Check, CreditCard, Plus, Star, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import type { PaymentMethod } from '../types';

defineProps<{
    paymentMethods: PaymentMethod[];
}>();

const emit = defineEmits<{
    add: [];
}>();

const { t } = useI18n();

const removeForm = useForm({
    payment_method_id: '',
});

const defaultForm = useForm({
    payment_method_id: '',
});

const removePaymentMethod = (paymentMethodId: string): void => {
    removeForm.payment_method_id = paymentMethodId;
    removeForm.delete(route('billing.payment-method.remove'));
};

const setDefaultPaymentMethod = (paymentMethodId: string): void => {
    defaultForm.payment_method_id = paymentMethodId;
    defaultForm.post(route('billing.payment-method.default'));
};

const getBrandIcon = (brand: string): string => {
    const brandClasses: Record<string, string> = {
        visa: 'text-blue-600',
        mastercard: 'text-orange-500',
        amex: 'text-green-600',
        discover: 'text-purple-600',
    };
    return brandClasses[brand.toLowerCase()] || 'text-gray-500';
};

const formatExpiry = (month: number, year: number): string => {
    return `${month.toString().padStart(2, '0')}/${year.toString().slice(-2)}`;
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ t('billing.payment_methods.saved_title') }}</CardTitle>
            <CardDescription>
                {{ t('billing.payment_methods.saved_description') }}
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div v-if="paymentMethods.length > 0" class="space-y-4">
                <div
                    v-for="method in paymentMethods"
                    :key="method.id"
                    class="hover:bg-muted/50 flex items-center justify-between rounded-lg border p-4 transition-colors"
                >
                    <div class="flex items-center gap-4">
                        <div class="bg-muted flex h-12 w-12 items-center justify-center rounded-lg">
                            <CreditCard :class="getBrandIcon(method.card.brand)" class="h-6 w-6" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-medium capitalize">
                                    {{ method.card.brand }}
                                </p>
                                <Badge v-if="method.is_default" variant="default" class="text-xs">
                                    <Star class="me-1 h-3 w-3" />
                                    {{ t('billing.payment_methods.default') }}
                                </Badge>
                            </div>
                            <p class="text-muted-foreground text-sm">
                                •••• •••• •••• {{ method.card.last4 }}
                                <span v-if="method.card">
                                    •
                                    {{
                                        t('billing.payment_methods.expires', {
                                            date: formatExpiry(method.card.exp_month, method.card.exp_year),
                                        })
                                    }}
                                </span>
                                <span v-if="method.billing_details.name" class="ms-2"> • {{ method.billing_details.name }} </span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            v-if="!method.is_default"
                            @click="setDefaultPaymentMethod(method.id)"
                            :disabled="defaultForm.processing"
                            variant="outline"
                            size="sm"
                        >
                            <Check class="me-2 h-4 w-4" />
                            {{ t('billing.payment_methods.set_default') }}
                        </Button>

                        <DeleteConfirmationDialog
                            :title="t('billing.payment_methods.remove_dialog.title')"
                            :description="t('billing.payment_methods.remove_dialog.description')"
                            :confirm-text="t('billing.payment_methods.remove_dialog.confirm')"
                            :cancel-text="t('billing.common.cancel')"
                            @confirm="removePaymentMethod(method.id)"
                        >
                            <template #trigger>
                                <Button
                                    :disabled="removeForm.processing"
                                    variant="outline"
                                    size="sm"
                                    class="text-destructive hover:text-destructive"
                                    :aria-label="t('billing.payment_methods.remove_dialog.title')"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </template>
                        </DeleteConfirmationDialog>
                    </div>
                </div>
            </div>

            <div v-else class="py-12 text-center">
                <CreditCard class="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                <h3 class="mb-2 text-lg font-semibold">{{ t('billing.payment_methods.empty_title') }}</h3>
                <p class="text-muted-foreground mb-6">{{ t('billing.payment_methods.empty_description') }}</p>
                <Button @click="emit('add')" class="gap-2">
                    <Plus class="h-4 w-4" />
                    {{ t('billing.payment_methods.add_first') }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
