import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextDestroyController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextDestroyController.php:9
* @route '/agents/{agent}/sources/text/{id}'
*/
const AgentKnowledgeTextDestroyController = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentKnowledgeTextDestroyController.url(args, options),
    method: 'delete',
})

AgentKnowledgeTextDestroyController.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/sources/text/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextDestroyController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextDestroyController.php:9
* @route '/agents/{agent}/sources/text/{id}'
*/
AgentKnowledgeTextDestroyController.url = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions) => {
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

    return AgentKnowledgeTextDestroyController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextDestroyController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextDestroyController.php:9
* @route '/agents/{agent}/sources/text/{id}'
*/
AgentKnowledgeTextDestroyController.delete = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentKnowledgeTextDestroyController.url(args, options),
    method: 'delete',
})

export default AgentKnowledgeTextDestroyController