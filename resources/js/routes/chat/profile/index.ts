import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\Auth\ChatProfileUpdateController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatProfileUpdateController.php:13
* @route '/chat/profile'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/chat/profile',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Chat\Auth\ChatProfileUpdateController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatProfileUpdateController.php:13
* @route '/chat/profile'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Auth\ChatProfileUpdateController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatProfileUpdateController.php:13
* @route '/chat/profile'
*/
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

const profile = {
    update: Object.assign(update, update),
}

export default profile