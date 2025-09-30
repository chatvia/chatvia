import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Agents\AgentRenameController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentRenameController.php:11
* @route '/agents/{agent}/rename'
*/
const AgentRenameController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: AgentRenameController.url(args, options),
    method: 'patch',
})

AgentRenameController.definition = {
    methods: ["patch"],
    url: '/agents/{agent}/rename',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentRenameController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentRenameController.php:11
* @route '/agents/{agent}/rename'
*/
AgentRenameController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentRenameController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentRenameController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentRenameController.php:11
* @route '/agents/{agent}/rename'
*/
AgentRenameController.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: AgentRenameController.url(args, options),
    method: 'patch',
})

export default AgentRenameController