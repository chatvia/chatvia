import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\CreateBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/CreateBlog.php:7
* @route '/admin/blogs/create'
*/
const CreateBlog = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreateBlog.url(options),
    method: 'get',
})

CreateBlog.definition = {
    methods: ["get","head"],
    url: '/admin/blogs/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\CreateBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/CreateBlog.php:7
* @route '/admin/blogs/create'
*/
CreateBlog.url = (options?: RouteQueryOptions) => {
    return CreateBlog.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\CreateBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/CreateBlog.php:7
* @route '/admin/blogs/create'
*/
CreateBlog.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreateBlog.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\BlogResource\Pages\CreateBlog::__invoke
* @see app/Filament/Admin/Resources/BlogResource/Pages/CreateBlog.php:7
* @route '/admin/blogs/create'
*/
CreateBlog.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: CreateBlog.url(options),
    method: 'head',
})

export default CreateBlog