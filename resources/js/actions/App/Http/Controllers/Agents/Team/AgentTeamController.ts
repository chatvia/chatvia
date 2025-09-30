import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Team\AgentTeamController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentTeamController.php:12
* @route '/agents/{agent}/team'
*/
const AgentTeamController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentTeamController.url(args, options),
    method: 'get',
})

AgentTeamController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/team',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Team\AgentTeamController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentTeamController.php:12
* @route '/agents/{agent}/team'
*/
AgentTeamController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentTeamController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Team\AgentTeamController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentTeamController.php:12
* @route '/agents/{agent}/team'
*/
AgentTeamController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentTeamController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Team\AgentTeamController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentTeamController.php:12
* @route '/agents/{agent}/team'
*/
AgentTeamController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentTeamController.url(args, options),
    method: 'head',
})

export default AgentTeamController