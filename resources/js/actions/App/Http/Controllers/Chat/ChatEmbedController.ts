import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\ChatEmbedController::__invoke
* @see app/Http/Controllers/Chat/ChatEmbedController.php:9
* @route '/chat/embed/c/{conversationId}'
*/
const ChatEmbedController = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatEmbedController.url(args, options),
    method: 'get',
})

ChatEmbedController.definition = {
    methods: ["get","head"],
    url: '/chat/embed/c/{conversationId}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatEmbedController::__invoke
* @see app/Http/Controllers/Chat/ChatEmbedController.php:9
* @route '/chat/embed/c/{conversationId}'
*/
ChatEmbedController.url = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return ChatEmbedController.definition.url
            .replace('{conversationId}', parsedArgs.conversationId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatEmbedController::__invoke
* @see app/Http/Controllers/Chat/ChatEmbedController.php:9
* @route '/chat/embed/c/{conversationId}'
*/
ChatEmbedController.get = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatEmbedController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatEmbedController::__invoke
* @see app/Http/Controllers/Chat/ChatEmbedController.php:9
* @route '/chat/embed/c/{conversationId}'
*/
ChatEmbedController.head = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ChatEmbedController.url(args, options),
    method: 'head',
})

export default ChatEmbedController