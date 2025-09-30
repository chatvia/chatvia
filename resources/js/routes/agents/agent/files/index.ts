import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Agents\AgentFileSummaryController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentFileSummaryController.php:12
* @route '/agents/{agent}/sources/files/{file}/summary'
*/
export const summary = (args: { agent: number | { id: number }, file: number | { id: number } } | [agent: number | { id: number }, file: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: summary.url(args, options),
    method: 'get',
})

summary.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/files/{file}/summary',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentFileSummaryController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentFileSummaryController.php:12
* @route '/agents/{agent}/sources/files/{file}/summary'
*/
summary.url = (args: { agent: number | { id: number }, file: number | { id: number } } | [agent: number | { id: number }, file: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            agent: args[0],
            file: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agent: typeof args.agent === 'object'
        ? args.agent.id
        : args.agent,
        file: typeof args.file === 'object'
        ? args.file.id
        : args.file,
    }

    return summary.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{file}', parsedArgs.file.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentFileSummaryController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentFileSummaryController.php:12
* @route '/agents/{agent}/sources/files/{file}/summary'
*/
summary.get = (args: { agent: number | { id: number }, file: number | { id: number } } | [agent: number | { id: number }, file: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: summary.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentFileSummaryController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentFileSummaryController.php:12
* @route '/agents/{agent}/sources/files/{file}/summary'
*/
summary.head = (args: { agent: number | { id: number }, file: number | { id: number } } | [agent: number | { id: number }, file: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: summary.url(args, options),
    method: 'head',
})

const files = {
    summary: Object.assign(summary, summary),
}

export default files