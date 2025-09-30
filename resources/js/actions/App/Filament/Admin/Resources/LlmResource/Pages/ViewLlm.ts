import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\ViewLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/ViewLlm.php:7
* @route '/admin/llms/{record}'
*/
const ViewLlm = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ViewLlm.url(args, options),
    method: 'get',
})

ViewLlm.definition = {
    methods: ["get","head"],
    url: '/admin/llms/{record}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\ViewLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/ViewLlm.php:7
* @route '/admin/llms/{record}'
*/
ViewLlm.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return ViewLlm.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\ViewLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/ViewLlm.php:7
* @route '/admin/llms/{record}'
*/
ViewLlm.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ViewLlm.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\ViewLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/ViewLlm.php:7
* @route '/admin/llms/{record}'
*/
ViewLlm.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ViewLlm.url(args, options),
    method: 'head',
})

export default ViewLlm