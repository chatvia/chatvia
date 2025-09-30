import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\Auth\ChatMemberAgentsController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatMemberAgentsController.php:9
* @route '/chat/agents'
*/
const ChatMemberAgentsController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatMemberAgentsController.url(options),
    method: 'get',
})

ChatMemberAgentsController.definition = {
    methods: ["get","head"],
    url: '/chat/agents',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\Auth\ChatMemberAgentsController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatMemberAgentsController.php:9
* @route '/chat/agents'
*/
ChatMemberAgentsController.url = (options?: RouteQueryOptions) => {
    return ChatMemberAgentsController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Auth\ChatMemberAgentsController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatMemberAgentsController.php:9
* @route '/chat/agents'
*/
ChatMemberAgentsController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatMemberAgentsController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\Auth\ChatMemberAgentsController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatMemberAgentsController.php:9
* @route '/chat/agents'
*/
ChatMemberAgentsController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ChatMemberAgentsController.url(options),
    method: 'head',
})

export default ChatMemberAgentsController