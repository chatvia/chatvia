import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\EditBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/EditBlog.php:7
* @route '/admin/blogs/{record}/edit'
*/
const EditBlog = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditBlog.url(args, options),
    method: 'get',
})

EditBlog.definition = {
    methods: ["get","head"],
    url: '/admin/blogs/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\EditBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/EditBlog.php:7
* @route '/admin/blogs/{record}/edit'
*/
EditBlog.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return EditBlog.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\EditBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/EditBlog.php:7
* @route '/admin/blogs/{record}/edit'
*/
EditBlog.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditBlog.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\EditBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/EditBlog.php:7
* @route '/admin/blogs/{record}/edit'
*/
EditBlog.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: EditBlog.url(args, options),
    method: 'head',
})

export default EditBlog