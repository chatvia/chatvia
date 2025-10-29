import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeMarkdownController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeMarkdownController.php:11
* @route '/agents/{agent}/sources/markdown'
*/
const AgentKnowledgeMarkdownController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeMarkdownController.url(args, options),
    method: 'get',
})

AgentKnowledgeMarkdownController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/markdown',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeMarkdownController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeMarkdownController.php:11
* @route '/agents/{agent}/sources/markdown'
*/
AgentKnowledgeMarkdownController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentKnowledgeMarkdownController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeMarkdownController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeMarkdownController.php:11
* @route '/agents/{agent}/sources/markdown'
*/
AgentKnowledgeMarkdownController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeMarkdownController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeMarkdownController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeMarkdownController.php:11
* @route '/agents/{agent}/sources/markdown'
*/
AgentKnowledgeMarkdownController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentKnowledgeMarkdownController.url(args, options),
    method: 'head',
})

export default AgentKnowledgeMarkdownController