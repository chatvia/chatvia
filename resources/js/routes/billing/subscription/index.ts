import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionChangeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionChangeController.php:13
* @route '/billing/subscriptions/change'
*/
export const change = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: change.url(options),
    method: 'post',
})

change.definition = {
    methods: ["post"],
    url: '/billing/subscriptions/change',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionChangeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionChangeController.php:13
* @route '/billing/subscriptions/change'
*/
change.url = (options?: RouteQueryOptions) => {
    return change.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionChangeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionChangeController.php:13
* @route '/billing/subscriptions/change'
*/
change.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: change.url(options),
    method: 'post',
})

const subscription = {
    change: Object.assign(change, change),
}

export default subscription