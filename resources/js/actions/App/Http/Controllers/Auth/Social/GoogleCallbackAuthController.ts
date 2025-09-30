import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\Social\GoogleCallbackAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/GoogleCallbackAuthController.php:10
* @route '/auth/google/callback'
*/
const GoogleCallbackAuthController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: GoogleCallbackAuthController.url(options),
    method: 'get',
})

GoogleCallbackAuthController.definition = {
    methods: ["get","head"],
    url: '/auth/google/callback',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\Social\GoogleCallbackAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/GoogleCallbackAuthController.php:10
* @route '/auth/google/callback'
*/
GoogleCallbackAuthController.url = (options?: RouteQueryOptions) => {
    return GoogleCallbackAuthController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\Social\GoogleCallbackAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/GoogleCallbackAuthController.php:10
* @route '/auth/google/callback'
*/
GoogleCallbackAuthController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: GoogleCallbackAuthController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\Social\GoogleCallbackAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/GoogleCallbackAuthController.php:10
* @route '/auth/google/callback'
*/
GoogleCallbackAuthController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: GoogleCallbackAuthController.url(options),
    method: 'head',
})

export default GoogleCallbackAuthController