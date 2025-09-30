import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersInvitationAcceptController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersInvitationAcceptController.php:13
* @route '/agents/{agent}/invitations/{token}/accept'
*/
export const accept = (args: { agent: number | { id: number }, token: string | number } | [agent: number | { id: number }, token: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: accept.url(args, options),
    method: 'post',
})

accept.definition = {
    methods: ["post"],
    url: '/agents/{agent}/invitations/{token}/accept',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersInvitationAcceptController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersInvitationAcceptController.php:13
* @route '/agents/{agent}/invitations/{token}/accept'
*/
accept.url = (args: { agent: number | { id: number }, token: string | number } | [agent: number | { id: number }, token: string | number ], options?: RouteQueryOptions) => {
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

    return accept.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersInvitationAcceptController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersInvitationAcceptController.php:13
* @route '/agents/{agent}/invitations/{token}/accept'
*/
accept.post = (args: { agent: number | { id: number }, token: string | number } | [agent: number | { id: number }, token: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: accept.url(args, options),
    method: 'post',
})

const invitations = {
    accept: Object.assign(accept, accept),
}

export default invitations