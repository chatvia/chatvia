import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\ListAgents::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/ListAgents.php:7
* @route '/admin/agents'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/agents',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\ListAgents::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/ListAgents.php:7
* @route '/admin/agents'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\ListAgents::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/ListAgents.php:7
* @route '/admin/agents'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\ListAgents::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/ListAgents.php:7
* @route '/admin/agents'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\ViewAgent::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/ViewAgent.php:7
* @route '/admin/agents/{record}'
*/
export const view = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: view.url(args, options),
    method: 'get',
})

view.definition = {
    methods: ["get","head"],
    url: '/admin/agents/{record}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\ViewAgent::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/ViewAgent.php:7
* @route '/admin/agents/{record}'
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
* @see \App\Filament\Admin\Resources\AgentResource\Pages\ViewAgent::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/ViewAgent.php:7
* @route '/admin/agents/{record}'
*/
view.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: view.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\ViewAgent::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/ViewAgent.php:7
* @route '/admin/agents/{record}'
*/
view.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: view.url(args, options),
    method: 'head',
})

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\EditAgent::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/EditAgent.php:7
* @route '/admin/agents/{record}/edit'
*/
export const edit = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/agents/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\EditAgent::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/EditAgent.php:7
* @route '/admin/agents/{record}/edit'
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
* @see \App\Filament\Admin\Resources\AgentResource\Pages\EditAgent::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/EditAgent.php:7
* @route '/admin/agents/{record}/edit'
*/
edit.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\EditAgent::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/EditAgent.php:7
* @route '/admin/agents/{record}/edit'
*/
edit.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

const agents = {
    index: Object.assign(index, index),
    view: Object.assign(view, view),
    edit: Object.assign(edit, edit),
}

export default agents