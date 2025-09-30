import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \Laravel\Sanctum\Http\Controllers\CsrfCookieController::csrfCookie
* @see vendor/laravel/sanctum/src/Http/Controllers/CsrfCookieController.php:17
* @route '/sanctum/csrf-cookie'
*/
export const csrfCookie = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: csrfCookie.url(options),
    method: 'get',
})

csrfCookie.definition = {
    methods: ["get","head"],
    url: '/sanctum/csrf-cookie',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Laravel\Sanctum\Http\Controllers\CsrfCookieController::csrfCookie
* @see vendor/laravel/sanctum/src/Http/Controllers/CsrfCookieController.php:17
* @route '/sanctum/csrf-cookie'
*/
csrfCookie.url = (options?: RouteQueryOptions) => {
    return csrfCookie.definition.url + queryParams(options)
}

/**
* @see \Laravel\Sanctum\Http\Controllers\CsrfCookieController::csrfCookie
* @see vendor/laravel/sanctum/src/Http/Controllers/CsrfCookieController.php:17
* @route '/sanctum/csrf-cookie'
*/
csrfCookie.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: csrfCookie.url(options),
    method: 'get',
})

/**
* @see \Laravel\Sanctum\Http\Controllers\CsrfCookieController::csrfCookie
* @see vendor/laravel/sanctum/src/Http/Controllers/CsrfCookieController.php:17
* @route '/sanctum/csrf-cookie'
*/
csrfCookie.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: csrfCookie.url(options),
    method: 'head',
})

const sanctum = {
    csrfCookie: Object.assign(csrfCookie, csrfCookie),
}

export default sanctum