import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Widget\WidgetThreadsDeleteController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsDeleteController.php:10
* @route '/api/widget/threads'
*/
const WidgetThreadsDeleteController = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: WidgetThreadsDeleteController.url(options),
    method: 'delete',
})

WidgetThreadsDeleteController.definition = {
    methods: ["delete"],
    url: '/api/widget/threads',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Widget\WidgetThreadsDeleteController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsDeleteController.php:10
* @route '/api/widget/threads'
*/
WidgetThreadsDeleteController.url = (options?: RouteQueryOptions) => {
    return WidgetThreadsDeleteController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetThreadsDeleteController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsDeleteController.php:10
* @route '/api/widget/threads'
*/
WidgetThreadsDeleteController.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: WidgetThreadsDeleteController.url(options),
    method: 'delete',
})

export default WidgetThreadsDeleteController