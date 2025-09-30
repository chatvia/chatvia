import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\ChatStreamController::__invoke
* @see app/Http/Controllers/Chat/ChatStreamController.php:17
* @route '/chat/stream/{conversationId}'
*/
const ChatStreamController = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ChatStreamController.url(args, options),
    method: 'post',
})

ChatStreamController.definition = {
    methods: ["post"],
    url: '/chat/stream/{conversationId}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Chat\ChatStreamController::__invoke
* @see app/Http/Controllers/Chat/ChatStreamController.php:17
* @route '/chat/stream/{conversationId}'
*/
ChatStreamController.url = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { conversationId: args }
    }

    if (Array.isArray(args)) {
        args = {
            conversationId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        conversationId: args.conversationId,
    }

    return ChatStreamController.definition.url
            .replace('{conversationId}', parsedArgs.conversationId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatStreamController::__invoke
* @see app/Http/Controllers/Chat/ChatStreamController.php:17
* @route '/chat/stream/{conversationId}'
*/
ChatStreamController.post = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ChatStreamController.url(args, options),
    method: 'post',
})

export default ChatStreamController