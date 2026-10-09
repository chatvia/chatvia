<script lang="ts" setup>
// Edits the billing contact and address that Stripe prints on invoices. The
// country list only offers countries Stripe supports.
import CountriesSelect from '@/components/common/CountriesSelect.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useForm } from '@inertiajs/vue3';
import { AlertCircle, Check, CreditCard, Loader2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { BillingUser } from '../types';

const props = defineProps<{
    user: BillingUser;
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();

const billingForm = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    address: {
        line1: props.user?.billing_address?.line1 || '',
        line2: props.user?.billing_address?.line2 || '',
        city: props.user?.billing_address?.city || '',
        state: props.user?.billing_address?.state || '',
        postal_code: props.user?.billing_address?.postal_code || '',
        country: props.user?.billing_address?.country || 'US',
    },
});

const updateBillingInformation = (): void => {
    billingForm.patch(route('billing.information.update'), {
        onSuccess: () => {
            open.value = false;
            toast.success(t('billing.payment_methods.toast.info_updated'));
        },
        onError: () => {
            toast.error(t('billing.payment_methods.toast.info_failed'));
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <CreditCard class="h-5 w-5" />
                    {{ t('billing.payment_methods.info_dialog.title') }}
                </DialogTitle>
                <DialogDescription>{{ t('billing.payment_methods.info_dialog.description') }}</DialogDescription>
            </DialogHeader>

            <form @submit.prevent="updateBillingInformation" class="space-y-6">
                <!-- Personal Information -->
                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="billing-name" class="mb-2">{{ t('billing.payment_methods.fields.name') }}</Label>
                            <Input
                                id="billing-name"
                                v-model="billingForm.name"
                                :placeholder="t('billing.payment_methods.placeholders.name')"
                                required
                            />
                        </div>
                        <div>
                            <Label for="billing-email" class="mb-2">{{ t('billing.payment_methods.fields.email') }}</Label>
                            <Input
                                id="billing-email"
                                type="email"
                                v-model="billingForm.email"
                                :placeholder="t('billing.payment_methods.placeholders.email')"
                                required
                            />
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- Address Information -->
                <div class="space-y-4">
                    <h4 class="text-sm font-medium">{{ t('billing.payment_methods.fields.billing_address') }}</h4>

                    <div class="space-y-4">
                        <div>
                            <Label for="billing-line1" class="mb-2">{{ t('billing.payment_methods.fields.line1') }}</Label>
                            <Input
                                id="billing-line1"
                                v-model="billingForm.address.line1"
                                :placeholder="t('billing.payment_methods.placeholders.line1')"
                                required
                            />
                        </div>

                        <div>
                            <Label for="billing-line2" class="mb-2">{{ t('billing.payment_methods.fields.line2') }}</Label>
                            <Input
                                id="billing-line2"
                                v-model="billingForm.address.line2"
                                :placeholder="t('billing.payment_methods.placeholders.line2')"
                            />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="billing-city" class="mb-2">{{ t('billing.payment_methods.fields.city') }}</Label>
                                <Input
                                    id="billing-city"
                                    v-model="billingForm.address.city"
                                    :placeholder="t('billing.payment_methods.placeholders.city')"
                                    required
                                />
                            </div>

                            <div>
                                <Label for="billing-state" class="mb-2">{{ t('billing.payment_methods.fields.state') }}</Label>
                                <Input
                                    id="billing-state"
                                    v-model="billingForm.address.state"
                                    :placeholder="t('billing.payment_methods.placeholders.state')"
                                    required
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="billing-postal" class="mb-2">{{ t('billing.payment_methods.fields.postal_code') }}</Label>
                                <Input
                                    id="billing-postal"
                                    v-model="billingForm.address.postal_code"
                                    :placeholder="t('billing.payment_methods.placeholders.postal_code')"
                                    required
                                />
                            </div>

                            <div>
                                <Label for="billing-country" class="mb-2">{{ t('billing.payment_methods.fields.country') }}</Label>
                                <CountriesSelect
                                    v-model="billingForm.address.country"
                                    :placeholder="t('billing.payment_methods.placeholders.country')"
                                    :disabled="billingForm.processing"
                                    :required="true"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error Display -->
                <div v-if="billingForm.hasErrors" class="bg-destructive/10 border-destructive/20 flex items-center gap-2 rounded-md border p-3">
                    <AlertCircle class="text-destructive h-4 w-4" />
                    <div class="text-destructive text-sm">
                        <p v-for="error in Object.values(billingForm.errors)" :key="error" class="mb-1 last:mb-0">
                            {{ error }}
                        </p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end gap-3">
                    <Button type="button" variant="outline" @click="open = false" :disabled="billingForm.processing">
                        {{ t('billing.common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="billingForm.processing" class="gap-2">
                        <template v-if="billingForm.processing">
                            <Loader2 class="h-4 w-4 animate-spin" />
                            {{ t('billing.payment_methods.updating') }}
                        </template>
                        <template v-else>
                            <Check class="h-4 w-4" />
                            {{ t('billing.payment_methods.update_information') }}
                        </template>
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
