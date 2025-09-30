import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Widget\WidgetThreadsStoreController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsStoreController.php:11
* @route '/api/widget/threads'
*/
const WidgetThreadsStoreController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: WidgetThreadsStoreController.url(options),
    method: 'post',
})

WidgetThreadsStoreController.definition = {
    methods: ["post"],
    url: '/api/widget/threads',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Widget\WidgetThreadsStoreController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsStoreController.php:11
* @route '/api/widget/threads'
*/
WidgetThreadsStoreController.url = (options?: RouteQueryOptions) => {
    return WidgetThreadsStoreController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetThreadsStoreController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsStoreController.php:11
* @route '/api/widget/threads'
*/
WidgetThreadsStoreController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: WidgetThreadsStoreController.url(options),
    method: 'post',
})

export default WidgetThreadsStoreController