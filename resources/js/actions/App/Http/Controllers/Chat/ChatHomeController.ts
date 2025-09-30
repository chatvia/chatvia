import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\ChatHomeController::__invoke
* @see app/Http/Controllers/Chat/ChatHomeController.php:11
* @route '/chat'
*/
const ChatHomeController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatHomeController.url(options),
    method: 'get',
})

ChatHomeController.definition = {
    methods: ["get","head"],
    url: '/chat',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatHomeController::__invoke
* @see app/Http/Controllers/Chat/ChatHomeController.php:11
* @route '/chat'
*/
ChatHomeController.url = (options?: RouteQueryOptions) => {
    return ChatHomeController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatHomeController::__invoke
* @see app/Http/Controllers/Chat/ChatHomeController.php:11
* @route '/chat'
*/
ChatHomeController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatHomeController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatHomeController::__invoke
* @see app/Http/Controllers/Chat/ChatHomeController.php:11
* @route '/chat'
*/
ChatHomeController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ChatHomeController.url(options),
    method: 'head',
})

export default ChatHomeController