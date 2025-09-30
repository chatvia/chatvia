import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionCancelController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionCancelController.php:10
* @route '/billing/subscription/cancel'
*/
const BillingSubscriptionCancelController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: BillingSubscriptionCancelController.url(options),
    method: 'post',
})

BillingSubscriptionCancelController.definition = {
    methods: ["post"],
    url: '/billing/subscription/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionCancelController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionCancelController.php:10
* @route '/billing/subscription/cancel'
*/
BillingSubscriptionCancelController.url = (options?: RouteQueryOptions) => {
    return BillingSubscriptionCancelController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionCancelController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionCancelController.php:10
* @route '/billing/subscription/cancel'
*/
BillingSubscriptionCancelController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: BillingSubscriptionCancelController.url(options),
    method: 'post',
})

export default BillingSubscriptionCancelController