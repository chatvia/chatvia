import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerController.php:17
* @route '/agents/{agent}/sources/website'
*/
export const crawl = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: crawl.url(args, options),
    method: 'post',
})

crawl.definition = {
    methods: ["post"],
    url: '/agents/{agent}/sources/website',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerController.php:17
* @route '/agents/{agent}/sources/website'
*/
crawl.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return crawl.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerController.php:17
* @route '/agents/{agent}/sources/website'
*/
crawl.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: crawl.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteTrainController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteTrainController.php:12
* @route '/agents/{agent}/sources/website/{website}/train'
*/
export const train = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: train.url(args, options),
    method: 'post',
})

train.definition = {
    methods: ["post"],
    url: '/agents/{agent}/sources/website/{website}/train',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteTrainController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteTrainController.php:12
* @route '/agents/{agent}/sources/website/{website}/train'
*/
train.url = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions) => {
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

    return train.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{website}', parsedArgs.website.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteTrainController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteTrainController.php:12
* @route '/agents/{agent}/sources/website/{website}/train'
*/
train.post = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: train.url(args, options),
    method: 'post',
})

const website = {
    crawl: Object.assign(crawl, crawl),
    train: Object.assign(train, train),
}

export default website