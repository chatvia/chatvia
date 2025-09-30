import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Dashboard\DashboardStatsController::__invoke
* @see app/Http/Controllers/Dashboard/DashboardStatsController.php:11
* @route '/dashboard/stats'
*/
export const stats = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: stats.url(options),
    method: 'get',
})

stats.definition = {
    methods: ["get","head"],
    url: '/dashboard/stats',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dashboard\DashboardStatsController::__invoke
* @see app/Http/Controllers/Dashboard/DashboardStatsController.php:11
* @route '/dashboard/stats'
*/
stats.url = (options?: RouteQueryOptions) => {
    return stats.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\DashboardStatsController::__invoke
* @see app/Http/Controllers/Dashboard/DashboardStatsController.php:11
* @route '/dashboard/stats'
*/
stats.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: stats.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dashboard\DashboardStatsController::__invoke
* @see app/Http/Controllers/Dashboard/DashboardStatsController.php:11
* @route '/dashboard/stats'
*/
stats.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: stats.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\FeaturesController::__invoke
* @see app/Http/Controllers/FeaturesController.php:13
* @route '/dashboard/features'
*/
export const features = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: features.url(options),
    method: 'get',
})

features.definition = {
    methods: ["get","head"],
    url: '/dashboard/features',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\FeaturesController::__invoke
* @see app/Http/Controllers/FeaturesController.php:13
* @route '/dashboard/features'
*/
features.url = (options?: RouteQueryOptions) => {
    return features.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\FeaturesController::__invoke
* @see app/Http/Controllers/FeaturesController.php:13
* @route '/dashboard/features'
*/
features.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: features.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\FeaturesController::__invoke
* @see app/Http/Controllers/FeaturesController.php:13
* @route '/dashboard/features'
*/
features.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: features.url(options),
    method: 'head',
})

const dashboard = {
    stats: Object.assign(stats, stats),
    features: Object.assign(features, features),
}

export default dashboard