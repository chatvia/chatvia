import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationsController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationsController.php:11
* @route '/chat/conversations'
*/
const ConversationsController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ConversationsController.url(options),
    method: 'get',
})

ConversationsController.definition = {
    methods: ["get","head"],
    url: '/chat/conversations',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationsController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationsController.php:11
* @route '/chat/conversations'
*/
ConversationsController.url = (options?: RouteQueryOptions) => {
    return ConversationsController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationsController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationsController.php:11
* @route '/chat/conversations'
*/
ConversationsController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ConversationsController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationsController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationsController.php:11
* @route '/chat/conversations'
*/
ConversationsController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ConversationsController.url(options),
    method: 'head',
})

export default ConversationsController