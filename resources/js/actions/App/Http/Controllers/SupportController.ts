import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\SupportController::__invoke
* @see app/Http/Controllers/SupportController.php:10
* @route '/dashboard/support'
*/
const SupportController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: SupportController.url(options),
    method: 'get',
})

SupportController.definition = {
    methods: ["get","head"],
    url: '/dashboard/support',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SupportController::__invoke
* @see app/Http/Controllers/SupportController.php:10
* @route '/dashboard/support'
*/
SupportController.url = (options?: RouteQueryOptions) => {
    return SupportController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SupportController::__invoke
* @see app/Http/Controllers/SupportController.php:10
* @route '/dashboard/support'
*/
SupportController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: SupportController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SupportController::__invoke
* @see app/Http/Controllers/SupportController.php:10
* @route '/dashboard/support'
*/
SupportController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: SupportController.url(options),
    method: 'head',
})

export default SupportController