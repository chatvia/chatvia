import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Dashboard\DashboardStatsController::__invoke
* @see app/Http/Controllers/Dashboard/DashboardStatsController.php:11
* @route '/dashboard/stats'
*/
const DashboardStatsController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: DashboardStatsController.url(options),
    method: 'get',
})

DashboardStatsController.definition = {
    methods: ["get","head"],
    url: '/dashboard/stats',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dashboard\DashboardStatsController::__invoke
* @see app/Http/Controllers/Dashboard/DashboardStatsController.php:11
* @route '/dashboard/stats'
*/
DashboardStatsController.url = (options?: RouteQueryOptions) => {
    return DashboardStatsController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\DashboardStatsController::__invoke
* @see app/Http/Controllers/Dashboard/DashboardStatsController.php:11
* @route '/dashboard/stats'
*/
DashboardStatsController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: DashboardStatsController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dashboard\DashboardStatsController::__invoke
* @see app/Http/Controllers/Dashboard/DashboardStatsController.php:11
* @route '/dashboard/stats'
*/
DashboardStatsController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: DashboardStatsController.url(options),
    method: 'head',
})

export default DashboardStatsController