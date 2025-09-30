import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\ListLlms::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/ListLlms.php:7
* @route '/admin/llms'
*/
const ListLlms = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListLlms.url(options),
    method: 'get',
})

ListLlms.definition = {
    methods: ["get","head"],
    url: '/admin/llms',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\ListLlms::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/ListLlms.php:7
* @route '/admin/llms'
*/
ListLlms.url = (options?: RouteQueryOptions) => {
    return ListLlms.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\ListLlms::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/ListLlms.php:7
* @route '/admin/llms'
*/
ListLlms.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListLlms.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\ListLlms::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/ListLlms.php:7
* @route '/admin/llms'
*/
ListLlms.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ListLlms.url(options),
    method: 'head',
})

export default ListLlms