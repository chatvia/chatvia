/**
 * Prop shapes for the billing pages that the server sends as plain arrays
 * (Stripe objects, `config('billing')` values) rather than generated Data
 * classes. Kept in one place so the pages and their child components agree.
 */

/** A saved card, as `BillingPaymentMethodController` maps it from Stripe. */
export interface PaymentMethod {
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
    metadata: Record<string, string>;
    is_default: boolean;
}

/** The Stripe SetupIntent the page confirms a new card against. */
export interface SetupIntent {
    client_secret: string;
}

/** The address Stripe keeps for invoices. Every part is optional. */
export interface BillingAddress {
    line1?: string;
    line2?: string;
    city?: string;
    state?: string;
    postal_code?: string;
    country?: string;
}

/** The billing contact shown and edited on the payment methods page. */
export interface BillingUser {
    name: string;
    email: string;
    billing_address?: BillingAddress;
}

/** One plan from `PlanCatalog::availablePackages()`. */
export interface PlanPackage {
    id: string;
    name: string;
    description: string;
    features_monthly: Record<string, string>;
    features_yearly: Record<string, string>;
    capabilities: Record<string, string | number | boolean>;
    monthly_price: number;
    yearly_price: number;
    monthly_price_formatted: string;
    yearly_price_formatted: string;
    monthly_savings: {
        amount: number;
        percentage: number;
        formatted: string;
    };
    is_popular: boolean;
    contact_sales?: boolean;
}

/** A language model listed in the "available models" dialog. */
export interface PlanLlm {
    name: string;
    icon: string;
    paid: boolean;
}

/** The pricing-page settings from `config('billing')`. */
export interface PlanBillingConfig {
    ui: {
        yearly_savings_percentage: number;
        yearly_savings_text: string;
        most_popular_text: string;
        current_plan_text: string;
        popular_badge_text: string;

        money_back_guarantee_days: number;
        yearly_upfront_text: string;
        yearly_benefit_text: string;
    };
    commonFeatures: Record<string, string>;
    packageIcons: Record<string, string>;
    currencySymbol: string;
    trial: {
        days: number;
        package: string;
    };
    discount: {
        percentage: number;
        ends_at: string | null;
    };
}

/** The texts of one per-unit monthly add-on card, translated by the parent. */
export interface RecurringAddonLabels {
    title: string;
    summary: string;
    perUnit: string;
    cancelTitle: string;
    cancelDescription: string;
    changeTitle: string;
    subscribeTitle: string;
    dialogDescription: string;
    quantityLabel: string;
}
