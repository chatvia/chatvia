import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Members\MembersControllerShow::__invoke
* @see app/Http/Controllers/Members/MembersControllerShow.php:12
* @route '/dashboard/members'
*/
const MembersControllerShow = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: MembersControllerShow.url(options),
    method: 'get',
})

MembersControllerShow.definition = {
    methods: ["get","head"],
    url: '/dashboard/members',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Members\MembersControllerShow::__invoke
* @see app/Http/Controllers/Members/MembersControllerShow.php:12
* @route '/dashboard/members'
*/
MembersControllerShow.url = (options?: RouteQueryOptions) => {
    return MembersControllerShow.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Members\MembersControllerShow::__invoke
* @see app/Http/Controllers/Members/MembersControllerShow.php:12
* @route '/dashboard/members'
*/
MembersControllerShow.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: MembersControllerShow.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Members\MembersControllerShow::__invoke
* @see app/Http/Controllers/Members/MembersControllerShow.php:12
* @route '/dashboard/members'
*/
MembersControllerShow.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: MembersControllerShow.url(options),
    method: 'head',
})

export default MembersControllerShow