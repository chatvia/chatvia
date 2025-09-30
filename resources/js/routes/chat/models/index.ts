import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\ChatModelsListController::__invoke
* @see app/Http/Controllers/Chat/ChatModelsListController.php:9
* @route '/chat/models'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/chat/models',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatModelsListController::__invoke
* @see app/Http/Controllers/Chat/ChatModelsListController.php:9
* @route '/chat/models'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatModelsListController::__invoke
* @see app/Http/Controllers/Chat/ChatModelsListController.php:9
* @route '/chat/models'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatModelsListController::__invoke
* @see app/Http/Controllers/Chat/ChatModelsListController.php:9
* @route '/chat/models'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

const models = {
    index: Object.assign(index, index),
}

export default models