import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Widget\WidgetThreadsController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsController.php:12
* @route '/api/widget/threads'
*/
const WidgetThreadsController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: WidgetThreadsController.url(options),
    method: 'get',
})

WidgetThreadsController.definition = {
    methods: ["get","head"],
    url: '/api/widget/threads',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Widget\WidgetThreadsController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsController.php:12
* @route '/api/widget/threads'
*/
WidgetThreadsController.url = (options?: RouteQueryOptions) => {
    return WidgetThreadsController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetThreadsController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsController.php:12
* @route '/api/widget/threads'
*/
WidgetThreadsController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: WidgetThreadsController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Widget\WidgetThreadsController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsController.php:12
* @route '/api/widget/threads'
*/
WidgetThreadsController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: WidgetThreadsController.url(options),
    method: 'head',
})

export default WidgetThreadsController