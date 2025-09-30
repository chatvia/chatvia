import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersListController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersListController.php:11
* @route '/agents/{agent}/team/list'
*/
const AgentMembersListController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentMembersListController.url(args, options),
    method: 'get',
})

AgentMembersListController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/team/list',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersListController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersListController.php:11
* @route '/agents/{agent}/team/list'
*/
AgentMembersListController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { agent: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { agent: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            agent: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agent: typeof args.agent === 'object'
        ? args.agent.id
        : args.agent,
    }

    return AgentMembersListController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersListController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersListController.php:11
* @route '/agents/{agent}/team/list'
*/
AgentMembersListController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentMembersListController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersListController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersListController.php:11
* @route '/agents/{agent}/team/list'
*/
AgentMembersListController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentMembersListController.url(args, options),
    method: 'head',
})

export default AgentMembersListController