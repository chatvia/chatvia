import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\ListChangelogs::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/ListChangelogs.php:7
* @route '/admin/changelogs'
*/
const ListChangelogs = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListChangelogs.url(options),
    method: 'get',
})

ListChangelogs.definition = {
    methods: ["get","head"],
    url: '/admin/changelogs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\ListChangelogs::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/ListChangelogs.php:7
* @route '/admin/changelogs'
*/
ListChangelogs.url = (options?: RouteQueryOptions) => {
    return ListChangelogs.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\ListChangelogs::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/ListChangelogs.php:7
* @route '/admin/changelogs'
*/
ListChangelogs.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListChangelogs.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\ListChangelogs::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/ListChangelogs.php:7
* @route '/admin/changelogs'
*/
ListChangelogs.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ListChangelogs.url(options),
    method: 'head',
})

export default ListChangelogs