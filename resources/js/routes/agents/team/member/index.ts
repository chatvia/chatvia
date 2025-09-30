import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersDeleteController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersDeleteController.php:10
* @route '/agents/{agent}/team/member'
*/
export const deleteMethod = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

deleteMethod.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/team/member',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersDeleteController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersDeleteController.php:10
* @route '/agents/{agent}/team/member'
*/
deleteMethod.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return deleteMethod.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersDeleteController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersDeleteController.php:10
* @route '/agents/{agent}/team/member'
*/
deleteMethod.delete = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersAccessGrantController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersAccessGrantController.php:10
* @route '/agents/{agent}/team/member/grant'
*/
export const grant = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: grant.url(args, options),
    method: 'post',
})

grant.definition = {
    methods: ["post"],
    url: '/agents/{agent}/team/member/grant',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersAccessGrantController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersAccessGrantController.php:10
* @route '/agents/{agent}/team/member/grant'
*/
grant.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return grant.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersAccessGrantController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersAccessGrantController.php:10
* @route '/agents/{agent}/team/member/grant'
*/
grant.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: grant.url(args, options),
    method: 'post',
})

const member = {
    delete: Object.assign(deleteMethod, deleteMethod),
    grant: Object.assign(grant, grant),
}

export default member