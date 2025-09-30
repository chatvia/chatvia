import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Dashboard\DashboardController::__invoke
* @see app/Http/Controllers/Dashboard/DashboardController.php:9
* @route '/dashboard'
*/
const DashboardController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: DashboardController.url(options),
    method: 'get',
})

DashboardController.definition = {
    methods: ["get","head"],
    url: '/dashboard',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dashboard\DashboardController::__invoke
* @see app/Http/Controllers/Dashboard/DashboardController.php:9
* @route '/dashboard'
*/
DashboardController.url = (options?: RouteQueryOptions) => {
    return DashboardController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\DashboardController::__invoke
* @see app/Http/Controllers/Dashboard/DashboardController.php:9
* @route '/dashboard'
*/
DashboardController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: DashboardController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dashboard\DashboardController::__invoke
* @see app/Http/Controllers/Dashboard/DashboardController.php:9
* @route '/dashboard'
*/
DashboardController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: DashboardController.url(options),
    method: 'head',
})

export default DashboardController