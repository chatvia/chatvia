import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationCreateController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationCreateController.php:15
* @route '/chat/conversations/create'
*/
const ConversationCreateController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ConversationCreateController.url(options),
    method: 'post',
})

ConversationCreateController.definition = {
    methods: ["post"],
    url: '/chat/conversations/create',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationCreateController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationCreateController.php:15
* @route '/chat/conversations/create'
*/
ConversationCreateController.url = (options?: RouteQueryOptions) => {
    return ConversationCreateController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Conversations\ConversationCreateController::__invoke
* @see app/Http/Controllers/Chat/Conversations/ConversationCreateController.php:15
* @route '/chat/conversations/create'
*/
ConversationCreateController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ConversationCreateController.url(options),
    method: 'post',
})

export default ConversationCreateController