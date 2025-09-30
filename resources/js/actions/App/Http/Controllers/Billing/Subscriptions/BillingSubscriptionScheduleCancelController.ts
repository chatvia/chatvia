import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionScheduleCancelController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionScheduleCancelController.php:11
* @route '/billing/subscription/schedule/cancel'
*/
const BillingSubscriptionScheduleCancelController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: BillingSubscriptionScheduleCancelController.url(options),
    method: 'post',
})

BillingSubscriptionScheduleCancelController.definition = {
    methods: ["post"],
    url: '/billing/subscription/schedule/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionScheduleCancelController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionScheduleCancelController.php:11
* @route '/billing/subscription/schedule/cancel'
*/
BillingSubscriptionScheduleCancelController.url = (options?: RouteQueryOptions) => {
    return BillingSubscriptionScheduleCancelController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionScheduleCancelController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionScheduleCancelController.php:11
* @route '/billing/subscription/schedule/cancel'
*/
BillingSubscriptionScheduleCancelController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: BillingSubscriptionScheduleCancelController.url(options),
    method: 'post',
})

export default BillingSubscriptionScheduleCancelController