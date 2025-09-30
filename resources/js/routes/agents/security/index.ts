import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Security\AgentSecurityUpdateController::__invoke
* @see app/Http/Controllers/Agents/Security/AgentSecurityUpdateController.php:10
* @route '/agents/{agent}/security'
*/
export const update = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/agents/{agent}/security',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Agents\Security\AgentSecurityUpdateController::__invoke
* @see app/Http/Controllers/Agents/Security/AgentSecurityUpdateController.php:10
* @route '/agents/{agent}/security'
*/
update.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Security\AgentSecurityUpdateController::__invoke
* @see app/Http/Controllers/Agents/Security/AgentSecurityUpdateController.php:10
* @route '/agents/{agent}/security'
*/
update.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

const security = {
    update: Object.assign(update, update),
}

export default security