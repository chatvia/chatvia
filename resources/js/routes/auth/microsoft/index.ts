import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\Social\MicrosoftCallbackAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/MicrosoftCallbackAuthController.php:10
* @route '/auth/microsoft/callback'
*/
export const callback = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callback.url(options),
    method: 'get',
})

callback.definition = {
    methods: ["get","head"],
    url: '/auth/microsoft/callback',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\Social\MicrosoftCallbackAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/MicrosoftCallbackAuthController.php:10
* @route '/auth/microsoft/callback'
*/
callback.url = (options?: RouteQueryOptions) => {
    return callback.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\Social\MicrosoftCallbackAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/MicrosoftCallbackAuthController.php:10
* @route '/auth/microsoft/callback'
*/
callback.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callback.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\Social\MicrosoftCallbackAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/MicrosoftCallbackAuthController.php:10
* @route '/auth/microsoft/callback'
*/
callback.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: callback.url(options),
    method: 'head',
})

const microsoft = {
    callback: Object.assign(callback, callback),
}

export default microsoft