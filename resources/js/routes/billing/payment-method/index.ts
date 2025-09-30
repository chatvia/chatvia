import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodDeleteController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodDeleteController.php:9
* @route '/billing/payments'
*/
export const remove = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: remove.url(options),
    method: 'delete',
})

remove.definition = {
    methods: ["delete"],
    url: '/billing/payments',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodDeleteController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodDeleteController.php:9
* @route '/billing/payments'
*/
remove.url = (options?: RouteQueryOptions) => {
    return remove.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodDeleteController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodDeleteController.php:9
* @route '/billing/payments'
*/
remove.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: remove.url(options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodDefaultController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodDefaultController.php:9
* @route '/billing/payments/default'
*/
export const defaultMethod = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: defaultMethod.url(options),
    method: 'post',
})

defaultMethod.definition = {
    methods: ["post"],
    url: '/billing/payments/default',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodDefaultController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodDefaultController.php:9
* @route '/billing/payments/default'
*/
defaultMethod.url = (options?: RouteQueryOptions) => {
    return defaultMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodDefaultController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodDefaultController.php:9
* @route '/billing/payments/default'
*/
defaultMethod.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: defaultMethod.url(options),
    method: 'post',
})

const paymentMethod = {
    remove: Object.assign(remove, remove),
    default: Object.assign(defaultMethod, defaultMethod),
}

export default paymentMethod