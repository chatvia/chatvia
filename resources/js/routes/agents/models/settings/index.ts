import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsStoreSettings::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsStoreSettings.php:10
* @route '/agents/{agent}/models/settings'
*/
export const update = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/agents/{agent}/models/settings',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsStoreSettings::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsStoreSettings.php:10
* @route '/agents/{agent}/models/settings'
*/
update.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsStoreSettings::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsStoreSettings.php:10
* @route '/agents/{agent}/models/settings'
*/
update.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

const settings = {
    update: Object.assign(update, update),
}

export default settings