import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsController.php:11
* @route '/agents/{agent}/leads'
*/
const AgentLeadsController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentLeadsController.url(args, options),
    method: 'get',
})

AgentLeadsController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/leads',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsController.php:11
* @route '/agents/{agent}/leads'
*/
AgentLeadsController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentLeadsController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsController.php:11
* @route '/agents/{agent}/leads'
*/
AgentLeadsController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentLeadsController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsController.php:11
* @route '/agents/{agent}/leads'
*/
AgentLeadsController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentLeadsController.url(args, options),
    method: 'head',
})

export default AgentLeadsController