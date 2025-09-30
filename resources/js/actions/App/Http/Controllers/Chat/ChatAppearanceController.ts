import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\ChatAppearanceController::__invoke
* @see app/Http/Controllers/Chat/ChatAppearanceController.php:7
* @route '/chat/appearance'
*/
const ChatAppearanceController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatAppearanceController.url(options),
    method: 'get',
})

ChatAppearanceController.definition = {
    methods: ["get","head"],
    url: '/chat/appearance',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatAppearanceController::__invoke
* @see app/Http/Controllers/Chat/ChatAppearanceController.php:7
* @route '/chat/appearance'
*/
ChatAppearanceController.url = (options?: RouteQueryOptions) => {
    return ChatAppearanceController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatAppearanceController::__invoke
* @see app/Http/Controllers/Chat/ChatAppearanceController.php:7
* @route '/chat/appearance'
*/
ChatAppearanceController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatAppearanceController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatAppearanceController::__invoke
* @see app/Http/Controllers/Chat/ChatAppearanceController.php:7
* @route '/chat/appearance'
*/
ChatAppearanceController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ChatAppearanceController.url(options),
    method: 'head',
})

export default ChatAppearanceController