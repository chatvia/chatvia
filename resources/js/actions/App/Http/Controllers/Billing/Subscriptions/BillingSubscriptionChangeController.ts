import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionChangeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionChangeController.php:13
* @route '/billing/subscriptions/change'
*/
const BillingSubscriptionChangeController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: BillingSubscriptionChangeController.url(options),
    method: 'post',
})

BillingSubscriptionChangeController.definition = {
    methods: ["post"],
    url: '/billing/subscriptions/change',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionChangeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionChangeController.php:13
* @route '/billing/subscriptions/change'
*/
BillingSubscriptionChangeController.url = (options?: RouteQueryOptions) => {
    return BillingSubscriptionChangeController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionChangeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionChangeController.php:13
* @route '/billing/subscriptions/change'
*/
BillingSubscriptionChangeController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: BillingSubscriptionChangeController.url(options),
    method: 'post',
})

export default BillingSubscriptionChangeController