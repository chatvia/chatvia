import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\ListBlogs::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/ListBlogs.php:7
* @route '/admin/blogs'
*/
const ListBlogs = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListBlogs.url(options),
    method: 'get',
})

ListBlogs.definition = {
    methods: ["get","head"],
    url: '/admin/blogs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\ListBlogs::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/ListBlogs.php:7
* @route '/admin/blogs'
*/
ListBlogs.url = (options?: RouteQueryOptions) => {
    return ListBlogs.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\ListBlogs::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/ListBlogs.php:7
* @route '/admin/blogs'
*/
ListBlogs.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListBlogs.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\ListBlogs::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/ListBlogs.php:7
* @route '/admin/blogs'
*/
ListBlogs.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ListBlogs.url(options),
    method: 'head',
})

export default ListBlogs