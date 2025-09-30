import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\Checkout\CheckoutExtraMessagesController::__invoke
* @see app/Http/Controllers/Billing/Checkout/CheckoutExtraMessagesController.php:9
* @route '/billing/addons/extra-message-credits'
*/
export const extraMessageCredits = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: extraMessageCredits.url(options),
    method: 'get',
})

extraMessageCredits.definition = {
    methods: ["get","head"],
    url: '/billing/addons/extra-message-credits',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Billing\Checkout\CheckoutExtraMessagesController::__invoke
* @see app/Http/Controllers/Billing/Checkout/CheckoutExtraMessagesController.php:9
* @route '/billing/addons/extra-message-credits'
*/
extraMessageCredits.url = (options?: RouteQueryOptions) => {
    return extraMessageCredits.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Checkout\CheckoutExtraMessagesController::__invoke
* @see app/Http/Controllers/Billing/Checkout/CheckoutExtraMessagesController.php:9
* @route '/billing/addons/extra-message-credits'
*/
extraMessageCredits.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: extraMessageCredits.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Billing\Checkout\CheckoutExtraMessagesController::__invoke
* @see app/Http/Controllers/Billing/Checkout/CheckoutExtraMessagesController.php:9
* @route '/billing/addons/extra-message-credits'
*/
extraMessageCredits.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: extraMessageCredits.url(options),
    method: 'head',
})

const addons = {
    extraMessageCredits: Object.assign(extraMessageCredits, extraMessageCredits),
}

export default addons