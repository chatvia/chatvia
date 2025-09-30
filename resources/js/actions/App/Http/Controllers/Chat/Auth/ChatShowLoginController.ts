import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\Auth\ChatShowLoginController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatShowLoginController.php:15
* @route '/chat/login'
*/
const ChatShowLoginController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatShowLoginController.url(options),
    method: 'get',
})

ChatShowLoginController.definition = {
    methods: ["get","head"],
    url: '/chat/login',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\Auth\ChatShowLoginController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatShowLoginController.php:15
* @route '/chat/login'
*/
ChatShowLoginController.url = (options?: RouteQueryOptions) => {
    return ChatShowLoginController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Auth\ChatShowLoginController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatShowLoginController.php:15
* @route '/chat/login'
*/
ChatShowLoginController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatShowLoginController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\Auth\ChatShowLoginController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatShowLoginController.php:15
* @route '/chat/login'
*/
ChatShowLoginController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ChatShowLoginController.url(options),
    method: 'head',
})

export default ChatShowLoginController