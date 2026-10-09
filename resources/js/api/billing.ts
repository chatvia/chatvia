/**
 * The billing overview: current plan, subscription state, any scheduled plan
 * change and the add-ons. The billing page loads it after the first render so
 * the Stripe lookups behind it do not hold up the page. Plan usage lives in
 * `api/usage.ts`.
 */
import { get } from './http';

export type BillingOverview = Domain.Billing.Data.Resources.Subscription.BillingResourceData;

/** Load the billing overview of the signed-in workspace. */
export function fetchBillingOverview(): Promise<BillingOverview> {
    return get<BillingOverview>(route('billing.data'), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
}
