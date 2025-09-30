import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
import deleteMethod57c334 from './delete'
/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationCreateController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationCreateController.php:15
* @route '/chat/conversations/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: create.url(options),
    method: 'post',
})

create.definition = {
    methods: ["post"],
    url: '/chat/conversations/create',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationCreateController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationCreateController.php:15
* @route '/chat/conversations/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationCreateController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationCreateController.php:15
* @route '/chat/conversations/create'
*/
create.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: create.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationMessagesController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationMessagesController.php:11
* @route '/chat/conversations/{conversationId}/messages'
*/
export const messages = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: messages.url(args, options),
    method: 'get',
})

messages.definition = {
    methods: ["get","head"],
    url: '/chat/conversations/{conversationId}/messages',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationMessagesController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationMessagesController.php:11
* @route '/chat/conversations/{conversationId}/messages'
*/
messages.url = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return messages.definition.url
            .replace('{conversationId}', parsedArgs.conversationId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationMessagesController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationMessagesController.php:11
* @route '/chat/conversations/{conversationId}/messages'
*/
messages.get = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: messages.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationMessagesController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationMessagesController.php:11
* @route '/chat/conversations/{conversationId}/messages'
*/
messages.head = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: messages.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationsController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationsController.php:11
* @route '/chat/conversations'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/chat/conversations',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationsController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationsController.php:11
* @route '/chat/conversations'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationsController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationsController.php:11
* @route '/chat/conversations'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationsController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationsController.php:11
* @route '/chat/conversations'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationUpdate::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationUpdate.php:10
* @route '/chat/conversations/{conversationId}'
*/
export const update = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/chat/conversations/{conversationId}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationUpdate::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationUpdate.php:10
* @route '/chat/conversations/{conversationId}'
*/
update.url = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{conversationId}', parsedArgs.conversationId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationUpdate::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationUpdate.php:10
* @route '/chat/conversations/{conversationId}'
*/
update.patch = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationDeleteController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationDeleteController.php:11
* @route '/chat/conversations/{conversationId}'
*/
export const deleteMethod = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

deleteMethod.definition = {
    methods: ["delete"],
    url: '/chat/conversations/{conversationId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationDeleteController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationDeleteController.php:11
* @route '/chat/conversations/{conversationId}'
*/
deleteMethod.url = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return deleteMethod.definition.url
            .replace('{conversationId}', parsedArgs.conversationId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationDeleteController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationDeleteController.php:11
* @route '/chat/conversations/{conversationId}'
*/
deleteMethod.delete = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Chat\Export\ChatExportController::__invoke
* @see app/Http/Controllers/Chat/Export/ChatExportController.php:19
* @route '/chat/conversations/{conversationId}/export'
*/
export const exportMethod = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(args, options),
    method: 'get',
})

exportMethod.definition = {
    methods: ["get","head"],
    url: '/chat/conversations/{conversationId}/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\Export\ChatExportController::__invoke
* @see app/Http/Controllers/Chat/Export/ChatExportController.php:19
* @route '/chat/conversations/{conversationId}/export'
*/
exportMethod.url = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return exportMethod.definition.url
            .replace('{conversationId}', parsedArgs.conversationId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Export\ChatExportController::__invoke
* @see app/Http/Controllers/Chat/Export/ChatExportController.php:19
* @route '/chat/conversations/{conversationId}/export'
*/
exportMethod.get = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\Export\ChatExportController::__invoke
* @see app/Http/Controllers/Chat/Export/ChatExportController.php:19
* @route '/chat/conversations/{conversationId}/export'
*/
exportMethod.head = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportMethod.url(args, options),
    method: 'head',
})

const conversations = {
    create: Object.assign(create, create),
    messages: Object.assign(messages, messages),
    index: Object.assign(index, index),
    update: Object.assign(update, update),
    delete: Object.assign(deleteMethod, deleteMethod57c334),
    export: Object.assign(exportMethod, exportMethod),
}

export default conversations