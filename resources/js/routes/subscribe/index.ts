import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscribeFailureController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscribeFailureController.php:9
* @route '/subscribe/failure'
*/
export const failure = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: failure.url(options),
    method: 'get',
})

failure.definition = {
    methods: ["get","head"],
    url: '/subscribe/failure',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscribeFailureController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscribeFailureController.php:9
* @route '/subscribe/failure'
*/
failure.url = (options?: RouteQueryOptions) => {
    return failure.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscribeFailureController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscribeFailureController.php:9
* @route '/subscribe/failure'
*/
failure.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: failure.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscribeFailureController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscribeFailureController.php:9
* @route '/subscribe/failure'
*/
failure.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: failure.url(options),
    method: 'head',
})

const subscribe = {
    failure: Object.assign(failure, failure),
}

export default subscribe