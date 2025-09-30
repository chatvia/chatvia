import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodDeleteController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodDeleteController.php:9
* @route '/billing/payments'
*/
const BillingPaymentMethodDeleteController = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: BillingPaymentMethodDeleteController.url(options),
    method: 'delete',
})

BillingPaymentMethodDeleteController.definition = {
    methods: ["delete"],
    url: '/billing/payments',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodDeleteController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodDeleteController.php:9
* @route '/billing/payments'
*/
BillingPaymentMethodDeleteController.url = (options?: RouteQueryOptions) => {
    return BillingPaymentMethodDeleteController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodDeleteController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodDeleteController.php:9
* @route '/billing/payments'
*/
BillingPaymentMethodDeleteController.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: BillingPaymentMethodDeleteController.url(options),
    method: 'delete',
})

export default BillingPaymentMethodDeleteController