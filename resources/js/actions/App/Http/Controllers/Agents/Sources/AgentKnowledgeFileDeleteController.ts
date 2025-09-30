import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFileDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFileDeleteController.php:11
* @route '/agents/{agent}/sources/files/{file}'
*/
const AgentKnowledgeFileDeleteController = (args: { agent: number | { id: number }, file: number | { id: number } } | [agent: number | { id: number }, file: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentKnowledgeFileDeleteController.url(args, options),
    method: 'delete',
})

AgentKnowledgeFileDeleteController.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/sources/files/{file}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFileDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFileDeleteController.php:11
* @route '/agents/{agent}/sources/files/{file}'
*/
AgentKnowledgeFileDeleteController.url = (args: { agent: number | { id: number }, file: number | { id: number } } | [agent: number | { id: number }, file: number | { id: number } ], options?: RouteQueryOptions) => {
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

    return AgentKnowledgeFileDeleteController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{file}', parsedArgs.file.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFileDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFileDeleteController.php:11
* @route '/agents/{agent}/sources/files/{file}'
*/
AgentKnowledgeFileDeleteController.delete = (args: { agent: number | { id: number }, file: number | { id: number } } | [agent: number | { id: number }, file: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentKnowledgeFileDeleteController.url(args, options),
    method: 'delete',
})

export default AgentKnowledgeFileDeleteController