import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsDeleteController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsDeleteController.php:10
* @route '/agents/{agent}/leads'
*/
const AgentLeadsDeleteController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentLeadsDeleteController.url(args, options),
    method: 'delete',
})

AgentLeadsDeleteController.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/leads',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsDeleteController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsDeleteController.php:10
* @route '/agents/{agent}/leads'
*/
AgentLeadsDeleteController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentLeadsDeleteController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsDeleteController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsDeleteController.php:10
* @route '/agents/{agent}/leads'
*/
AgentLeadsDeleteController.delete = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentLeadsDeleteController.url(args, options),
    method: 'delete',
})

export default AgentLeadsDeleteController