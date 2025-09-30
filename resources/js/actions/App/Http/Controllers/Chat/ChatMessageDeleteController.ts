import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\ChatMessageDeleteController::__invoke
* @see app/Http/Controllers/Chat/ChatMessageDeleteController.php:11
* @route '/chat/messages'
*/
const ChatMessageDeleteController = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: ChatMessageDeleteController.url(options),
    method: 'delete',
})

ChatMessageDeleteController.definition = {
    methods: ["delete"],
    url: '/chat/messages',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Chat\ChatMessageDeleteController::__invoke
* @see app/Http/Controllers/Chat/ChatMessageDeleteController.php:11
* @route '/chat/messages'
*/
ChatMessageDeleteController.url = (options?: RouteQueryOptions) => {
    return ChatMessageDeleteController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatMessageDeleteController::__invoke
* @see app/Http/Controllers/Chat/ChatMessageDeleteController.php:11
* @route '/chat/messages'
*/
ChatMessageDeleteController.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: ChatMessageDeleteController.url(options),
    method: 'delete',
})

export default ChatMessageDeleteController