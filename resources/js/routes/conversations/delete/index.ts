import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\ChatDeleteAllConversationsController::__invoke
* @see app/Http/Controllers/Chat/ChatDeleteAllConversationsController.php:11
* @route '/chat/conversations'
*/
export const all = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: all.url(options),
    method: 'delete',
})

all.definition = {
    methods: ["delete"],
    url: '/chat/conversations',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Chat\ChatDeleteAllConversationsController::__invoke
* @see app/Http/Controllers/Chat/ChatDeleteAllConversationsController.php:11
* @route '/chat/conversations'
*/
all.url = (options?: RouteQueryOptions) => {
    return all.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatDeleteAllConversationsController::__invoke
* @see app/Http/Controllers/Chat/ChatDeleteAllConversationsController.php:11
* @route '/chat/conversations'
*/
all.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: all.url(options),
    method: 'delete',
})

const deleteMethod = {
    all: Object.assign(all, all),
}

export default deleteMethod