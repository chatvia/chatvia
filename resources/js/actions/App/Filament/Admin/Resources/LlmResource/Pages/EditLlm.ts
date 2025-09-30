import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\EditLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/EditLlm.php:7
* @route '/admin/llms/{record}/edit'
*/
const EditLlm = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditLlm.url(args, options),
    method: 'get',
})

EditLlm.definition = {
    methods: ["get","head"],
    url: '/admin/llms/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\EditLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/EditLlm.php:7
* @route '/admin/llms/{record}/edit'
*/
EditLlm.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return EditLlm.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\EditLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/EditLlm.php:7
* @route '/admin/llms/{record}/edit'
*/
EditLlm.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditLlm.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\EditLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/EditLlm.php:7
* @route '/admin/llms/{record}/edit'
*/
EditLlm.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: EditLlm.url(args, options),
    method: 'head',
})

export default EditLlm