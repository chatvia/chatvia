import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\Social\GoogleAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/GoogleAuthController.php:10
* @route '/auth/google'
*/
const GoogleAuthController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: GoogleAuthController.url(options),
    method: 'get',
})

GoogleAuthController.definition = {
    methods: ["get","head"],
    url: '/auth/google',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\Social\GoogleAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/GoogleAuthController.php:10
* @route '/auth/google'
*/
GoogleAuthController.url = (options?: RouteQueryOptions) => {
    return GoogleAuthController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\Social\GoogleAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/GoogleAuthController.php:10
* @route '/auth/google'
*/
GoogleAuthController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: GoogleAuthController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\Social\GoogleAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/GoogleAuthController.php:10
* @route '/auth/google'
*/
GoogleAuthController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: GoogleAuthController.url(options),
    method: 'head',
})

export default GoogleAuthController