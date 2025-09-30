import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\UsageController::__invoke
* @see app/Http/Controllers/UsageController.php:12
* @route '/dashboard/usage'
*/
const UsageController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: UsageController.url(options),
    method: 'get',
})

UsageController.definition = {
    methods: ["get","head"],
    url: '/dashboard/usage',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\UsageController::__invoke
* @see app/Http/Controllers/UsageController.php:12
* @route '/dashboard/usage'
*/
UsageController.url = (options?: RouteQueryOptions) => {
    return UsageController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\UsageController::__invoke
* @see app/Http/Controllers/UsageController.php:12
* @route '/dashboard/usage'
*/
UsageController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: UsageController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\UsageController::__invoke
* @see app/Http/Controllers/UsageController.php:12
* @route '/dashboard/usage'
*/
UsageController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: UsageController.url(options),
    method: 'head',
})

export default UsageController