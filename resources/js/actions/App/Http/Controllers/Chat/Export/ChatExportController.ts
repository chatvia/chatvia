import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\Export\ChatExportController::__invoke
* @see app/Http/Controllers/Chat/Export/ChatExportController.php:19
* @route '/chat/conversations/{conversationId}/export'
*/
const ChatExportController = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatExportController.url(args, options),
    method: 'get',
})

ChatExportController.definition = {
    methods: ["get","head"],
    url: '/chat/conversations/{conversationId}/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\Export\ChatExportController::__invoke
* @see app/Http/Controllers/Chat/Export/ChatExportController.php:19
* @route '/chat/conversations/{conversationId}/export'
*/
ChatExportController.url = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return ChatExportController.definition.url
            .replace('{conversationId}', parsedArgs.conversationId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Export\ChatExportController::__invoke
* @see app/Http/Controllers/Chat/Export/ChatExportController.php:19
* @route '/chat/conversations/{conversationId}/export'
*/
ChatExportController.get = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatExportController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\Export\ChatExportController::__invoke
* @see app/Http/Controllers/Chat/Export/ChatExportController.php:19
* @route '/chat/conversations/{conversationId}/export'
*/
ChatExportController.head = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ChatExportController.url(args, options),
    method: 'head',
})

export default ChatExportController