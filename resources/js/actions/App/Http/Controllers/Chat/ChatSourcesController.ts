import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\ChatSourcesController::__invoke
* @see app/Http/Controllers/Chat/ChatSourcesController.php:11
* @route '/chat/conversations/{conversationId}/sources'
*/
const ChatSourcesController = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatSourcesController.url(args, options),
    method: 'get',
})

ChatSourcesController.definition = {
    methods: ["get","head"],
    url: '/chat/conversations/{conversationId}/sources',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatSourcesController::__invoke
* @see app/Http/Controllers/Chat/ChatSourcesController.php:11
* @route '/chat/conversations/{conversationId}/sources'
*/
ChatSourcesController.url = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return ChatSourcesController.definition.url
            .replace('{conversationId}', parsedArgs.conversationId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatSourcesController::__invoke
* @see app/Http/Controllers/Chat/ChatSourcesController.php:11
* @route '/chat/conversations/{conversationId}/sources'
*/
ChatSourcesController.get = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatSourcesController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatSourcesController::__invoke
* @see app/Http/Controllers/Chat/ChatSourcesController.php:11
* @route '/chat/conversations/{conversationId}/sources'
*/
ChatSourcesController.head = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ChatSourcesController.url(args, options),
    method: 'head',
})

export default ChatSourcesController