import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersDeleteController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersDeleteController.php:10
* @route '/agents/{agent}/team/member'
*/
const AgentMembersDeleteController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentMembersDeleteController.url(args, options),
    method: 'delete',
})

AgentMembersDeleteController.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/team/member',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersDeleteController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersDeleteController.php:10
* @route '/agents/{agent}/team/member'
*/
AgentMembersDeleteController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentMembersDeleteController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersDeleteController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersDeleteController.php:10
* @route '/agents/{agent}/team/member'
*/
AgentMembersDeleteController.delete = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentMembersDeleteController.url(args, options),
    method: 'delete',
})

export default AgentMembersDeleteController