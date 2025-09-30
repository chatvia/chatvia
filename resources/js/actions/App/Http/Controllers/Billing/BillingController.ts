import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\BillingController::__invoke
* @see app/Http/Controllers/Billing/BillingController.php:12
* @route '/billing'
*/
const BillingController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BillingController.url(options),
    method: 'get',
})

BillingController.definition = {
    methods: ["get","head"],
    url: '/billing',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Billing\BillingController::__invoke
* @see app/Http/Controllers/Billing/BillingController.php:12
* @route '/billing'
*/
BillingController.url = (options?: RouteQueryOptions) => {
    return BillingController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\BillingController::__invoke
* @see app/Http/Controllers/Billing/BillingController.php:12
* @route '/billing'
*/
BillingController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BillingController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Billing\BillingController::__invoke
* @see app/Http/Controllers/Billing/BillingController.php:12
* @route '/billing'
*/
BillingController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: BillingController.url(options),
    method: 'head',
})

export default BillingController