import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsDeleteController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsDeleteController.php:10
* @route '/agents/{agent}/leads'
*/
export const deleteMethod = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

deleteMethod.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/leads',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsDeleteController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsDeleteController.php:10
* @route '/agents/{agent}/leads'
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
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsDeleteController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsDeleteController.php:10
* @route '/agents/{agent}/leads'
*/
deleteMethod.delete = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsUpdateSettingsController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsUpdateSettingsController.php:10
* @route '/agents/{agent}/leads/settings'
*/
export const settings = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: settings.url(args, options),
    method: 'patch',
})

settings.definition = {
    methods: ["patch"],
    url: '/agents/{agent}/leads/settings',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsUpdateSettingsController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsUpdateSettingsController.php:10
* @route '/agents/{agent}/leads/settings'
*/
settings.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return settings.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsUpdateSettingsController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsUpdateSettingsController.php:10
* @route '/agents/{agent}/leads/settings'
*/
settings.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: settings.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsExportController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsExportController.php:11
* @route '/agents/{agent}/leads/export'
*/
export const exportMethod = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: exportMethod.url(args, options),
    method: 'post',
})

exportMethod.definition = {
    methods: ["post"],
    url: '/agents/{agent}/leads/export',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsExportController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsExportController.php:11
* @route '/agents/{agent}/leads/export'
*/
exportMethod.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return exportMethod.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsExportController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsExportController.php:11
* @route '/agents/{agent}/leads/export'
*/
exportMethod.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: exportMethod.url(args, options),
    method: 'post',
})

const leads = {
    delete: Object.assign(deleteMethod, deleteMethod),
    settings: Object.assign(settings, settings),
    export: Object.assign(exportMethod, exportMethod),
}

export default leads