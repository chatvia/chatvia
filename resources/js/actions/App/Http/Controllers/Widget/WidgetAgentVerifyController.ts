import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Widget\WidgetAgentVerifyController::__invoke
* @see app/Http/Controllers/Widget/WidgetAgentVerifyController.php:10
* @route '/api/widget/agents/verify'
*/
const WidgetAgentVerifyController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: WidgetAgentVerifyController.url(options),
    method: 'get',
})

WidgetAgentVerifyController.definition = {
    methods: ["get","head"],
    url: '/api/widget/agents/verify',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Widget\WidgetAgentVerifyController::__invoke
* @see app/Http/Controllers/Widget/WidgetAgentVerifyController.php:10
* @route '/api/widget/agents/verify'
*/
WidgetAgentVerifyController.url = (options?: RouteQueryOptions) => {
    return WidgetAgentVerifyController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetAgentVerifyController::__invoke
* @see app/Http/Controllers/Widget/WidgetAgentVerifyController.php:10
* @route '/api/widget/agents/verify'
*/
WidgetAgentVerifyController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: WidgetAgentVerifyController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Widget\WidgetAgentVerifyController::__invoke
* @see app/Http/Controllers/Widget/WidgetAgentVerifyController.php:10
* @route '/api/widget/agents/verify'
*/
WidgetAgentVerifyController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: WidgetAgentVerifyController.url(options),
    method: 'head',
})

export default WidgetAgentVerifyController