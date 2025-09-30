import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationMessagesController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationMessagesController.php:11
* @route '/chat/conversations/{conversationId}/messages'
*/
const ConversationMessagesController = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ConversationMessagesController.url(args, options),
    method: 'get',
})

ConversationMessagesController.definition = {
    methods: ["get","head"],
    url: '/chat/conversations/{conversationId}/messages',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationMessagesController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationMessagesController.php:11
* @route '/chat/conversations/{conversationId}/messages'
*/
ConversationMessagesController.url = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return ConversationMessagesController.definition.url
            .replace('{conversationId}', parsedArgs.conversationId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationMessagesController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationMessagesController.php:11
* @route '/chat/conversations/{conversationId}/messages'
*/
ConversationMessagesController.get = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ConversationMessagesController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationMessagesController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationMessagesController.php:11
* @route '/chat/conversations/{conversationId}/messages'
*/
ConversationMessagesController.head = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ConversationMessagesController.url(args, options),
    method: 'head',
})

export default ConversationMessagesController