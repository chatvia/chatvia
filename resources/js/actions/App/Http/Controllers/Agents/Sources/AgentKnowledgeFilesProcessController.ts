import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesProcessController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFilesProcessController.php:12
* @route '/agents/{agent}/sources/files/process'
*/
const AgentKnowledgeFilesProcessController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentKnowledgeFilesProcessController.url(args, options),
    method: 'post',
})

AgentKnowledgeFilesProcessController.definition = {
    methods: ["post"],
    url: '/agents/{agent}/sources/files/process',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesProcessController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFilesProcessController.php:12
* @route '/agents/{agent}/sources/files/process'
*/
AgentKnowledgeFilesProcessController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentKnowledgeFilesProcessController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesProcessController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFilesProcessController.php:12
* @route '/agents/{agent}/sources/files/process'
*/
AgentKnowledgeFilesProcessController.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentKnowledgeFilesProcessController.url(args, options),
    method: 'post',
})

export default AgentKnowledgeFilesProcessController