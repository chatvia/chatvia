import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\ChatStartersController::__invoke
* @see app/Http/Controllers/Chat/ChatStartersController.php:10
* @route '/chat/starters'
*/
const ChatStartersController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatStartersController.url(options),
    method: 'get',
})

ChatStartersController.definition = {
    methods: ["get","head"],
    url: '/chat/starters',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatStartersController::__invoke
* @see app/Http/Controllers/Chat/ChatStartersController.php:10
* @route '/chat/starters'
*/
ChatStartersController.url = (options?: RouteQueryOptions) => {
    return ChatStartersController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatStartersController::__invoke
* @see app/Http/Controllers/Chat/ChatStartersController.php:10
* @route '/chat/starters'
*/
ChatStartersController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatStartersController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatStartersController::__invoke
* @see app/Http/Controllers/Chat/ChatStartersController.php:10
* @route '/chat/starters'
*/
ChatStartersController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ChatStartersController.url(options),
    method: 'head',
})

export default ChatStartersController