import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Agents\AgentUpdateSettingsController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentUpdateSettingsController.php:10
* @route '/agents/{agent}/settings/{group}'
*/
export const update = (args: { agent: number | { id: number }, group: string | number } | [agent: number | { id: number }, group: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(args, options),
    method: 'post',
})

update.definition = {
    methods: ["post"],
    url: '/agents/{agent}/settings/{group}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentUpdateSettingsController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentUpdateSettingsController.php:10
* @route '/agents/{agent}/settings/{group}'
*/
update.url = (args: { agent: number | { id: number }, group: string | number } | [agent: number | { id: number }, group: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            agent: args[0],
            group: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agent: typeof args.agent === 'object'
        ? args.agent.id
        : args.agent,
        group: args.group,
    }

    return update.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{group}', parsedArgs.group.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentUpdateSettingsController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentUpdateSettingsController.php:10
* @route '/agents/{agent}/settings/{group}'
*/
update.post = (args: { agent: number | { id: number }, group: string | number } | [agent: number | { id: number }, group: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentDeleteSettingsController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentDeleteSettingsController.php:10
* @route '/agents/{agent}/settings/{group}'
*/
export const deleteMethod = (args: { agent: number | { id: number }, group: string | number } | [agent: number | { id: number }, group: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

deleteMethod.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/settings/{group}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentDeleteSettingsController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentDeleteSettingsController.php:10
* @route '/agents/{agent}/settings/{group}'
*/
deleteMethod.url = (args: { agent: number | { id: number }, group: string | number } | [agent: number | { id: number }, group: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            agent: args[0],
            group: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agent: typeof args.agent === 'object'
        ? args.agent.id
        : args.agent,
        group: args.group,
    }

    return deleteMethod.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{group}', parsedArgs.group.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentDeleteSettingsController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentDeleteSettingsController.php:10
* @route '/agents/{agent}/settings/{group}'
*/
deleteMethod.delete = (args: { agent: number | { id: number }, group: string | number } | [agent: number | { id: number }, group: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

const settings = {
    update: Object.assign(update, update),
    delete: Object.assign(deleteMethod, deleteMethod),
}

export default settings