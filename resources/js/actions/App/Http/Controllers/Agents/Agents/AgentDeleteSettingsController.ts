import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Agents\AgentDeleteSettingsController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentDeleteSettingsController.php:10
* @route '/agents/{agent}/settings/{group}'
*/
const AgentDeleteSettingsController = (args: { agent: number | { id: number }, group: string | number } | [agent: number | { id: number }, group: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentDeleteSettingsController.url(args, options),
    method: 'delete',
})

AgentDeleteSettingsController.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/settings/{group}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentDeleteSettingsController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentDeleteSettingsController.php:10
* @route '/agents/{agent}/settings/{group}'
*/
AgentDeleteSettingsController.url = (args: { agent: number | { id: number }, group: string | number } | [agent: number | { id: number }, group: string | number ], options?: RouteQueryOptions) => {
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

    return AgentDeleteSettingsController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{group}', parsedArgs.group.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentDeleteSettingsController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentDeleteSettingsController.php:10
* @route '/agents/{agent}/settings/{group}'
*/
AgentDeleteSettingsController.delete = (args: { agent: number | { id: number }, group: string | number } | [agent: number | { id: number }, group: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentDeleteSettingsController.url(args, options),
    method: 'delete',
})

export default AgentDeleteSettingsController