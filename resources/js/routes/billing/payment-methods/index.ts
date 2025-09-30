import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\BillingPaymentMethodAddController::__invoke
* @see app/Http/Controllers/Billing/BillingPaymentMethodAddController.php:10
* @route '/billing/payments/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/billing/payments/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Billing\BillingPaymentMethodAddController::__invoke
* @see app/Http/Controllers/Billing/BillingPaymentMethodAddController.php:10
* @route '/billing/payments/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\BillingPaymentMethodAddController::__invoke
* @see app/Http/Controllers/Billing/BillingPaymentMethodAddController.php:10
* @route '/billing/payments/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Billing\BillingPaymentMethodAddController::__invoke
* @see app/Http/Controllers/Billing/BillingPaymentMethodAddController.php:10
* @route '/billing/payments/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

const paymentMethods = {
    create: Object.assign(create, create),
}

export default paymentMethods