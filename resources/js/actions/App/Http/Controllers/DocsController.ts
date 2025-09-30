import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\DocsController::__invoke
* @see app/Http/Controllers/DocsController.php:12
* @route '/docs'
*/
const DocsController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: DocsController.url(options),
    method: 'get',
})

DocsController.definition = {
    methods: ["get","head"],
    url: '/docs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DocsController::__invoke
* @see app/Http/Controllers/DocsController.php:12
* @route '/docs'
*/
DocsController.url = (options?: RouteQueryOptions) => {
    return DocsController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DocsController::__invoke
* @see app/Http/Controllers/DocsController.php:12
* @route '/docs'
*/
DocsController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: DocsController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DocsController::__invoke
* @see app/Http/Controllers/DocsController.php:12
* @route '/docs'
*/
DocsController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: DocsController.url(options),
    method: 'head',
})

export default DocsController