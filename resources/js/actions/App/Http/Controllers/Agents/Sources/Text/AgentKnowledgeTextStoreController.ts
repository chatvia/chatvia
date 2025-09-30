import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextStoreController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextStoreController.php:12
* @route '/agents/{agent}/sources/text'
*/
const AgentKnowledgeTextStoreController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentKnowledgeTextStoreController.url(args, options),
    method: 'post',
})

AgentKnowledgeTextStoreController.definition = {
    methods: ["post"],
    url: '/agents/{agent}/sources/text',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextStoreController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextStoreController.php:12
* @route '/agents/{agent}/sources/text'
*/
AgentKnowledgeTextStoreController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentKnowledgeTextStoreController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextStoreController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextStoreController.php:12
* @route '/agents/{agent}/sources/text'
*/
AgentKnowledgeTextStoreController.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentKnowledgeTextStoreController.url(args, options),
    method: 'post',
})

export default AgentKnowledgeTextStoreController