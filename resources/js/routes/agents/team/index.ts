import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
import member from './member'
/**
* @see \App\Http\Controllers\Agents\Team\AgentMemberInvitationSendController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMemberInvitationSendController.php:13
* @route '/agents/{agent}/team/invite'
*/
export const invite = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: invite.url(args, options),
    method: 'post',
})

invite.definition = {
    methods: ["post"],
    url: '/agents/{agent}/team/invite',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Team\AgentMemberInvitationSendController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMemberInvitationSendController.php:13
* @route '/agents/{agent}/team/invite'
*/
invite.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return invite.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Team\AgentMemberInvitationSendController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMemberInvitationSendController.php:13
* @route '/agents/{agent}/team/invite'
*/
invite.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: invite.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersListController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersListController.php:11
* @route '/agents/{agent}/team/list'
*/
export const list = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: list.url(args, options),
    method: 'get',
})

list.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/team/list',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersListController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersListController.php:11
* @route '/agents/{agent}/team/list'
*/
list.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return list.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersListController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersListController.php:11
* @route '/agents/{agent}/team/list'
*/
list.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: list.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersListController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersListController.php:11
* @route '/agents/{agent}/team/list'
*/
list.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: list.url(args, options),
    method: 'head',
})

const team = {
    invite: Object.assign(invite, invite),
    list: Object.assign(list, list),
    member: Object.assign(member, member),
}

export default team