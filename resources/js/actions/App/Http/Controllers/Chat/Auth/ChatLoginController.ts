import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\Auth\ChatLoginController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatLoginController.php:10
* @route '/chat/login'
*/
const ChatLoginController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ChatLoginController.url(options),
    method: 'post',
})

ChatLoginController.definition = {
    methods: ["post"],
    url: '/chat/login',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Chat\Auth\ChatLoginController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatLoginController.php:10
* @route '/chat/login'
*/
ChatLoginController.url = (options?: RouteQueryOptions) => {
    return ChatLoginController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Auth\ChatLoginController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatLoginController.php:10
* @route '/chat/login'
*/
ChatLoginController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ChatLoginController.url(options),
    method: 'post',
})

export default ChatLoginController