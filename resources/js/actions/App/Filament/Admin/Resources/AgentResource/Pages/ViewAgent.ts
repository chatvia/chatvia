import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\ViewAgent::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/ViewAgent.php:7
* @route '/admin/agents/{record}'
*/
const ViewAgent = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ViewAgent.url(args, options),
    method: 'get',
})

ViewAgent.definition = {
    methods: ["get","head"],
    url: '/admin/agents/{record}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\ViewAgent::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/ViewAgent.php:7
* @route '/admin/agents/{record}'
*/
ViewAgent.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return ViewAgent.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\ViewAgent::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/ViewAgent.php:7
* @route '/admin/agents/{record}'
*/
ViewAgent.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ViewAgent.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\ViewAgent::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/ViewAgent.php:7
* @route '/admin/agents/{record}'
*/
ViewAgent.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ViewAgent.url(args, options),
    method: 'head',
})

export default ViewAgent