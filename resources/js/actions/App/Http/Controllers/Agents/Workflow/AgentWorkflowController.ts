import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Workflow\AgentWorkflowController::__invoke
* @see app/Http/Controllers/Agents/Workflow/AgentWorkflowController.php:12
* @route '/agents/{agent}/workflow'
*/
const AgentWorkflowController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentWorkflowController.url(args, options),
    method: 'get',
})

AgentWorkflowController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/workflow',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Workflow\AgentWorkflowController::__invoke
* @see app/Http/Controllers/Agents/Workflow/AgentWorkflowController.php:12
* @route '/agents/{agent}/workflow'
*/
AgentWorkflowController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentWorkflowController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Workflow\AgentWorkflowController::__invoke
* @see app/Http/Controllers/Agents/Workflow/AgentWorkflowController.php:12
* @route '/agents/{agent}/workflow'
*/
AgentWorkflowController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentWorkflowController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Workflow\AgentWorkflowController::__invoke
* @see app/Http/Controllers/Agents/Workflow/AgentWorkflowController.php:12
* @route '/agents/{agent}/workflow'
*/
AgentWorkflowController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentWorkflowController.url(args, options),
    method: 'head',
})

export default AgentWorkflowController