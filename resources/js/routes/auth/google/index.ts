import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\Social\GoogleCallbackAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/GoogleCallbackAuthController.php:10
* @route '/auth/google/callback'
*/
export const callback = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callback.url(options),
    method: 'get',
})

callback.definition = {
    methods: ["get","head"],
    url: '/auth/google/callback',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\Social\GoogleCallbackAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/GoogleCallbackAuthController.php:10
* @route '/auth/google/callback'
*/
callback.url = (options?: RouteQueryOptions) => {
    return callback.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\Social\GoogleCallbackAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/GoogleCallbackAuthController.php:10
* @route '/auth/google/callback'
*/
callback.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callback.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\Social\GoogleCallbackAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/GoogleCallbackAuthController.php:10
* @route '/auth/google/callback'
*/
callback.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: callback.url(options),
    method: 'head',
})

const google = {
    callback: Object.assign(callback, callback),
}

export default google