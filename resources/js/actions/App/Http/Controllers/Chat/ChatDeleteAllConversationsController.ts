import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\ChatDeleteAllConversationsController::__invoke
* @see app/Http/Controllers/Chat/ChatDeleteAllConversationsController.php:11
* @route '/chat/conversations'
*/
const ChatDeleteAllConversationsController = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: ChatDeleteAllConversationsController.url(options),
    method: 'delete',
})

ChatDeleteAllConversationsController.definition = {
    methods: ["delete"],
    url: '/chat/conversations',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Chat\ChatDeleteAllConversationsController::__invoke
* @see app/Http/Controllers/Chat/ChatDeleteAllConversationsController.php:11
* @route '/chat/conversations'
*/
ChatDeleteAllConversationsController.url = (options?: RouteQueryOptions) => {
    return ChatDeleteAllConversationsController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatDeleteAllConversationsController::__invoke
* @see app/Http/Controllers/Chat/ChatDeleteAllConversationsController.php:11
* @route '/chat/conversations'
*/
ChatDeleteAllConversationsController.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: ChatDeleteAllConversationsController.url(options),
    method: 'delete',
})

export default ChatDeleteAllConversationsController