import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\Auth\ChatLogoutController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatLogoutController.php:11
* @route '/chat/logout'
*/
const ChatLogoutController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ChatLogoutController.url(options),
    method: 'post',
})

ChatLogoutController.definition = {
    methods: ["post"],
    url: '/chat/logout',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Chat\Auth\ChatLogoutController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatLogoutController.php:11
* @route '/chat/logout'
*/
ChatLogoutController.url = (options?: RouteQueryOptions) => {
    return ChatLogoutController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Auth\ChatLogoutController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatLogoutController.php:11
* @route '/chat/logout'
*/
ChatLogoutController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ChatLogoutController.url(options),
    method: 'post',
})

export default ChatLogoutController