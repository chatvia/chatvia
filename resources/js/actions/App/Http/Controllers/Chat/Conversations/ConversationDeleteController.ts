import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationDeleteController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationDeleteController.php:11
* @route '/chat/conversations/{conversationId}'
*/
const ConversationDeleteController = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: ConversationDeleteController.url(args, options),
    method: 'delete',
})

ConversationDeleteController.definition = {
    methods: ["delete"],
    url: '/chat/conversations/{conversationId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationDeleteController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationDeleteController.php:11
* @route '/chat/conversations/{conversationId}'
*/
ConversationDeleteController.url = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return ConversationDeleteController.definition.url
            .replace('{conversationId}', parsedArgs.conversationId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationDeleteController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationDeleteController.php:11
* @route '/chat/conversations/{conversationId}'
*/
ConversationDeleteController.delete = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: ConversationDeleteController.url(args, options),
    method: 'delete',
})

export default ConversationDeleteController