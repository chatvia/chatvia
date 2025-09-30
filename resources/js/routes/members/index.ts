import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Members\MembersControllerShow::__invoke
* @see app/Http/Controllers/Members/MembersControllerShow.php:12
* @route '/dashboard/members'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/dashboard/members',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Members\MembersControllerShow::__invoke
* @see app/Http/Controllers/Members/MembersControllerShow.php:12
* @route '/dashboard/members'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Members\MembersControllerShow::__invoke
* @see app/Http/Controllers/Members/MembersControllerShow.php:12
* @route '/dashboard/members'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Members\MembersControllerShow::__invoke
* @see app/Http/Controllers/Members/MembersControllerShow.php:12
* @route '/dashboard/members'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Members\MembersListController::__invoke
* @see app/Http/Controllers/Members/MembersListController.php:10
* @route '/dashboard/members/list'
*/
export const list = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: list.url(options),
    method: 'get',
})

list.definition = {
    methods: ["get","head"],
    url: '/dashboard/members/list',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Members\MembersListController::__invoke
* @see app/Http/Controllers/Members/MembersListController.php:10
* @route '/dashboard/members/list'
*/
list.url = (options?: RouteQueryOptions) => {
    return list.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Members\MembersListController::__invoke
* @see app/Http/Controllers/Members/MembersListController.php:10
* @route '/dashboard/members/list'
*/
list.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: list.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Members\MembersListController::__invoke
* @see app/Http/Controllers/Members/MembersListController.php:10
* @route '/dashboard/members/list'
*/
list.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: list.url(options),
    method: 'head',
})

const members = {
    show: Object.assign(show, show),
    list: Object.assign(list, list),
}

export default members