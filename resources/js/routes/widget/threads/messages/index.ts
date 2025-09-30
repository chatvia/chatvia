import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Widget\WidgetThreadMessagesController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadMessagesController.php:10
* @route '/api/widget/threads/messages'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/widget/threads/messages',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Widget\WidgetThreadMessagesController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadMessagesController.php:10
* @route '/api/widget/threads/messages'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetThreadMessagesController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadMessagesController.php:10
* @route '/api/widget/threads/messages'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

