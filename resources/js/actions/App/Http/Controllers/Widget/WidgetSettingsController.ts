import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Widget\WidgetSettingsController::__invoke
* @see app/Http/Controllers/Widget/WidgetSettingsController.php:10
* @route '/api/widget/agents/settings'
*/
const WidgetSettingsController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: WidgetSettingsController.url(options),
    method: 'get',
})

WidgetSettingsController.definition = {
    methods: ["get","head"],
    url: '/api/widget/agents/settings',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Widget\WidgetSettingsController::__invoke
* @see app/Http/Controllers/Widget/WidgetSettingsController.php:10
* @route '/api/widget/agents/settings'
*/
WidgetSettingsController.url = (options?: RouteQueryOptions) => {
    return WidgetSettingsController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetSettingsController::__invoke
* @see app/Http/Controllers/Widget/WidgetSettingsController.php:10
* @route '/api/widget/agents/settings'
*/
WidgetSettingsController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: WidgetSettingsController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Widget\WidgetSettingsController::__invoke
* @see app/Http/Controllers/Widget/WidgetSettingsController.php:10
* @route '/api/widget/agents/settings'
*/
WidgetSettingsController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: WidgetSettingsController.url(options),
    method: 'head',
})

export default WidgetSettingsController