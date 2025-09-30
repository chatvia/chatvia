import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Agents\AgentUpdateSettingsController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentUpdateSettingsController.php:10
* @route '/agents/{agent}/settings/{group}'
*/
const AgentUpdateSettingsController = (args: { agent: number | { id: number }, group: string | number } | [agent: number | { id: number }, group: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentUpdateSettingsController.url(args, options),
    method: 'post',
})

AgentUpdateSettingsController.definition = {
    methods: ["post"],
    url: '/agents/{agent}/settings/{group}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentUpdateSettingsController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentUpdateSettingsController.php:10
* @route '/agents/{agent}/settings/{group}'
*/
AgentUpdateSettingsController.url = (args: { agent: number | { id: number }, group: string | number } | [agent: number | { id: number }, group: string | number ], options?: RouteQueryOptions) => {
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

    return AgentUpdateSettingsController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{group}', parsedArgs.group.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentUpdateSettingsController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentUpdateSettingsController.php:10
* @route '/agents/{agent}/settings/{group}'
*/
AgentUpdateSettingsController.post = (args: { agent: number | { id: number }, group: string | number } | [agent: number | { id: number }, group: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentUpdateSettingsController.url(args, options),
    method: 'post',
})

export default AgentUpdateSettingsController