import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\Auth\ChatProfileUpdateController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatProfileUpdateController.php:13
* @route '/chat/profile'
*/
const ChatProfileUpdateController = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: ChatProfileUpdateController.url(options),
    method: 'patch',
})

ChatProfileUpdateController.definition = {
    methods: ["patch"],
    url: '/chat/profile',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Chat\Auth\ChatProfileUpdateController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatProfileUpdateController.php:13
* @route '/chat/profile'
*/
ChatProfileUpdateController.url = (options?: RouteQueryOptions) => {
    return ChatProfileUpdateController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Auth\ChatProfileUpdateController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatProfileUpdateController.php:13
* @route '/chat/profile'
*/
ChatProfileUpdateController.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: ChatProfileUpdateController.url(options),
    method: 'patch',
})

export default ChatProfileUpdateController