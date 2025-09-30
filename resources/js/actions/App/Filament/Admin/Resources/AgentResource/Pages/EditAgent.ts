import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\EditAgent::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/EditAgent.php:7
* @route '/admin/agents/{record}/edit'
*/
const EditAgent = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditAgent.url(args, options),
    method: 'get',
})

EditAgent.definition = {
    methods: ["get","head"],
    url: '/admin/agents/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\EditAgent::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/EditAgent.php:7
* @route '/admin/agents/{record}/edit'
*/
EditAgent.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return EditAgent.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\EditAgent::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/EditAgent.php:7
* @route '/admin/agents/{record}/edit'
*/
EditAgent.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditAgent.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\EditAgent::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/EditAgent.php:7
* @route '/admin/agents/{record}/edit'
*/
EditAgent.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: EditAgent.url(args, options),
    method: 'head',
})

export default EditAgent