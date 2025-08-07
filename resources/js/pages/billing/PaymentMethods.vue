<script lang="ts" setup>
import { ref, onMounted, watch } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { loadStripe, type Stripe, type StripeElements, type StripeCardElement } from '@stripe/stripe-js';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CountriesSelect from '@/components/ui/countries-select.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger
} from '@/components/ui/dialog';
import type { BreadcrumbItem } from '@/types';
import ConfirmationDialog from '@/components/chat/ConfirmationDialog.vue';
import {
    CreditCard,
    Plus,
    MoreVertical,
    Trash2,
    Star,
    Check,
    ArrowLeft,
    Loader2,
    CheckCircle,
    AlertCircle,
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';

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
        title: 'Billing',
        href: route('billing.index'),
    },
    {
        title: 'Payment Methods',
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
    }
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
                }
            }
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
        error.value = 'Failed to initialize payment system';
    }
};

const handleAddPaymentMethod = async () => {
    if (!stripe.value || !cardElement.value) {
        error.value = 'Payment system not ready';
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
        const { error: confirmError } = await stripe.value.confirmCardSetup(
            props.intent.client_secret,
            {
                payment_method: paymentMethod!.id,
            }
        );

        if (confirmError) {
            throw new Error(confirmError.message);
        }

        // Success
        success.value = true;
        toast.success('Payment method added successfully!');

        setTimeout(() => {
            showAddPaymentDialog.value = false;
            resetAddPaymentForm();
            // Reload the page to show the new payment method
            window.location.reload();
        }, 1500);

    } catch (err: any) {
        error.value = err.message || 'An error occurred while adding your payment method';
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
            toast.success('Billing information updated successfully!');
        },
        onError: () => {
            toast.error('Failed to update billing information');
        }
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
    <AppLayout title="Payment Methods" :breadcrumbs="breadcrumbs">
        <Head title="Payment Methods" />

        <div class="container mx-auto max-w-5xl py-8">
            <div class="space-y-8">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight flex items-center gap-3">
                            <CreditCard class="h-8 w-8 text-primary" />
                            Payment Methods
                        </h1>
                        <p class="text-muted-foreground mt-2">
                            Manage your payment methods and billing information
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <Button variant="outline" as-child>
                            <Link :href="route('billing.index')">
                                <ArrowLeft class="h-4 w-4 mr-2" />
                                Back to Billing
                            </Link>
                        </Button>
                        <Button
                            @click="showAddPaymentDialog = true"
                            class="gap-2"
                        >
                            <Plus class="h-4 w-4" />
                            Add Payment Method
                        </Button>
                    </div>
                </div>

                <!-- Payment Methods List -->
                <div class="grid gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Saved Payment Methods</CardTitle>
                            <CardDescription>
                                Manage your saved payment methods. You can set a default method for automatic billing.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="paymentMethods.length > 0" class="space-y-4">
                                <div
                                    v-for="method in paymentMethods"
                                    :key="method.id"
                                    class="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50 transition-colors"
                                >
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 rounded-lg bg-muted flex items-center justify-center">
                                            <CreditCard :class="getBrandIcon(method.card.brand)" class="h-6 w-6" />
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <p class="font-medium capitalize">
                                                    {{ method.card.brand }}
                                                </p>
                                                <Badge v-if="method.is_default" variant="default" class="text-xs">
                                                    <Star class="h-3 w-3 mr-1" />
                                                    Default
                                                </Badge>
                                            </div>
                                            <p class="text-sm text-muted-foreground">
                                                •••• •••• •••• {{ method.card.last4 }}
                                                <span v-if="method.card">
                                                    • Expires {{ formatExpiry(method.card.exp_month, method.card.exp_year) }}
                                                </span>
                                                <span v-if="method.billing_details.name" class="ml-2">
                                                    • {{ method.billing_details.name }}
                                                </span>
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
                                            <Check class="h-4 w-4 mr-2" />
                                            Set Default
                                        </Button>

                                        <ConfirmationDialog
                                            title="Remove Payment Method"
                                            description="Are you sure you want to remove this payment method? This action cannot be undone."
                                            confirm-text="Yes, Remove"
                                            cancel-text="Cancel"
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

                            <div v-else class="text-center py-12">
                                <CreditCard class="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                <h3 class="text-lg font-semibold mb-2">No payment methods</h3>
                                <p class="text-muted-foreground mb-6">
                                    Add a payment method to manage your subscriptions and make payments.
                                </p>
                                <Button
                                    @click="showAddPaymentDialog = true"
                                    class="gap-2"
                                >
                                    <Plus class="h-4 w-4" />
                                    Add Your First Payment Method
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Billing Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Billing Information</CardTitle>
                            <CardDescription>
                                Update your billing address and contact information
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="text-sm font-medium">Name</label>
                                        <p class="text-sm text-muted-foreground mt-1">{{ user.name || 'Not set' }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium">Email Address</label>
                                        <p class="text-sm text-muted-foreground mt-1">{{ user.email || 'Not set' }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium">Address</label>
                                        <p class="text-sm text-muted-foreground mt-1">
                                            {{ user.billing_address?.line1 || 'Not set' }}
                                            <span v-if="user.billing_address?.line2">
                                                <br>{{ user.billing_address.line2 }}
                                            </span>
                                        </p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium">City, State & Country</label>
                                        <p class="text-sm text-muted-foreground mt-1">
                                            {{ [user.billing_address?.city, user.billing_address?.state, user.billing_address?.country].filter(Boolean).join(', ') || 'Not set' }}
                                        </p>
                                    </div>
                                </div>
                                <Separator />
                                <Button
                                    @click="showBillingDialog = true"
                                    variant="outline"
                                >
                                    Update Billing Information
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Security Notice -->
                    <Card>
                        <CardContent class="pt-6">
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center flex-shrink-0">
                                    <CreditCard class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <h4 class="font-medium mb-1">Secure Payment Processing</h4>
                                    <p class="text-sm text-muted-foreground">
                                        Your payment information is encrypted and securely processed by Stripe.
                                        We never store your complete card details on our servers.
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
                        Update Billing Information
                    </DialogTitle>
                    <DialogDescription>
                        Update your billing address and contact information for invoicing and payment processing.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="updateBillingInformation" class="space-y-6">
                    <!-- Personal Information -->
                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="billing-name" class="mb-2">Name</Label>
                                <Input
                                    id="billing-name"
                                    v-model="billingForm.name"
                                    placeholder="John Doe"
                                    required
                                />
                            </div>
                            <div>
                                <Label for="billing-email" class="mb-2">Email Address</Label>
                                <Input
                                    id="billing-email"
                                    type="email"
                                    v-model="billingForm.email"
                                    placeholder="john@example.com"
                                    required
                                />
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <!-- Address Information -->
                    <div class="space-y-4">
                        <h4 class="text-sm font-medium">Billing Address</h4>

                        <div class="space-y-4">
                            <div>
                                <Label for="billing-line1" class="mb-2">Address Line 1</Label>
                                <Input
                                    id="billing-line1"
                                    v-model="billingForm.address.line1"
                                    placeholder="123 Main Street"
                                    required
                                />
                            </div>

                            <div>
                                <Label for="billing-line2" class="mb-2">Address Line 2 (Optional)</Label>
                                <Input
                                    id="billing-line2"
                                    v-model="billingForm.address.line2"
                                    placeholder="Apt, suite, etc."
                                />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label for="billing-city" class="mb-2">City</Label>
                                    <Input
                                        id="billing-city"
                                        v-model="billingForm.address.city"
                                        placeholder="New York"
                                        required
                                    />
                                </div>

                                <div>
                                    <Label for="billing-state" class="mb-2">State</Label>
                                    <Input
                                        id="billing-state"
                                        v-model="billingForm.address.state"
                                        placeholder="NY"
                                        required
                                    />
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label for="billing-postal" class="mb-2">Postal Code</Label>
                                    <Input
                                        id="billing-postal"
                                        v-model="billingForm.address.postal_code"
                                        placeholder="10001"
                                        required
                                    />
                                </div>

                                <div>
                                    <Label for="billing-country" class="mb-2">Country</Label>
                                    <CountriesSelect
                                        v-model="billingForm.address.country"
                                        placeholder="Select country..."
                                        :disabled="billingForm.processing"
                                        :required="true"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Error Display -->
                    <div v-if="billingForm.hasErrors" class="flex items-center gap-2 p-3 bg-destructive/10 border border-destructive/20 rounded-md">
                        <AlertCircle class="h-4 w-4 text-destructive" />
                        <div class="text-sm text-destructive">
                            <p v-for="error in Object.values(billingForm.errors)" :key="error" class="mb-1 last:mb-0">
                                {{ error }}
                            </p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showBillingDialog = false"
                            :disabled="billingForm.processing"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="billingForm.processing"
                            class="gap-2"
                        >
                            <template v-if="billingForm.processing">
                                <Loader2 class="h-4 w-4 animate-spin" />
                                Updating...
                            </template>
                            <template v-else>
                                <Check class="h-4 w-4" />
                                Update Information
                            </template>
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Add Payment Method Dialog -->
        <Dialog :open="showAddPaymentDialog" @update:open="handleDialogChange">
            <DialogContent class="sm:max-w-md max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <CreditCard class="h-5 w-5" />
                        Add Payment Method
                    </DialogTitle>
                    <DialogDescription>
                        Add a new payment method to your account. Your card information is secure and encrypted.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="success" class="flex flex-col items-center justify-center py-8 text-center">
                    <CheckCircle class="h-12 w-12 text-green-500 mb-4" />
                    <h3 class="text-lg font-semibold mb-2">Payment Method Added!</h3>
                    <p class="text-sm text-muted-foreground">
                        Your payment method has been successfully added to your account.
                    </p>
                </div>

                <form v-else @submit.prevent="handleAddPaymentMethod" class="space-y-6">
                    <!-- Card Information -->
                    <div class="space-y-4">
                        <label class="text-sm font-medium">Card Information</label>
                        <div
                            ref="cardElementContainer"
                            class="p-3 border border-input rounded-md min-h-[40px] bg-background"
                        ></div>
                    </div>

                    <!-- Error Display -->
                    <div v-if="error" class="flex items-center gap-2 p-3 bg-destructive/10 border border-destructive/20 rounded-md">
                        <AlertCircle class="h-4 w-4 text-destructive" />
                        <p class="text-sm text-destructive">{{ error }}</p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showAddPaymentDialog = false"
                            :disabled="isProcessing"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="isProcessing"
                            class="gap-2"
                        >
                            <template v-if="isProcessing">
                                <Loader2 class="h-4 w-4 animate-spin" />
                                Processing...
                            </template>
                            <template v-else>
                                <CreditCard class="h-4 w-4" />
                                Add Payment Method
                            </template>
                        </Button>
                    </div>
                </form>

                <!-- Security Notice -->
                <div class="flex items-start gap-3 p-3 bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 rounded-md">
                    <CreditCard class="h-4 w-4 text-blue-600 dark:text-blue-400 mt-0.5" />
                    <div>
                        <p class="text-xs text-blue-700 dark:text-blue-300">
                            Your payment information is encrypted and securely processed by Stripe.
                            We never store your complete card details.
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
