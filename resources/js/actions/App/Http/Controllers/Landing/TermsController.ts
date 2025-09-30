import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landing\TermsController::__invoke
* @see app/Http/Controllers/Landing/TermsController.php:9
* @route '/terms-of-service'
*/
const TermsController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: TermsController.url(options),
    method: 'get',
})

TermsController.definition = {
    methods: ["get","head"],
    url: '/terms-of-service',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landing\TermsController::__invoke
* @see app/Http/Controllers/Landing/TermsController.php:9
* @route '/terms-of-service'
*/
TermsController.url = (options?: RouteQueryOptions) => {
    return TermsController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landing\TermsController::__invoke
* @see app/Http/Controllers/Landing/TermsController.php:9
* @route '/terms-of-service'
*/
TermsController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: TermsController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landing\TermsController::__invoke
* @see app/Http/Controllers/Landing/TermsController.php:9
* @route '/terms-of-service'
*/
TermsController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: TermsController.url(options),
    method: 'head',
})

export default TermsController