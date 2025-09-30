import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landing\PrivacyController::__invoke
* @see app/Http/Controllers/Landing/PrivacyController.php:9
* @route '/privacy-policy'
*/
const PrivacyController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PrivacyController.url(options),
    method: 'get',
})

PrivacyController.definition = {
    methods: ["get","head"],
    url: '/privacy-policy',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landing\PrivacyController::__invoke
* @see app/Http/Controllers/Landing/PrivacyController.php:9
* @route '/privacy-policy'
*/
PrivacyController.url = (options?: RouteQueryOptions) => {
    return PrivacyController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landing\PrivacyController::__invoke
* @see app/Http/Controllers/Landing/PrivacyController.php:9
* @route '/privacy-policy'
*/
PrivacyController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PrivacyController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landing\PrivacyController::__invoke
* @see app/Http/Controllers/Landing/PrivacyController.php:9
* @route '/privacy-policy'
*/
PrivacyController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: PrivacyController.url(options),
    method: 'head',
})

export default PrivacyController