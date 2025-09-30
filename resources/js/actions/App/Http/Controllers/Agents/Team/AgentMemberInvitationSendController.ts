import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Team\AgentMemberInvitationSendController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMemberInvitationSendController.php:13
* @route '/agents/{agent}/team/invite'
*/
const AgentMemberInvitationSendController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentMemberInvitationSendController.url(args, options),
    method: 'post',
})

AgentMemberInvitationSendController.definition = {
    methods: ["post"],
    url: '/agents/{agent}/team/invite',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Team\AgentMemberInvitationSendController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMemberInvitationSendController.php:13
* @route '/agents/{agent}/team/invite'
*/
AgentMemberInvitationSendController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentMemberInvitationSendController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Team\AgentMemberInvitationSendController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMemberInvitationSendController.php:13
* @route '/agents/{agent}/team/invite'
*/
AgentMemberInvitationSendController.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentMemberInvitationSendController.url(args, options),
    method: 'post',
})

export default AgentMemberInvitationSendController