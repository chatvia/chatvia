import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscribeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscribeController.php:11
* @route '/subscribe'
*/
const BillingSubscribeController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BillingSubscribeController.url(options),
    method: 'get',
})

BillingSubscribeController.definition = {
    methods: ["get","head"],
    url: '/subscribe',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscribeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscribeController.php:11
* @route '/subscribe'
*/
BillingSubscribeController.url = (options?: RouteQueryOptions) => {
    return BillingSubscribeController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscribeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscribeController.php:11
* @route '/subscribe'
*/
BillingSubscribeController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BillingSubscribeController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscribeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscribeController.php:11
* @route '/subscribe'
*/
BillingSubscribeController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: BillingSubscribeController.url(options),
    method: 'head',
})

export default BillingSubscribeController