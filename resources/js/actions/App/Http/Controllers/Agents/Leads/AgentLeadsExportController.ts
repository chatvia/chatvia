import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsExportController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsExportController.php:11
* @route '/agents/{agent}/leads/export'
*/
const AgentLeadsExportController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentLeadsExportController.url(args, options),
    method: 'post',
})

AgentLeadsExportController.definition = {
    methods: ["post"],
    url: '/agents/{agent}/leads/export',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsExportController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsExportController.php:11
* @route '/agents/{agent}/leads/export'
*/
AgentLeadsExportController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentLeadsExportController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsExportController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsExportController.php:11
* @route '/agents/{agent}/leads/export'
*/
AgentLeadsExportController.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentLeadsExportController.url(args, options),
    method: 'post',
})

export default AgentLeadsExportController