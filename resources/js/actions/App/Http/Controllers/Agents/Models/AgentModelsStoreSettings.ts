import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsStoreSettings::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsStoreSettings.php:10
* @route '/agents/{agent}/models/settings'
*/
const AgentModelsStoreSettings = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: AgentModelsStoreSettings.url(args, options),
    method: 'patch',
})

AgentModelsStoreSettings.definition = {
    methods: ["patch"],
    url: '/agents/{agent}/models/settings',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsStoreSettings::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsStoreSettings.php:10
* @route '/agents/{agent}/models/settings'
*/
AgentModelsStoreSettings.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentModelsStoreSettings.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsStoreSettings::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsStoreSettings.php:10
* @route '/agents/{agent}/models/settings'
*/
AgentModelsStoreSettings.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: AgentModelsStoreSettings.url(args, options),
    method: 'patch',
})

export default AgentModelsStoreSettings