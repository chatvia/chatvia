import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\CreateChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/CreateChangelog.php:7
* @route '/admin/changelogs/create'
*/
const CreateChangelog = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreateChangelog.url(options),
    method: 'get',
})

CreateChangelog.definition = {
    methods: ["get","head"],
    url: '/admin/changelogs/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\CreateChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/CreateChangelog.php:7
* @route '/admin/changelogs/create'
*/
CreateChangelog.url = (options?: RouteQueryOptions) => {
    return CreateChangelog.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\CreateChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/CreateChangelog.php:7
* @route '/admin/changelogs/create'
*/
CreateChangelog.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreateChangelog.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\CreateChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/CreateChangelog.php:7
* @route '/admin/changelogs/create'
*/
CreateChangelog.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: CreateChangelog.url(options),
    method: 'head',
})

export default CreateChangelog