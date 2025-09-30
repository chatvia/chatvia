import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\SettingsUsageController::__invoke
* @see app/Http/Controllers/SettingsUsageController.php:10
* @route '/settings/usage'
*/
const SettingsUsageController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: SettingsUsageController.url(options),
    method: 'get',
})

SettingsUsageController.definition = {
    methods: ["get","head"],
    url: '/settings/usage',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SettingsUsageController::__invoke
* @see app/Http/Controllers/SettingsUsageController.php:10
* @route '/settings/usage'
*/
SettingsUsageController.url = (options?: RouteQueryOptions) => {
    return SettingsUsageController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SettingsUsageController::__invoke
* @see app/Http/Controllers/SettingsUsageController.php:10
* @route '/settings/usage'
*/
SettingsUsageController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: SettingsUsageController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SettingsUsageController::__invoke
* @see app/Http/Controllers/SettingsUsageController.php:10
* @route '/settings/usage'
*/
SettingsUsageController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: SettingsUsageController.url(options),
    method: 'head',
})

export default SettingsUsageController