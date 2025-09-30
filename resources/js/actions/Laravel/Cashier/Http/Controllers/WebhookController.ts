import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \Laravel\Cashier\Http\Controllers\WebhookController::handleWebhook
* @see vendor/laravel/cashier/src/Http/Controllers/WebhookController.php:40
* @route '/stripe/webhook'
*/
export const handleWebhook = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: handleWebhook.url(options),
    method: 'post',
})

handleWebhook.definition = {
    methods: ["post"],
    url: '/stripe/webhook',
} satisfies RouteDefinition<["post"]>

/**
* @see \Laravel\Cashier\Http\Controllers\WebhookController::handleWebhook
* @see vendor/laravel/cashier/src/Http/Controllers/WebhookController.php:40
* @route '/stripe/webhook'
*/
handleWebhook.url = (options?: RouteQueryOptions) => {
    return handleWebhook.definition.url + queryParams(options)
}

/**
* @see \Laravel\Cashier\Http\Controllers\WebhookController::handleWebhook
* @see vendor/laravel/cashier/src/Http/Controllers/WebhookController.php:40
* @route '/stripe/webhook'
*/
handleWebhook.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: handleWebhook.url(options),
    method: 'post',
})

const WebhookController = { handleWebhook }

export default WebhookController