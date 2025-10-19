import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextShowController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextShowController.php:9
* @route '/agents/{agent}/sources/text/{id}'
*/
const AgentKnowledgeTextShowController = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeTextShowController.url(args, options),
    method: 'get',
})

AgentKnowledgeTextShowController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/text/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextShowController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextShowController.php:9
* @route '/agents/{agent}/sources/text/{id}'
*/
AgentKnowledgeTextShowController.url = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            agent: args[0],
            id: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agent: typeof args.agent === 'object'
        ? args.agent.id
        : args.agent,
        id: args.id,
    }

    return AgentKnowledgeTextShowController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextShowController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextShowController.php:9
* @route '/agents/{agent}/sources/text/{id}'
*/
AgentKnowledgeTextShowController.get = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeTextShowController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextShowController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextShowController.php:9
* @route '/agents/{agent}/sources/text/{id}'
*/
AgentKnowledgeTextShowController.head = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentKnowledgeTextShowController.url(args, options),
    method: 'head',
})

export default AgentKnowledgeTextShowController