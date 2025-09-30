import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\ListAgents::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/ListAgents.php:7
* @route '/admin/agents'
*/
const ListAgents = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListAgents.url(options),
    method: 'get',
})

ListAgents.definition = {
    methods: ["get","head"],
    url: '/admin/agents',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\ListAgents::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/ListAgents.php:7
* @route '/admin/agents'
*/
ListAgents.url = (options?: RouteQueryOptions) => {
    return ListAgents.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\ListAgents::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/ListAgents.php:7
* @route '/admin/agents'
*/
ListAgents.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListAgents.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\AgentResource\Pages\ListAgents::__invoke
* @see app/Filament/Admin/Resources/AgentResource/Pages/ListAgents.php:7
* @route '/admin/agents'
*/
ListAgents.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ListAgents.url(options),
    method: 'head',
})

export default ListAgents