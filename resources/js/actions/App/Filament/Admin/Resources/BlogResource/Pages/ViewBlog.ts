import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\ViewBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/ViewBlog.php:7
* @route '/admin/blogs/{record}'
*/
const ViewBlog = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ViewBlog.url(args, options),
    method: 'get',
})

ViewBlog.definition = {
    methods: ["get","head"],
    url: '/admin/blogs/{record}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\ViewBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/ViewBlog.php:7
* @route '/admin/blogs/{record}'
*/
ViewBlog.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return ViewBlog.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\ViewBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/ViewBlog.php:7
* @route '/admin/blogs/{record}'
*/
ViewBlog.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ViewBlog.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\ViewBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/ViewBlog.php:7
* @route '/admin/blogs/{record}'
*/
ViewBlog.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ViewBlog.url(args, options),
    method: 'head',
})

export default ViewBlog