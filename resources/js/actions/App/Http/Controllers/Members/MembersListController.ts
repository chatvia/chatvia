import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Members\MembersListController::__invoke
* @see app/Http/Controllers/Members/MembersListController.php:10
* @route '/dashboard/members/list'
*/
const MembersListController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: MembersListController.url(options),
    method: 'get',
})

MembersListController.definition = {
    methods: ["get","head"],
    url: '/dashboard/members/list',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Members\MembersListController::__invoke
* @see app/Http/Controllers/Members/MembersListController.php:10
* @route '/dashboard/members/list'
*/
MembersListController.url = (options?: RouteQueryOptions) => {
    return MembersListController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Members\MembersListController::__invoke
* @see app/Http/Controllers/Members/MembersListController.php:10
* @route '/dashboard/members/list'
*/
MembersListController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: MembersListController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Members\MembersListController::__invoke
* @see app/Http/Controllers/Members/MembersListController.php:10
* @route '/dashboard/members/list'
*/
MembersListController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: MembersListController.url(options),
    method: 'head',
})

export default MembersListController