import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\SettingsUsageController::__invoke
* @see app/Http/Controllers/SettingsUsageController.php:10
* @route '/settings/usage'
*/
export const usage = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: usage.url(options),
    method: 'get',
})

usage.definition = {
    methods: ["get","head"],
    url: '/settings/usage',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SettingsUsageController::__invoke
* @see app/Http/Controllers/SettingsUsageController.php:10
* @route '/settings/usage'
*/
usage.url = (options?: RouteQueryOptions) => {
    return usage.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SettingsUsageController::__invoke
* @see app/Http/Controllers/SettingsUsageController.php:10
* @route '/settings/usage'
*/
usage.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: usage.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SettingsUsageController::__invoke
* @see app/Http/Controllers/SettingsUsageController.php:10
* @route '/settings/usage'
*/
usage.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: usage.url(options),
    method: 'head',
})

const settings = {
    usage: Object.assign(usage, usage),
}

export default settings