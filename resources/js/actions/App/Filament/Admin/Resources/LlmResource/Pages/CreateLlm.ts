import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\CreateLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/CreateLlm.php:7
* @route '/admin/llms/create'
*/
const CreateLlm = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreateLlm.url(options),
    method: 'get',
})

CreateLlm.definition = {
    methods: ["get","head"],
    url: '/admin/llms/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\CreateLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/CreateLlm.php:7
* @route '/admin/llms/create'
*/
CreateLlm.url = (options?: RouteQueryOptions) => {
    return CreateLlm.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\CreateLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/CreateLlm.php:7
* @route '/admin/llms/create'
*/
CreateLlm.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreateLlm.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\CreateLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/CreateLlm.php:7
* @route '/admin/llms/create'
*/
CreateLlm.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: CreateLlm.url(options),
    method: 'head',
})

export default CreateLlm