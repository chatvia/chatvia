import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\EditChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/EditChangelog.php:7
* @route '/admin/changelogs/{record}/edit'
*/
const EditChangelog = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditChangelog.url(args, options),
    method: 'get',
})

EditChangelog.definition = {
    methods: ["get","head"],
    url: '/admin/changelogs/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\EditChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/EditChangelog.php:7
* @route '/admin/changelogs/{record}/edit'
*/
EditChangelog.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return EditChangelog.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\EditChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/EditChangelog.php:7
* @route '/admin/changelogs/{record}/edit'
*/
EditChangelog.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditChangelog.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\EditChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/EditChangelog.php:7
* @route '/admin/changelogs/{record}/edit'
*/
EditChangelog.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: EditChangelog.url(args, options),
    method: 'head',
})

export default EditChangelog