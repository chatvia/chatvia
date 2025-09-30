import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Widget\WidgetThreadsStoreController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsStoreController.php:11
* @route '/api/widget/threads'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/widget/threads',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Widget\WidgetThreadsStoreController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsStoreController.php:11
* @route '/api/widget/threads'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetThreadsStoreController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsStoreController.php:11
* @route '/api/widget/threads'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Widget\WidgetThreadsDeleteController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsDeleteController.php:10
* @route '/api/widget/threads'
*/
export const deleteMethod = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(options),
    method: 'delete',
})

deleteMethod.definition = {
    methods: ["delete"],
    url: '/api/widget/threads',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Widget\WidgetThreadsDeleteController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsDeleteController.php:10
* @route '/api/widget/threads'
*/
deleteMethod.url = (options?: RouteQueryOptions) => {
    return deleteMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetThreadsDeleteController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsDeleteController.php:10
* @route '/api/widget/threads'
*/
deleteMethod.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Widget\WidgetThreadMessagesController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadMessagesController.php:10
* @route '/api/widget/threads/messages'
*/
export const messages = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: messages.url(options),
    method: 'get',
})

messages.definition = {
    methods: ["get","head"],
    url: '/api/widget/threads/messages',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Widget\WidgetThreadMessagesController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadMessagesController.php:10
* @route '/api/widget/threads/messages'
*/
messages.url = (options?: RouteQueryOptions) => {
    return messages.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetThreadMessagesController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadMessagesController.php:10
* @route '/api/widget/threads/messages'
*/
messages.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: messages.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Widget\WidgetThreadMessagesController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadMessagesController.php:10
* @route '/api/widget/threads/messages'
*/
messages.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: messages.url(options),
    method: 'head',
})

const threads = {
    store: Object.assign(store, store),
    delete: Object.assign(deleteMethod, deleteMethod),
    messages: Object.assign(messages, messages),
}

export default threads