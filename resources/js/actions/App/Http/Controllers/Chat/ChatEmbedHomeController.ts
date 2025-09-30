import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\ChatEmbedHomeController::__invoke
* @see app/Http/Controllers/Chat/ChatEmbedHomeController.php:9
* @route '/chat/embed'
*/
const ChatEmbedHomeController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatEmbedHomeController.url(options),
    method: 'get',
})

ChatEmbedHomeController.definition = {
    methods: ["get","head"],
    url: '/chat/embed',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatEmbedHomeController::__invoke
* @see app/Http/Controllers/Chat/ChatEmbedHomeController.php:9
* @route '/chat/embed'
*/
ChatEmbedHomeController.url = (options?: RouteQueryOptions) => {
    return ChatEmbedHomeController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatEmbedHomeController::__invoke
* @see app/Http/Controllers/Chat/ChatEmbedHomeController.php:9
* @route '/chat/embed'
*/
ChatEmbedHomeController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatEmbedHomeController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatEmbedHomeController::__invoke
* @see app/Http/Controllers/Chat/ChatEmbedHomeController.php:9
* @route '/chat/embed'
*/
ChatEmbedHomeController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ChatEmbedHomeController.url(options),
    method: 'head',
})

export default ChatEmbedHomeController