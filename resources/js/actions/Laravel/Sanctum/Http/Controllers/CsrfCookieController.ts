import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \Laravel\Sanctum\Http\Controllers\CsrfCookieController::show
* @see vendor/laravel/sanctum/src/Http/Controllers/CsrfCookieController.php:17
* @route '/sanctum/csrf-cookie'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/sanctum/csrf-cookie',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Laravel\Sanctum\Http\Controllers\CsrfCookieController::show
* @see vendor/laravel/sanctum/src/Http/Controllers/CsrfCookieController.php:17
* @route '/sanctum/csrf-cookie'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \Laravel\Sanctum\Http\Controllers\CsrfCookieController::show
* @see vendor/laravel/sanctum/src/Http/Controllers/CsrfCookieController.php:17
* @route '/sanctum/csrf-cookie'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \Laravel\Sanctum\Http\Controllers\CsrfCookieController::show
* @see vendor/laravel/sanctum/src/Http/Controllers/CsrfCookieController.php:17
* @route '/sanctum/csrf-cookie'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

const CsrfCookieController = { show }

export default CsrfCookieController