import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerController.php:17
* @route '/agents/{agent}/sources/website'
*/
const AgentKnowledgeCrawlerController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentKnowledgeCrawlerController.url(args, options),
    method: 'post',
})

AgentKnowledgeCrawlerController.definition = {
    methods: ["post"],
    url: '/agents/{agent}/sources/website',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerController.php:17
* @route '/agents/{agent}/sources/website'
*/
AgentKnowledgeCrawlerController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentKnowledgeCrawlerController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerController.php:17
* @route '/agents/{agent}/sources/website'
*/
AgentKnowledgeCrawlerController.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentKnowledgeCrawlerController.url(args, options),
    method: 'post',
})

export default AgentKnowledgeCrawlerController