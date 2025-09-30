import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\Social\MicrosoftCallbackAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/MicrosoftCallbackAuthController.php:10
* @route '/auth/microsoft/callback'
*/
const MicrosoftCallbackAuthController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: MicrosoftCallbackAuthController.url(options),
    method: 'get',
})

MicrosoftCallbackAuthController.definition = {
    methods: ["get","head"],
    url: '/auth/microsoft/callback',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\Social\MicrosoftCallbackAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/MicrosoftCallbackAuthController.php:10
* @route '/auth/microsoft/callback'
*/
MicrosoftCallbackAuthController.url = (options?: RouteQueryOptions) => {
    return MicrosoftCallbackAuthController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\Social\MicrosoftCallbackAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/MicrosoftCallbackAuthController.php:10
* @route '/auth/microsoft/callback'
*/
MicrosoftCallbackAuthController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: MicrosoftCallbackAuthController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\Social\MicrosoftCallbackAuthController::__invoke
* @see app/Http/Controllers/Auth/Social/MicrosoftCallbackAuthController.php:10
* @route '/auth/microsoft/callback'
*/
MicrosoftCallbackAuthController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: MicrosoftCallbackAuthController.url(options),
    method: 'head',
})

export default MicrosoftCallbackAuthController