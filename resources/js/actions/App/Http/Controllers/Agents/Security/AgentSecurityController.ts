import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Security\AgentSecurityController::__invoke
* @see app/Http/Controllers/Agents/Security/AgentSecurityController.php:11
* @route '/agents/{agent}/security'
*/
const AgentSecurityController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentSecurityController.url(args, options),
    method: 'get',
})

AgentSecurityController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/security',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Security\AgentSecurityController::__invoke
* @see app/Http/Controllers/Agents/Security/AgentSecurityController.php:11
* @route '/agents/{agent}/security'
*/
AgentSecurityController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentSecurityController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Security\AgentSecurityController::__invoke
* @see app/Http/Controllers/Agents/Security/AgentSecurityController.php:11
* @route '/agents/{agent}/security'
*/
AgentSecurityController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentSecurityController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Security\AgentSecurityController::__invoke
* @see app/Http/Controllers/Agents/Security/AgentSecurityController.php:11
* @route '/agents/{agent}/security'
*/
AgentSecurityController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentSecurityController.url(args, options),
    method: 'head',
})

export default AgentSecurityController