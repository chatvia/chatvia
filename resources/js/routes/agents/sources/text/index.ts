import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextShowController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextShowController.php:10
* @route '/agents/{agent}/sources/text/{id}'
*/
export const show = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/text/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextShowController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextShowController.php:10
* @route '/agents/{agent}/sources/text/{id}'
*/
show.url = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextShowController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextShowController.php:10
* @route '/agents/{agent}/sources/text/{id}'
*/
show.get = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextShowController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextShowController.php:10
* @route '/agents/{agent}/sources/text/{id}'
*/
show.head = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextStoreController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextStoreController.php:12
* @route '/agents/{agent}/sources/text'
*/
export const store = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/agents/{agent}/sources/text',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextStoreController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextStoreController.php:12
* @route '/agents/{agent}/sources/text'
*/
store.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextStoreController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextStoreController.php:12
* @route '/agents/{agent}/sources/text'
*/
store.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextListController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextListController.php:9
* @route '/agents/{agent}/sources/text/list'
*/
export const index = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/text/list',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextListController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextListController.php:9
* @route '/agents/{agent}/sources/text/list'
*/
index.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return index.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextListController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextListController.php:9
* @route '/agents/{agent}/sources/text/list'
*/
index.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextListController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextListController.php:9
* @route '/agents/{agent}/sources/text/list'
*/
index.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextDestroyController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextDestroyController.php:10
* @route '/agents/{agent}/sources/text/{id}'
*/
export const destroy = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/sources/text/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextDestroyController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextDestroyController.php:10
* @route '/agents/{agent}/sources/text/{id}'
*/
destroy.url = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextDestroyController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextDestroyController.php:10
* @route '/agents/{agent}/sources/text/{id}'
*/
destroy.delete = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const text = {
    show: Object.assign(show, show),
    store: Object.assign(store, store),
    index: Object.assign(index, index),
    destroy: Object.assign(destroy, destroy),
}

export default text