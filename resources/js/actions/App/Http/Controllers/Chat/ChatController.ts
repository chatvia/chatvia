import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\ChatController::__invoke
* @see app/Http/Controllers/Chat/ChatController.php:10
* @route '/chat/c/{conversationId}'
*/
const ChatController = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatController.url(args, options),
    method: 'get',
})

ChatController.definition = {
    methods: ["get","head"],
    url: '/chat/c/{conversationId}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatController::__invoke
* @see app/Http/Controllers/Chat/ChatController.php:10
* @route '/chat/c/{conversationId}'
*/
ChatController.url = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return ChatController.definition.url
            .replace('{conversationId}', parsedArgs.conversationId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatController::__invoke
* @see app/Http/Controllers/Chat/ChatController.php:10
* @route '/chat/c/{conversationId}'
*/
ChatController.get = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatController::__invoke
* @see app/Http/Controllers/Chat/ChatController.php:10
* @route '/chat/c/{conversationId}'
*/
ChatController.head = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ChatController.url(args, options),
    method: 'head',
})

export default ChatController