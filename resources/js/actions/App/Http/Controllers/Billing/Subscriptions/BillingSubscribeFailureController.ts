import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscribeFailureController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscribeFailureController.php:9
* @route '/subscribe/failure'
*/
const BillingSubscribeFailureController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BillingSubscribeFailureController.url(options),
    method: 'get',
})

BillingSubscribeFailureController.definition = {
    methods: ["get","head"],
    url: '/subscribe/failure',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscribeFailureController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscribeFailureController.php:9
* @route '/subscribe/failure'
*/
BillingSubscribeFailureController.url = (options?: RouteQueryOptions) => {
    return BillingSubscribeFailureController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscribeFailureController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscribeFailureController.php:9
* @route '/subscribe/failure'
*/
BillingSubscribeFailureController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BillingSubscribeFailureController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscribeFailureController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscribeFailureController.php:9
* @route '/subscribe/failure'
*/
BillingSubscribeFailureController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: BillingSubscribeFailureController.url(options),
    method: 'head',
})

export default BillingSubscribeFailureController