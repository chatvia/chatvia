import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\ViewChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/ViewChangelog.php:7
* @route '/admin/changelogs/{record}'
*/
const ViewChangelog = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ViewChangelog.url(args, options),
    method: 'get',
})

ViewChangelog.definition = {
    methods: ["get","head"],
    url: '/admin/changelogs/{record}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\ViewChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/ViewChangelog.php:7
* @route '/admin/changelogs/{record}'
*/
ViewChangelog.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { record: args }
    }

    if (Array.isArray(args)) {
        args = {
            record: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        record: args.record,
    }

    return ViewChangelog.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\ViewChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/ViewChangelog.php:7
* @route '/admin/changelogs/{record}'
*/
ViewChangelog.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ViewChangelog.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\ViewChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/ViewChangelog.php:7
* @route '/admin/changelogs/{record}'
*/
ViewChangelog.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ViewChangelog.url(args, options),
    method: 'head',
})

export default ViewChangelog