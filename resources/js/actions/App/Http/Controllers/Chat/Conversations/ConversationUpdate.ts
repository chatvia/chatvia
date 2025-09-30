import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationUpdate::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationUpdate.php:10
* @route '/chat/conversations/{conversationId}'
*/
const ConversationUpdate = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: ConversationUpdate.url(args, options),
    method: 'patch',
})

ConversationUpdate.definition = {
    methods: ["patch"],
    url: '/chat/conversations/{conversationId}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationUpdate::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationUpdate.php:10
* @route '/chat/conversations/{conversationId}'
*/
ConversationUpdate.url = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return ConversationUpdate.definition.url
            .replace('{conversationId}', parsedArgs.conversationId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationUpdate::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationUpdate.php:10
* @route '/chat/conversations/{conversationId}'
*/
ConversationUpdate.patch = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: ConversationUpdate.url(args, options),
    method: 'patch',
})

export default ConversationUpdate