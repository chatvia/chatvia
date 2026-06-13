<script lang="ts" setup>
import ConfirmationDialog from '@/components/chat/ConfirmationDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import CountriesSelect from '@/components/ui/countries-select.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { loadStripe, type Stripe, type StripeCardElement, type StripeElements } from '@stripe/stripe-js';
import { AlertCircle, ArrowLeft, Check, CheckCircle, CreditCard, Loader2, Plus, Star, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

interface PaymentMethod {
    id: string;
    object: string;
    type: string;
    billing_details: {
        address: {
            city: string | null;
            country: string;
            line1: string | null;
            line2: string | null;
            postal_code: string | null;
            state: string | null;
        };
        email: string;
        name: string;
        phone: string | null;
        tax_id: string | null;
    };
    card: {
        brand: string;
        country: string;
        exp_month: number;
        exp_year: number;
        last4: string;
        networks: {
            available: string[];
            preferred: string | null;
        };
        three_d_secure_usage: {
            supported: boolean;
        };
    };
    customer: string;
    livemode: boolean;
    metadata: Record<string, any>;
    is_default: boolean;
}

const props = defineProps<{
    intent: any;
    paymentMethods: PaymentMethod[];
    stripeKey: string;
    user: {
        name: string;
        email: string;
        billing_address?: {
            line1?: string;
            line2?: string;
            city?: string;
            state?: string;
            postal_code?: string;
            country?: string;
        };
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('billing.common.billing'),
        href: route('billing.index'),
    },
    {
        title: t('billing.payment_methods.breadcrumb'),
        href: route('billing.payment-methods'),
    },
];

const removeForm = useForm({
    payment_method_id: '',
});

const defaultForm = useForm({
    payment_method_id: '',
});

// Stripe-related state
const showAddPaymentDialog = ref(false);
const isProcessing = ref(false);
const error = ref('');
const success = ref(false);
const stripe = ref<Stripe | null>(null);
const elements = ref<StripeElements | null>(null);
const cardElement = ref<StripeCardElement | null>(null);
const cardElementContainer = ref<HTMLElement | null>(null);

// Billing information state
const showBillingDialog = ref(false);
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

const removePaymentMethod = (paymentMethodId: string) => {
    removeForm.payment_method_id = paymentMethodId;
    removeForm.delete(route('billing.payment-method.remove'));
};

const setDefaultPaymentMethod = (paymentMethodId: string) => {
    defaultForm.payment_method_id = paymentMethodId;
    defaultForm.post(route('billing.payment-method.default'));
};

const initializeStripe = async () => {
    try {
        if (!props.stripeKey) {
            throw new Error('Stripe key is not provided');
        }

        // Load Stripe
        stripe.value = await loadStripe(props.stripeKey);

        if (!stripe.value) {
            throw new Error('Failed to load Stripe');
        }

        // Create elements
        elements.value = stripe.value.elements({
            appearance: {
                theme: 'stripe',
                variables: {
                    colorPrimary: 'hsl(var(--primary))',
                    colorBackground: 'hsl(var(--background))',
                    colorText: 'hsl(var(--foreground))',
                    colorDanger: 'hsl(var(--destructive))',
                    fontFamily: 'Inter, system-ui, sans-serif',
                    spacingUnit: '4px',
                    borderRadius: '6px',
                },
            },
        });

        // Create card element
        if (elements.value) {
            cardElement.value = elements.value.create('card', {
                style: {
                    base: {
                        fontSize: '16px',
                        color: 'hsl(var(--foreground))',
                        fontFamily: 'Inter, system-ui, sans-serif',
                        '::placeholder': {
                            color: 'hsl(var(--muted-foreground))',
                        },
                    },
                    invalid: {
                        color: 'hsl(var(--destructive))',
                    },
                },
            });

            // Mount the card element
            if (cardElementContainer.value && cardElement.value) {
                cardElement.value.mount(cardElementContainer.value);
            }

            // Listen for changes
            if (cardElement.value) {
                cardElement.value.on('change', (event: any) => {
                    if (event.error) {
                        error.value = event.error.message;
                    } else {
                        error.value = '';
                    }
                });
            }
        }
    } catch (err) {
        console.error('Error initializing Stripe:', err);
        error.value = t('billing.payment_methods.errors.init_failed');
    }
};

const handleAddPaymentMethod = async () => {
    if (!stripe.value || !cardElement.value) {
        error.value = t('billing.payment_methods.errors.not_ready');
        return;
    }

    isProcessing.value = true;
    error.value = '';

    try {
        // Create payment method
        const { error: createError, paymentMethod } = await stripe.value.createPaymentMethod({
            type: 'card',
            card: cardElement.value,
        });

        if (createError) {
            throw new Error(createError.message);
        }

        // Confirm setup intent
        const { error: confirmError } = await stripe.value.confirmCardSetup(props.intent.client_secret, {
            payment_method: paymentMethod!.id,
        });

        if (confirmError) {
            throw new Error(confirmError.message);
        }

        // Success
        success.value = true;
        toast.success(t('billing.payment_methods.toast.added'));

        setTimeout(() => {
            showAddPaymentDialog.value = false;
            resetAddPaymentForm();
            // Reload the page to show the new payment method
            window.location.reload();
        }, 1500);
    } catch (err: any) {
        error.value = err.message || t('billing.payment_methods.errors.add_failed');
        toast.error(error.value);
    } finally {
        isProcessing.value = false;
    }
};

const resetAddPaymentForm = () => {
    error.value = '';
    success.value = false;

    // Clear the card element
    if (cardElement.value) {
        cardElement.value.clear();
    }
};

const handleDialogChange = (newOpen: boolean) => {
    showAddPaymentDialog.value = newOpen;
    if (newOpen && !stripe.value) {
        // Initialize Stripe when dialog opens
        setTimeout(() => initializeStripe(), 100); // Small delay to ensure DOM is ready
    } else if (!newOpen && !success.value) {
        resetAddPaymentForm();
    }
};

// Watch for dialog opening to initialize Stripe
watch(showAddPaymentDialog, (newValue) => {
    if (newValue && !stripe.value) {
        // Initialize Stripe when dialog opens
        setTimeout(() => initializeStripe(), 100);
    } else if (!newValue && !success.value) {
        resetAddPaymentForm();
    }
});

const updateBillingInformation = () => {
    billingForm.patch(route('billing.information.update'), {
        onSuccess: () => {
            showBillingDialog.value = false;
            toast.success(t('billing.payment_methods.toast.info_updated'));
        },
        onError: () => {
            toast.error(t('billing.payment_methods.toast.info_failed'));
        },
    });
};

const getBrandIcon = (brand: string) => {
    // You can replace these with actual brand icons
    const brandClasses: Record<string, string> = {
        visa: 'text-blue-600',
        mastercard: 'text-orange-500',
        amex: 'text-green-600',
        discover: 'text-purple-600',
    };
    return brandClasses[brand.toLowerCase()] || 'text-gray-500';
};

const formatExpiry = (month: number, year: number) => {
    return `${month.toString().padStart(2, '0')}/${year.toString().slice(-2)}`;
};
</script>

<template>
    <AppLayout :title="t('billing.payment_methods.page_title')" :breadcrumbs="breadcrumbs">
        <Head :title="t('billing.payment_methods.page_title')" />

        <div class="container mx-auto max-w-5xl py-8">
            <div class="space-y-8">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="flex items-center gap-3 text-3xl font-bold tracking-tight">
                            <CreditCard class="text-primary h-8 w-8" />
                            {{ t('billing.payment_methods.heading') }}
                        </h1>
                        <p class="text-muted-foreground mt-2">{{ t('billing.payment_methods.subheading') }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <Button variant="outline" as-child>
                            <Link :href="route('billing.index')">
                                <ArrowLeft class="me-2 h-4 w-4" />
                                {{ t('billing.common.back_to_billing') }}
                            </Link>
                        </Button>
                        <Button @click="showAddPaymentDialog = true" class="gap-2">
                            <Plus class="h-4 w-4" />
                            {{ t('billing.payment_methods.add_method') }}
                        </Button>
                    </div>
                </div>

                <!-- Payment Methods List -->
                <div class="grid gap-6">
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

                                        <ConfirmationDialog
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
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </template>
                                        </ConfirmationDialog>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="py-12 text-center">
                                <CreditCard class="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                <h3 class="mb-2 text-lg font-semibold">{{ t('billing.payment_methods.empty_title') }}</h3>
                                <p class="text-muted-foreground mb-6">{{ t('billing.payment_methods.empty_description') }}</p>
                                <Button @click="showAddPaymentDialog = true" class="gap-2">
                                    <Plus class="h-4 w-4" />
                                    {{ t('billing.payment_methods.add_first') }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Billing Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ t('billing.payment_methods.info_title') }}</CardTitle>
                            <CardDescription>{{ t('billing.payment_methods.info_description') }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="text-sm font-medium">{{ t('billing.payment_methods.fields.name') }}</label>
                                        <p class="text-muted-foreground mt-1 text-sm">{{ user.name || t('billing.payment_methods.not_set') }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium">{{ t('billing.payment_methods.fields.email') }}</label>
                                        <p class="text-muted-foreground mt-1 text-sm">{{ user.email || t('billing.payment_methods.not_set') }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium">{{ t('billing.payment_methods.fields.address') }}</label>
                                        <p class="text-muted-foreground mt-1 text-sm">
                                            {{ user.billing_address?.line1 || t('billing.payment_methods.not_set') }}
                                            <span v-if="user.billing_address?.line2"> <br />{{ user.billing_address.line2 }} </span>
                                        </p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium">{{ t('billing.payment_methods.fields.city_state_country') }}</label>
                                        <p class="text-muted-foreground mt-1 text-sm">
                                            {{
                                                [user.billing_address?.city, user.billing_address?.state, user.billing_address?.country]
                                                    .filter(Boolean)
                                                    .join(', ') || t('billing.payment_methods.not_set')
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <Separator />
                                <Button @click="showBillingDialog = true" variant="outline">{{ t('billing.payment_methods.update_info') }}</Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Security Notice -->
                    <Card>
                        <CardContent class="pt-6">
                            <div class="flex items-start gap-3">
                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/20">
                                    <CreditCard class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <h4 class="mb-1 font-medium">{{ t('billing.payment_methods.secure_title') }}</h4>
                                    <p class="text-muted-foreground text-sm">
                                        {{ t('billing.payment_methods.secure_description') }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Billing Information Update Dialog -->
        <Dialog v-model:open="showBillingDialog">
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
                        <Button type="button" variant="outline" @click="showBillingDialog = false" :disabled="billingForm.processing">
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

        <!-- Add Payment Method Dialog -->
        <Dialog :open="showAddPaymentDialog" @update:open="handleDialogChange">
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
                        <Button type="button" variant="outline" @click="showAddPaymentDialog = false" :disabled="isProcessing">{{
                            t('billing.common.cancel')
                        }}</Button>
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
    </AppLayout>
</template>

<style scoped>
/* Stripe element styling */
:deep(.StripeElement) {
    padding: 8px 0;
}

:deep(.StripeElement--focus) {
    outline: 2px solid hsl(var(--ring));
    outline-offset: 2px;
}

:deep(.StripeElement--invalid) {
    color: hsl(var(--destructive));
}
</style>
