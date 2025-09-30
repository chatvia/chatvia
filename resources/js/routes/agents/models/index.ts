import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
import settings from './settings'
/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsListController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsListController.php:11
* @route '/agents/{agent}/models/list'
*/
export const list = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: list.url(args, options),
    method: 'get',
})

list.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/models/list',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsListController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsListController.php:11
* @route '/agents/{agent}/models/list'
*/
list.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return list.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsListController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsListController.php:11
* @route '/agents/{agent}/models/list'
*/
list.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: list.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsListController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsListController.php:11
* @route '/agents/{agent}/models/list'
*/
list.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: list.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsToggleController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsToggleController.php:11
* @route '/agents/{agent}/models/toggle'
*/
export const toggle = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggle.url(args, options),
    method: 'patch',
})

toggle.definition = {
    methods: ["patch"],
    url: '/agents/{agent}/models/toggle',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsToggleController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsToggleController.php:11
* @route '/agents/{agent}/models/toggle'
*/
toggle.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return toggle.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsToggleController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsToggleController.php:11
* @route '/agents/{agent}/models/toggle'
*/
toggle.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggle.url(args, options),
    method: 'patch',
})

const models = {
    settings: Object.assign(settings, settings),
    toggle: Object.assign(toggle, toggle),
}

export default models