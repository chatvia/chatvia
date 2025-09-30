import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\BillingPaymentMethodAddController::__invoke
* @see app/Http/Controllers/Billing/BillingPaymentMethodAddController.php:10
* @route '/billing/payments/create'
*/
const BillingPaymentMethodAddController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BillingPaymentMethodAddController.url(options),
    method: 'get',
})

BillingPaymentMethodAddController.definition = {
    methods: ["get","head"],
    url: '/billing/payments/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Billing\BillingPaymentMethodAddController::__invoke
* @see app/Http/Controllers/Billing/BillingPaymentMethodAddController.php:10
* @route '/billing/payments/create'
*/
BillingPaymentMethodAddController.url = (options?: RouteQueryOptions) => {
    return BillingPaymentMethodAddController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\BillingPaymentMethodAddController::__invoke
* @see app/Http/Controllers/Billing/BillingPaymentMethodAddController.php:10
* @route '/billing/payments/create'
*/
BillingPaymentMethodAddController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BillingPaymentMethodAddController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Billing\BillingPaymentMethodAddController::__invoke
* @see app/Http/Controllers/Billing/BillingPaymentMethodAddController.php:10
* @route '/billing/payments/create'
*/
BillingPaymentMethodAddController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: BillingPaymentMethodAddController.url(options),
    method: 'head',
})

export default BillingPaymentMethodAddController