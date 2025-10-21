import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\ListChangelogs::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/ListChangelogs.php:7
* @route '/admin/changelogs'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/changelogs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\ListChangelogs::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/ListChangelogs.php:7
* @route '/admin/changelogs'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\ListChangelogs::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/ListChangelogs.php:7
* @route '/admin/changelogs'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\ListChangelogs::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/ListChangelogs.php:7
* @route '/admin/changelogs'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\CreateChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/CreateChangelog.php:7
* @route '/admin/changelogs/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/changelogs/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\CreateChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/CreateChangelog.php:7
* @route '/admin/changelogs/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\CreateChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/CreateChangelog.php:7
* @route '/admin/changelogs/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\CreateChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/CreateChangelog.php:7
* @route '/admin/changelogs/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\ViewChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/ViewChangelog.php:7
* @route '/admin/changelogs/{record}'
*/
export const view = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: view.url(args, options),
    method: 'get',
})

view.definition = {
    methods: ["get","head"],
    url: '/admin/changelogs/{record}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\ViewChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/ViewChangelog.php:7
* @route '/admin/changelogs/{record}'
*/
view.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return view.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\ViewChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/ViewChangelog.php:7
* @route '/admin/changelogs/{record}'
*/
view.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: view.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\ViewChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/ViewChangelog.php:7
* @route '/admin/changelogs/{record}'
*/
view.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: view.url(args, options),
    method: 'head',
})

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\EditChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/EditChangelog.php:7
* @route '/admin/changelogs/{record}/edit'
*/
export const edit = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/changelogs/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\EditChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/EditChangelog.php:7
* @route '/admin/changelogs/{record}/edit'
*/
edit.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return edit.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\EditChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/EditChangelog.php:7
* @route '/admin/changelogs/{record}/edit'
*/
edit.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\ChangelogResource\Pages\EditChangelog::__invoke
* @see app/Filament/Admin/Resources/ChangelogResource/Pages/EditChangelog.php:7
* @route '/admin/changelogs/{record}/edit'
*/
edit.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

const changelogs = {
    index: Object.assign(index, index),
    create: Object.assign(create, create),
    view: Object.assign(view, view),
    edit: Object.assign(edit, edit),
}

export default changelogs