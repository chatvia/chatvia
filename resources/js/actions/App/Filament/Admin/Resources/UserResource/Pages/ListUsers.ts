import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\UserResource\Pages\ListUsers::__invoke
* @see app/Filament/Admin/Resources/UserResource/Pages/ListUsers.php:7
* @route '/admin/users'
*/
const ListUsers = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListUsers.url(options),
    method: 'get',
})

ListUsers.definition = {
    methods: ["get","head"],
    url: '/admin/users',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\UserResource\Pages\ListUsers::__invoke
* @see app/Filament/Admin/Resources/UserResource/Pages/ListUsers.php:7
* @route '/admin/users'
*/
ListUsers.url = (options?: RouteQueryOptions) => {
    return ListUsers.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\UserResource\Pages\ListUsers::__invoke
* @see app/Filament/Admin/Resources/UserResource/Pages/ListUsers.php:7
* @route '/admin/users'
*/
ListUsers.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListUsers.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\UserResource\Pages\ListUsers::__invoke
* @see app/Filament/Admin/Resources/UserResource/Pages/ListUsers.php:7
* @route '/admin/users'
*/
ListUsers.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ListUsers.url(options),
    method: 'head',
})

export default ListUsers