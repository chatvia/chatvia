import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\ListBlogs::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/ListBlogs.php:7
* @route '/admin/blogs'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/blogs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\ListBlogs::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/ListBlogs.php:7
* @route '/admin/blogs'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\ListBlogs::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/ListBlogs.php:7
* @route '/admin/blogs'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\ListBlogs::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/ListBlogs.php:7
* @route '/admin/blogs'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\CreateBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/CreateBlog.php:7
* @route '/admin/blogs/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/blogs/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\CreateBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/CreateBlog.php:7
* @route '/admin/blogs/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\CreateBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/CreateBlog.php:7
* @route '/admin/blogs/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\CreateBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/CreateBlog.php:7
* @route '/admin/blogs/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\ViewBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/ViewBlog.php:7
* @route '/admin/blogs/{record}'
*/
export const view = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: view.url(args, options),
    method: 'get',
})

view.definition = {
    methods: ["get","head"],
    url: '/admin/blogs/{record}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\ViewBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/ViewBlog.php:7
* @route '/admin/blogs/{record}'
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
* @see \App\Filament\Admin\Resources\BlogResource\Pages\ViewBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/ViewBlog.php:7
* @route '/admin/blogs/{record}'
*/
view.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: view.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\ViewBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/ViewBlog.php:7
* @route '/admin/blogs/{record}'
*/
view.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: view.url(args, options),
    method: 'head',
})

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\EditBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/EditBlog.php:7
* @route '/admin/blogs/{record}/edit'
*/
export const edit = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/blogs/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\EditBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/EditBlog.php:7
* @route '/admin/blogs/{record}/edit'
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
* @see \App\Filament\Admin\Resources\BlogResource\Pages\EditBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/EditBlog.php:7
* @route '/admin/blogs/{record}/edit'
*/
edit.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\EditBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/EditBlog.php:7
* @route '/admin/blogs/{record}/edit'
*/
edit.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

const blogs = {
    index: Object.assign(index, index),
    create: Object.assign(create, create),
    view: Object.assign(view, view),
    edit: Object.assign(edit, edit),
}

export default blogs