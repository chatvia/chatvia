import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersInvitationAcceptController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersInvitationAcceptController.php:13
* @route '/agents/{agent}/invitations/{token}/accept'
*/
const AgentMembersInvitationAcceptController = (args: { agent: number | { id: number }, token: string | number } | [agent: number | { id: number }, token: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentMembersInvitationAcceptController.url(args, options),
    method: 'post',
})

AgentMembersInvitationAcceptController.definition = {
    methods: ["post"],
    url: '/agents/{agent}/invitations/{token}/accept',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersInvitationAcceptController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersInvitationAcceptController.php:13
* @route '/agents/{agent}/invitations/{token}/accept'
*/
AgentMembersInvitationAcceptController.url = (args: { agent: number | { id: number }, token: string | number } | [agent: number | { id: number }, token: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            agent: args[0],
            token: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agent: typeof args.agent === 'object'
        ? args.agent.id
        : args.agent,
        token: args.token,
    }

    return AgentMembersInvitationAcceptController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersInvitationAcceptController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersInvitationAcceptController.php:13
* @route '/agents/{agent}/invitations/{token}/accept'
*/
AgentMembersInvitationAcceptController.post = (args: { agent: number | { id: number }, token: string | number } | [agent: number | { id: number }, token: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentMembersInvitationAcceptController.url(args, options),
    method: 'post',
})

export default AgentMembersInvitationAcceptController