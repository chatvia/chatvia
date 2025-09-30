import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodDefaultController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodDefaultController.php:9
* @route '/billing/payments/default'
*/
const BillingPaymentMethodDefaultController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: BillingPaymentMethodDefaultController.url(options),
    method: 'post',
})

BillingPaymentMethodDefaultController.definition = {
    methods: ["post"],
    url: '/billing/payments/default',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodDefaultController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodDefaultController.php:9
* @route '/billing/payments/default'
*/
BillingPaymentMethodDefaultController.url = (options?: RouteQueryOptions) => {
    return BillingPaymentMethodDefaultController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodDefaultController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodDefaultController.php:9
* @route '/billing/payments/default'
*/
BillingPaymentMethodDefaultController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: BillingPaymentMethodDefaultController.url(options),
    method: 'post',
})

export default BillingPaymentMethodDefaultController