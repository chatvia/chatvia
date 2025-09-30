import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersAccessGrantController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersAccessGrantController.php:10
* @route '/agents/{agent}/team/member/grant'
*/
const AgentMembersAccessGrantController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentMembersAccessGrantController.url(args, options),
    method: 'post',
})

AgentMembersAccessGrantController.definition = {
    methods: ["post"],
    url: '/agents/{agent}/team/member/grant',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersAccessGrantController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersAccessGrantController.php:10
* @route '/agents/{agent}/team/member/grant'
*/
AgentMembersAccessGrantController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentMembersAccessGrantController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersAccessGrantController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersAccessGrantController.php:10
* @route '/agents/{agent}/team/member/grant'
*/
AgentMembersAccessGrantController.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentMembersAccessGrantController.url(args, options),
    method: 'post',
})

export default AgentMembersAccessGrantController