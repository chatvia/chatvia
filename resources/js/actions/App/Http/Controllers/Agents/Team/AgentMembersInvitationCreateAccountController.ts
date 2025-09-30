import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersInvitationCreateAccountController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersInvitationCreateAccountController.php:11
* @route '/agents/{agent}/invitations/{token}'
*/
const AgentMembersInvitationCreateAccountController = (args: { agent: number | { id: number }, token: string | number } | [agent: number | { id: number }, token: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentMembersInvitationCreateAccountController.url(args, options),
    method: 'get',
})

AgentMembersInvitationCreateAccountController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/invitations/{token}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersInvitationCreateAccountController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersInvitationCreateAccountController.php:11
* @route '/agents/{agent}/invitations/{token}'
*/
AgentMembersInvitationCreateAccountController.url = (args: { agent: number | { id: number }, token: string | number } | [agent: number | { id: number }, token: string | number ], options?: RouteQueryOptions) => {
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

    return AgentMembersInvitationCreateAccountController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersInvitationCreateAccountController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersInvitationCreateAccountController.php:11
* @route '/agents/{agent}/invitations/{token}'
*/
AgentMembersInvitationCreateAccountController.get = (args: { agent: number | { id: number }, token: string | number } | [agent: number | { id: number }, token: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentMembersInvitationCreateAccountController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersInvitationCreateAccountController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersInvitationCreateAccountController.php:11
* @route '/agents/{agent}/invitations/{token}'
*/
AgentMembersInvitationCreateAccountController.head = (args: { agent: number | { id: number }, token: string | number } | [agent: number | { id: number }, token: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentMembersInvitationCreateAccountController.url(args, options),
    method: 'head',
})

export default AgentMembersInvitationCreateAccountController