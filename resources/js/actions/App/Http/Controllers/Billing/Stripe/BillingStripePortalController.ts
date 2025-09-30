import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\Stripe\BillingStripePortalController::__invoke
* @see app/Http/Controllers/Billing/Stripe/BillingStripePortalController.php:9
* @route '/billing/portal'
*/
const BillingStripePortalController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BillingStripePortalController.url(options),
    method: 'get',
})

BillingStripePortalController.definition = {
    methods: ["get","head"],
    url: '/billing/portal',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Billing\Stripe\BillingStripePortalController::__invoke
* @see app/Http/Controllers/Billing/Stripe/BillingStripePortalController.php:9
* @route '/billing/portal'
*/
BillingStripePortalController.url = (options?: RouteQueryOptions) => {
    return BillingStripePortalController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Stripe\BillingStripePortalController::__invoke
* @see app/Http/Controllers/Billing/Stripe/BillingStripePortalController.php:9
* @route '/billing/portal'
*/
BillingStripePortalController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BillingStripePortalController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Billing\Stripe\BillingStripePortalController::__invoke
* @see app/Http/Controllers/Billing/Stripe/BillingStripePortalController.php:9
* @route '/billing/portal'
*/
BillingStripePortalController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: BillingStripePortalController.url(options),
    method: 'head',
})

export default BillingStripePortalController