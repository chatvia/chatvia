import { loadStripe, type Stripe, type StripeCardElement, type StripeCardElementChangeEvent, type StripeElements } from '@stripe/stripe-js';
import { ref, watch, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface StripeCardSetupOptions {
    /** Publishable Stripe key. */
    stripeKey: () => string;
    /** Client secret of the SetupIntent the new card is confirmed against. */
    clientSecret: () => string;
    /** Whether the "add payment method" dialog is open. */
    open: Ref<boolean>;
    /** The element Stripe mounts its card input into. */
    container: Ref<HTMLElement | null>;
}

// Stripe Elements renders inside its own iframe, so it cannot read our CSS
// variables. Resolve a theme variable to the concrete color the browser
// computes (an `rgb(...)` string, which Stripe accepts) before passing it on.
const themeColor = (variable: string): string => {
    const probe = document.createElement('span');
    probe.style.display = 'none';
    probe.style.color = `var(${variable})`;
    document.body.appendChild(probe);
    const color = getComputedStyle(probe).color;
    probe.remove();
    return color;
};

/**
 * Adds a card with Stripe Elements: loads Stripe.js the first time the dialog
 * opens, mounts a themed card input, then creates the payment method and
 * confirms the SetupIntent with it. On success the page reloads so the server
 * renders the new card in the list.
 */
export function useStripeCardSetup(options: StripeCardSetupOptions) {
    const { t } = useI18n();

    const isProcessing = ref(false);
    const error = ref('');
    const success = ref(false);
    const stripe = ref<Stripe | null>(null);
    const elements = ref<StripeElements | null>(null);
    const cardElement = ref<StripeCardElement | null>(null);

    const initializeStripe = async (): Promise<void> => {
        try {
            if (!options.stripeKey()) {
                throw new Error('Stripe key is not provided');
            }

            stripe.value = await loadStripe(options.stripeKey());

            if (!stripe.value) {
                throw new Error('Failed to load Stripe');
            }

            const colors = {
                primary: themeColor('--primary'),
                background: themeColor('--background'),
                foreground: themeColor('--foreground'),
                mutedForeground: themeColor('--muted-foreground'),
                destructive: themeColor('--destructive'),
            };

            elements.value = stripe.value.elements({
                appearance: {
                    theme: 'stripe',
                    variables: {
                        colorPrimary: colors.primary,
                        colorBackground: colors.background,
                        colorText: colors.foreground,
                        colorDanger: colors.destructive,
                        fontFamily: 'Inter, system-ui, sans-serif',
                        spacingUnit: '4px',
                        borderRadius: '6px',
                    },
                },
            });

            if (elements.value) {
                cardElement.value = elements.value.create('card', {
                    style: {
                        base: {
                            fontSize: '16px',
                            color: colors.foreground,
                            fontFamily: 'Inter, system-ui, sans-serif',
                            '::placeholder': {
                                color: colors.mutedForeground,
                            },
                        },
                        invalid: {
                            color: colors.destructive,
                        },
                    },
                });

                if (options.container.value && cardElement.value) {
                    cardElement.value.mount(options.container.value);
                }

                if (cardElement.value) {
                    cardElement.value.on('change', (event: StripeCardElementChangeEvent) => {
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

    const resetAddPaymentForm = (): void => {
        error.value = '';
        success.value = false;

        if (cardElement.value) {
            cardElement.value.clear();
        }
    };

    const handleAddPaymentMethod = async (): Promise<void> => {
        if (!stripe.value || !cardElement.value) {
            error.value = t('billing.payment_methods.errors.not_ready');
            return;
        }

        isProcessing.value = true;
        error.value = '';

        try {
            const { error: createError, paymentMethod } = await stripe.value.createPaymentMethod({
                type: 'card',
                card: cardElement.value,
            });

            if (createError) {
                throw new Error(createError.message);
            }

            const { error: confirmError } = await stripe.value.confirmCardSetup(options.clientSecret(), {
                payment_method: paymentMethod.id,
            });

            if (confirmError) {
                throw new Error(confirmError.message);
            }

            success.value = true;
            toast.success(t('billing.payment_methods.toast.added'));

            setTimeout(() => {
                options.open.value = false;
                resetAddPaymentForm();
                // The list is server-rendered, so reload to show the new card.
                window.location.reload();
            }, 1500);
        } catch (err) {
            error.value = (err instanceof Error && err.message) || t('billing.payment_methods.errors.add_failed');
            toast.error(error.value);
        } finally {
            isProcessing.value = false;
        }
    };

    // Stripe loads on the first open. The short delay lets the dialog render the
    // card container before Stripe mounts into it.
    const handleDialogChange = (newOpen: boolean): void => {
        options.open.value = newOpen;
        if (newOpen && !stripe.value) {
            setTimeout(() => initializeStripe(), 100);
        } else if (!newOpen && !success.value) {
            resetAddPaymentForm();
        }
    };

    watch(options.open, (newValue) => {
        if (newValue && !stripe.value) {
            setTimeout(() => initializeStripe(), 100);
        } else if (!newValue && !success.value) {
            resetAddPaymentForm();
        }
    });

    return { isProcessing, error, success, handleAddPaymentMethod, handleDialogChange };
}
