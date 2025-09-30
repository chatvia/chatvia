import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\Checkout\CheckoutExtraMessagesController::__invoke
* @see app/Http/Controllers/Billing/Checkout/CheckoutExtraMessagesController.php:9
* @route '/billing/addons/extra-message-credits'
*/
const CheckoutExtraMessagesController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CheckoutExtraMessagesController.url(options),
    method: 'get',
})

CheckoutExtraMessagesController.definition = {
    methods: ["get","head"],
    url: '/billing/addons/extra-message-credits',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Billing\Checkout\CheckoutExtraMessagesController::__invoke
* @see app/Http/Controllers/Billing/Checkout/CheckoutExtraMessagesController.php:9
* @route '/billing/addons/extra-message-credits'
*/
CheckoutExtraMessagesController.url = (options?: RouteQueryOptions) => {
    return CheckoutExtraMessagesController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Checkout\CheckoutExtraMessagesController::__invoke
* @see app/Http/Controllers/Billing/Checkout/CheckoutExtraMessagesController.php:9
* @route '/billing/addons/extra-message-credits'
*/
CheckoutExtraMessagesController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CheckoutExtraMessagesController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Billing\Checkout\CheckoutExtraMessagesController::__invoke
* @see app/Http/Controllers/Billing/Checkout/CheckoutExtraMessagesController.php:9
* @route '/billing/addons/extra-message-credits'
*/
CheckoutExtraMessagesController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: CheckoutExtraMessagesController.url(options),
    method: 'head',
})

export default CheckoutExtraMessagesController