import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionScheduleCancelController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionScheduleCancelController.php:11
* @route '/billing/subscription/schedule/cancel'
*/
export const cancel = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(options),
    method: 'post',
})

cancel.definition = {
    methods: ["post"],
    url: '/billing/subscription/schedule/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionScheduleCancelController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionScheduleCancelController.php:11
* @route '/billing/subscription/schedule/cancel'
*/
cancel.url = (options?: RouteQueryOptions) => {
    return cancel.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionScheduleCancelController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionScheduleCancelController.php:11
* @route '/billing/subscription/schedule/cancel'
*/
cancel.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(options),
    method: 'post',
})

const schedule = {
    cancel: Object.assign(cancel, cancel),
}

export default schedule