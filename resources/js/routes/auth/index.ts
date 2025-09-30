import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import google723582 from './google'
import microsoftFc2e3b from './microsoft'
/**
* @see \App\Http\Controllers\Auth\Social\GoogleAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/GoogleAuthController.php:10
* @route '/auth/google'
*/
export const google = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: google.url(options),
    method: 'get',
})

google.definition = {
    methods: ["get","head"],
    url: '/auth/google',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\Social\GoogleAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/GoogleAuthController.php:10
* @route '/auth/google'
*/
google.url = (options?: RouteQueryOptions) => {
    return google.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\Social\GoogleAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/GoogleAuthController.php:10
* @route '/auth/google'
*/
google.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: google.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\Social\GoogleAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/GoogleAuthController.php:10
* @route '/auth/google'
*/
google.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: google.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\Social\MicrosoftAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/MicrosoftAuthController.php:10
* @route '/auth/microsoft'
*/
export const microsoft = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: microsoft.url(options),
    method: 'get',
})

microsoft.definition = {
    methods: ["get","head"],
    url: '/auth/microsoft',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\Social\MicrosoftAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/MicrosoftAuthController.php:10
* @route '/auth/microsoft'
*/
microsoft.url = (options?: RouteQueryOptions) => {
    return microsoft.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\Social\MicrosoftAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/MicrosoftAuthController.php:10
* @route '/auth/microsoft'
*/
microsoft.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: microsoft.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\Social\MicrosoftAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/MicrosoftAuthController.php:10
* @route '/auth/microsoft'
*/
microsoft.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: microsoft.url(options),
    method: 'head',
})

const auth = {
    google: Object.assign(google, google723582),
    microsoft: Object.assign(microsoft, microsoftFc2e3b),
}

export default auth