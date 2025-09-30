import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PlansController::__invoke
* @see app/Http/Controllers/PlansController.php:12
* @route '/billing/plans'
*/
const PlansController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PlansController.url(options),
    method: 'get',
})

PlansController.definition = {
    methods: ["get","head"],
    url: '/billing/plans',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PlansController::__invoke
* @see app/Http/Controllers/PlansController.php:12
* @route '/billing/plans'
*/
PlansController.url = (options?: RouteQueryOptions) => {
    return PlansController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlansController::__invoke
* @see app/Http/Controllers/PlansController.php:12
* @route '/billing/plans'
*/
PlansController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PlansController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PlansController::__invoke
* @see app/Http/Controllers/PlansController.php:12
* @route '/billing/plans'
*/
PlansController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: PlansController.url(options),
    method: 'head',
})

export default PlansController