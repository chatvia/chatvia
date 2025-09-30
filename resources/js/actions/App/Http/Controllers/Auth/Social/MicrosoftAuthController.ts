import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\Social\MicrosoftAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/MicrosoftAuthController.php:10
* @route '/auth/microsoft'
*/
const MicrosoftAuthController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: MicrosoftAuthController.url(options),
    method: 'get',
})

MicrosoftAuthController.definition = {
    methods: ["get","head"],
    url: '/auth/microsoft',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\Social\MicrosoftAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/MicrosoftAuthController.php:10
* @route '/auth/microsoft'
*/
MicrosoftAuthController.url = (options?: RouteQueryOptions) => {
    return MicrosoftAuthController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\Social\MicrosoftAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/MicrosoftAuthController.php:10
* @route '/auth/microsoft'
*/
MicrosoftAuthController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: MicrosoftAuthController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\Social\MicrosoftAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/MicrosoftAuthController.php:10
* @route '/auth/microsoft'
*/
MicrosoftAuthController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: MicrosoftAuthController.url(options),
    method: 'head',
})

export default MicrosoftAuthController