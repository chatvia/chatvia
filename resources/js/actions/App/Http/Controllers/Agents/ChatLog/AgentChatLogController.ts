import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\ChatLog\AgentChatLogController::__invoke
* @see app/Http/Controllers/Agents/ChatLog/AgentChatLogController.php:15
* @route '/agents/{agent}/logs'
*/
const AgentChatLogController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentChatLogController.url(args, options),
    method: 'get',
})

AgentChatLogController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/logs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\ChatLog\AgentChatLogController::__invoke
* @see app/Http/Controllers/Agents/ChatLog/AgentChatLogController.php:15
* @route '/agents/{agent}/logs'
*/
AgentChatLogController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentChatLogController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\ChatLog\AgentChatLogController::__invoke
* @see app/Http/Controllers/Agents/ChatLog/AgentChatLogController.php:15
* @route '/agents/{agent}/logs'
*/
AgentChatLogController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentChatLogController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\ChatLog\AgentChatLogController::__invoke
* @see app/Http/Controllers/Agents/ChatLog/AgentChatLogController.php:15
* @route '/agents/{agent}/logs'
*/
AgentChatLogController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentChatLogController.url(args, options),
    method: 'head',
})

export default AgentChatLogController