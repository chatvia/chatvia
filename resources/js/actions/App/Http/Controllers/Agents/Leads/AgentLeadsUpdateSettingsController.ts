import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsUpdateSettingsController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsUpdateSettingsController.php:10
* @route '/agents/{agent}/leads/settings'
*/
const AgentLeadsUpdateSettingsController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: AgentLeadsUpdateSettingsController.url(args, options),
    method: 'patch',
})

AgentLeadsUpdateSettingsController.definition = {
    methods: ["patch"],
    url: '/agents/{agent}/leads/settings',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsUpdateSettingsController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsUpdateSettingsController.php:10
* @route '/agents/{agent}/leads/settings'
*/
AgentLeadsUpdateSettingsController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentLeadsUpdateSettingsController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsUpdateSettingsController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsUpdateSettingsController.php:10
* @route '/agents/{agent}/leads/settings'
*/
AgentLeadsUpdateSettingsController.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: AgentLeadsUpdateSettingsController.url(args, options),
    method: 'patch',
})

export default AgentLeadsUpdateSettingsController