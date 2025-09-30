import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\ChatModelsListController::__invoke
* @see app/Http/Controllers/Chat/ChatModelsListController.php:9
* @route '/chat/models'
*/
const ChatModelsListController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatModelsListController.url(options),
    method: 'get',
})

ChatModelsListController.definition = {
    methods: ["get","head"],
    url: '/chat/models',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatModelsListController::__invoke
* @see app/Http/Controllers/Chat/ChatModelsListController.php:9
* @route '/chat/models'
*/
ChatModelsListController.url = (options?: RouteQueryOptions) => {
    return ChatModelsListController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatModelsListController::__invoke
* @see app/Http/Controllers/Chat/ChatModelsListController.php:9
* @route '/chat/models'
*/
ChatModelsListController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatModelsListController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatModelsListController::__invoke
* @see app/Http/Controllers/Chat/ChatModelsListController.php:9
* @route '/chat/models'
*/
ChatModelsListController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ChatModelsListController.url(options),
    method: 'head',
})

export default ChatModelsListController