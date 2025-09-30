import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \Filament\Actions\Exports\Http\Controllers\DownloadExport::__invoke
* @see vendor/filament/actions/src/Exports/Http/Controllers/DownloadExport.php:16
* @route '/filament/exports/{export}/download'
*/
export const download = (args: { export: string | number | { id: string | number } } | [exportParam: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/filament/exports/{export}/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Filament\Actions\Exports\Http\Controllers\DownloadExport::__invoke
* @see vendor/filament/actions/src/Exports/Http/Controllers/DownloadExport.php:16
* @route '/filament/exports/{export}/download'
*/
download.url = (args: { export: string | number | { id: string | number } } | [exportParam: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { export: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { export: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            export: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        export: typeof args.export === 'object'
        ? args.export.id
        : args.export,
    }

    return download.definition.url
            .replace('{export}', parsedArgs.export.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Filament\Actions\Exports\Http\Controllers\DownloadExport::__invoke
* @see vendor/filament/actions/src/Exports/Http/Controllers/DownloadExport.php:16
* @route '/filament/exports/{export}/download'
*/
download.get = (args: { export: string | number | { id: string | number } } | [exportParam: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

/**
* @see \Filament\Actions\Exports\Http\Controllers\DownloadExport::__invoke
* @see vendor/filament/actions/src/Exports/Http/Controllers/DownloadExport.php:16
* @route '/filament/exports/{export}/download'
*/
download.head = (args: { export: string | number | { id: string | number } } | [exportParam: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

const exports = {
    download: Object.assign(download, download),
}

export default exports