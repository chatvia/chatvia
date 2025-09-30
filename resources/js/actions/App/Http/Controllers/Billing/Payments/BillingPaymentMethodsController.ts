import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodsController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodsController.php:11
* @route '/billing/payments'
*/
const BillingPaymentMethodsController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BillingPaymentMethodsController.url(options),
    method: 'get',
})

BillingPaymentMethodsController.definition = {
    methods: ["get","head"],
    url: '/billing/payments',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodsController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodsController.php:11
* @route '/billing/payments'
*/
BillingPaymentMethodsController.url = (options?: RouteQueryOptions) => {
    return BillingPaymentMethodsController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodsController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodsController.php:11
* @route '/billing/payments'
*/
BillingPaymentMethodsController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BillingPaymentMethodsController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodsController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodsController.php:11
* @route '/billing/payments'
*/
BillingPaymentMethodsController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: BillingPaymentMethodsController.url(options),
    method: 'head',
})

export default BillingPaymentMethodsController