<script lang="ts" setup>
// Dialog that adds a card through Stripe Elements. The card input lives in a
// Stripe iframe mounted into `cardElementContainer`; see useStripeCardSetup.
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { AlertCircle, CheckCircle, CreditCard, Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useStripeCardSetup } from '../composables/useStripeCardSetup';

const props = defineProps<{
    stripeKey: string;
    clientSecret: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();

const cardElementContainer = ref<HTMLElement | null>(null);

const { isProcessing, error, success, handleAddPaymentMethod, handleDialogChange } = useStripeCardSetup({
    stripeKey: () => props.stripeKey,
    clientSecret: () => props.clientSecret,
    open,
    container: cardElementContainer,
});
</script>

<template>
    <Dialog :open="open" @update:open="handleDialogChange">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <CreditCard class="h-5 w-5" />
                    {{ t('billing.payment_methods.add_method') }}
                </DialogTitle>
                <DialogDescription>{{ t('billing.payment_methods.add_dialog.description') }}</DialogDescription>
            </DialogHeader>

            <div v-if="success" class="flex flex-col items-center justify-center py-8 text-center">
                <CheckCircle class="mb-4 h-12 w-12 text-green-500" />
                <h3 class="mb-2 text-lg font-semibold">{{ t('billing.payment_methods.add_dialog.success_title') }}</h3>
                <p class="text-muted-foreground text-sm">{{ t('billing.payment_methods.add_dialog.success_description') }}</p>
            </div>

            <form v-else @submit.prevent="handleAddPaymentMethod" class="space-y-6">
                <!-- Card Information -->
                <div class="space-y-4">
                    <label class="text-sm font-medium">{{ t('billing.payment_methods.card_information') }}</label>
                    <div ref="cardElementContainer" class="border-input bg-background min-h-[40px] rounded-md border p-3"></div>
                </div>

                <!-- Error Display -->
                <div v-if="error" class="bg-destructive/10 border-destructive/20 flex items-center gap-2 rounded-md border p-3">
                    <AlertCircle class="text-destructive h-4 w-4" />
                    <p class="text-destructive text-sm">{{ error }}</p>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end gap-3">
                    <Button type="button" variant="outline" @click="open = false" :disabled="isProcessing">{{ t('billing.common.cancel') }}</Button>
                    <Button type="submit" :disabled="isProcessing" class="gap-2">
                        <template v-if="isProcessing">
                            <Loader2 class="h-4 w-4 animate-spin" />
                            {{ t('billing.common.processing') }}
                        </template>
                        <template v-else>
                            <CreditCard class="h-4 w-4" />
                            {{ t('billing.payment_methods.add_method') }}
                        </template>
                    </Button>
                </div>
            </form>

            <!-- Security Notice -->
            <div class="flex items-start gap-3 rounded-md border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950/20">
                <CreditCard class="mt-0.5 h-4 w-4 text-blue-600 dark:text-blue-400" />
                <div>
                    <p class="text-xs text-blue-700 dark:text-blue-300">
                        {{ t('billing.payment_methods.add_dialog.secure_note') }}
                    </p>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
/* Stripe element styling */
:deep(.StripeElement) {
    padding: 8px 0;
}

:deep(.StripeElement--focus) {
    outline: 2px solid var(--ring);
    outline-offset: 2px;
}

:deep(.StripeElement--invalid) {
    color: var(--destructive);
}
</style>
