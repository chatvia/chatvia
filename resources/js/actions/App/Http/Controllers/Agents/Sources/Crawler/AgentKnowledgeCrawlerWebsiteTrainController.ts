import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteTrainController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteTrainController.php:12
* @route '/agents/{agent}/sources/website/{website}/train'
*/
const AgentKnowledgeCrawlerWebsiteTrainController = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentKnowledgeCrawlerWebsiteTrainController.url(args, options),
    method: 'post',
})

AgentKnowledgeCrawlerWebsiteTrainController.definition = {
    methods: ["post"],
    url: '/agents/{agent}/sources/website/{website}/train',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteTrainController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteTrainController.php:12
* @route '/agents/{agent}/sources/website/{website}/train'
*/
AgentKnowledgeCrawlerWebsiteTrainController.url = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            agent: args[0],
            website: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agent: typeof args.agent === 'object'
        ? args.agent.id
        : args.agent,
        website: typeof args.website === 'object'
        ? args.website.id
        : args.website,
    }

    return AgentKnowledgeCrawlerWebsiteTrainController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{website}', parsedArgs.website.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteTrainController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteTrainController.php:12
* @route '/agents/{agent}/sources/website/{website}/train'
*/
AgentKnowledgeCrawlerWebsiteTrainController.post = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentKnowledgeCrawlerWebsiteTrainController.url(args, options),
    method: 'post',
})

export default AgentKnowledgeCrawlerWebsiteTrainController