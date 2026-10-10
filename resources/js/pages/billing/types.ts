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
    /** The feature bullets per billing cycle, rendered by `usePlanText().featureText`. */
    features: Domain.Billing.Data.Resources.Package.BillingPackageFeaturesData;
    capabilities: Record<string, string | number | boolean>;
    monthly_price: number;
    yearly_price: number;
    monthly_price_formatted: string;
    yearly_price_formatted: string;
    /** What paying yearly saves; `percentage` is a whole number, 0 when it saves nothing. */
    monthly_savings: {
        amount: number;
        percentage: number;
        formatted: string;
    };
    is_popular: boolean;
    contact_sales?: boolean;
}

/** The pricing-page settings from `config('billing')`. */
export interface PlanBillingConfig {
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
