import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
import security176da1 from './security'
import sources4cea9d from './sources'
import agent from './agent'
import starters659bf6 from './starters'
import leads2ef265 from './leads'
import settings from './settings'
import modelsEff558 from './models'
import team4b9005 from './team'
import knowledge from './knowledge'
import invitations25ebbd from './invitations'
/**
* @see \App\Http\Controllers\Agents\Agents\AgentsIndexController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsIndexController.php:10
* @route '/agents'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/agents',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsIndexController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsIndexController.php:10
* @route '/agents'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsIndexController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsIndexController.php:10
* @route '/agents'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsIndexController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsIndexController.php:10
* @route '/agents'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsListController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsListController.php:10
* @route '/agents/list'
*/
export const list = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: list.url(options),
    method: 'get',
})

list.definition = {
    methods: ["get","head"],
    url: '/agents/list',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsListController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsListController.php:10
* @route '/agents/list'
*/
list.url = (options?: RouteQueryOptions) => {
    return list.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsListController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsListController.php:10
* @route '/agents/list'
*/
list.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: list.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsListController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsListController.php:10
* @route '/agents/list'
*/
list.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: list.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentStoreController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentStoreController.php:15
* @route '/agents'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/agents',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentStoreController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentStoreController.php:15
* @route '/agents'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentStoreController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentStoreController.php:15
* @route '/agents'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Agents\Stats\AgentStatsController::__invoke
* @see app/Http/Controllers/Agents/Stats/AgentStatsController.php:11
* @route '/agents/{agent}/stats'
*/
export const stats = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: stats.url(args, options),
    method: 'get',
})

stats.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/stats',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Stats\AgentStatsController::__invoke
* @see app/Http/Controllers/Agents/Stats/AgentStatsController.php:11
* @route '/agents/{agent}/stats'
*/
stats.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return stats.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Stats\AgentStatsController::__invoke
* @see app/Http/Controllers/Agents/Stats/AgentStatsController.php:11
* @route '/agents/{agent}/stats'
*/
stats.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: stats.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Stats\AgentStatsController::__invoke
* @see app/Http/Controllers/Agents/Stats/AgentStatsController.php:11
* @route '/agents/{agent}/stats'
*/
stats.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: stats.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentUpdateController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentUpdateController.php:10
* @route '/agents/{agent}'
*/
export const update = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/agents/{agent}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentUpdateController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentUpdateController.php:10
* @route '/agents/{agent}'
*/
update.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentUpdateController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentUpdateController.php:10
* @route '/agents/{agent}'
*/
update.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentPreviewController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentPreviewController.php:12
* @route '/agents/{agent}/preview'
*/
export const preview = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})

preview.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/preview',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentPreviewController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentPreviewController.php:12
* @route '/agents/{agent}/preview'
*/
preview.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return preview.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentPreviewController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentPreviewController.php:12
* @route '/agents/{agent}/preview'
*/
preview.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentPreviewController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentPreviewController.php:12
* @route '/agents/{agent}/preview'
*/
preview.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: preview.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Persona\AgentShowPersonaController::__invoke
* @see app/Http/Controllers/Agents/Persona/AgentShowPersonaController.php:12
* @route '/agents/{agent}/persona'
*/
export const persona = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: persona.url(args, options),
    method: 'get',
})

persona.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/persona',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Persona\AgentShowPersonaController::__invoke
* @see app/Http/Controllers/Agents/Persona/AgentShowPersonaController.php:12
* @route '/agents/{agent}/persona'
*/
persona.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return persona.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Persona\AgentShowPersonaController::__invoke
* @see app/Http/Controllers/Agents/Persona/AgentShowPersonaController.php:12
* @route '/agents/{agent}/persona'
*/
persona.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: persona.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Persona\AgentShowPersonaController::__invoke
* @see app/Http/Controllers/Agents/Persona/AgentShowPersonaController.php:12
* @route '/agents/{agent}/persona'
*/
persona.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: persona.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Security\AgentSecurityController::__invoke
* @see app/Http/Controllers/Agents/Security/AgentSecurityController.php:11
* @route '/agents/{agent}/security'
*/
export const security = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: security.url(args, options),
    method: 'get',
})

security.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/security',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Security\AgentSecurityController::__invoke
* @see app/Http/Controllers/Agents/Security/AgentSecurityController.php:11
* @route '/agents/{agent}/security'
*/
security.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return security.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Security\AgentSecurityController::__invoke
* @see app/Http/Controllers/Agents/Security/AgentSecurityController.php:11
* @route '/agents/{agent}/security'
*/
security.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: security.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Security\AgentSecurityController::__invoke
* @see app/Http/Controllers/Agents/Security/AgentSecurityController.php:11
* @route '/agents/{agent}/security'
*/
security.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: security.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeSourcesController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeSourcesController.php:11
* @route '/agents/{agent}/sources'
*/
export const sources = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sources.url(args, options),
    method: 'get',
})

sources.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeSourcesController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeSourcesController.php:11
* @route '/agents/{agent}/sources'
*/
sources.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return sources.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeSourcesController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeSourcesController.php:11
* @route '/agents/{agent}/sources'
*/
sources.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sources.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeSourcesController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeSourcesController.php:11
* @route '/agents/{agent}/sources'
*/
sources.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: sources.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Starters\StartersShowController::__invoke
* @see app/Http/Controllers/Agents/Starters/StartersShowController.php:11
* @route '/agents/{agent}/starters'
*/
export const starters = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: starters.url(args, options),
    method: 'get',
})

starters.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/starters',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Starters\StartersShowController::__invoke
* @see app/Http/Controllers/Agents/Starters/StartersShowController.php:11
* @route '/agents/{agent}/starters'
*/
starters.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return starters.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Starters\StartersShowController::__invoke
* @see app/Http/Controllers/Agents/Starters/StartersShowController.php:11
* @route '/agents/{agent}/starters'
*/
starters.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: starters.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Starters\StartersShowController::__invoke
* @see app/Http/Controllers/Agents/Starters/StartersShowController.php:11
* @route '/agents/{agent}/starters'
*/
starters.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: starters.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\History\AgentHistoryController::__invoke
* @see app/Http/Controllers/Agents/History/AgentHistoryController.php:11
* @route '/agents/{agent}/history'
*/
export const history = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: history.url(args, options),
    method: 'get',
})

history.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/history',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\History\AgentHistoryController::__invoke
* @see app/Http/Controllers/Agents/History/AgentHistoryController.php:11
* @route '/agents/{agent}/history'
*/
history.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return history.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\History\AgentHistoryController::__invoke
* @see app/Http/Controllers/Agents/History/AgentHistoryController.php:11
* @route '/agents/{agent}/history'
*/
history.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: history.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\History\AgentHistoryController::__invoke
* @see app/Http/Controllers/Agents/History/AgentHistoryController.php:11
* @route '/agents/{agent}/history'
*/
history.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: history.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsController.php:11
* @route '/agents/{agent}/leads'
*/
export const leads = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: leads.url(args, options),
    method: 'get',
})

leads.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/leads',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsController.php:11
* @route '/agents/{agent}/leads'
*/
leads.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return leads.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsController.php:11
* @route '/agents/{agent}/leads'
*/
leads.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: leads.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Leads\AgentLeadsController::__invoke
* @see app/Http/Controllers/Agents/Leads/AgentLeadsController.php:11
* @route '/agents/{agent}/leads'
*/
leads.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: leads.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\ChatLog\AgentChatLogController::__invoke
* @see app/Http/Controllers/Agents/ChatLog/AgentChatLogController.php:15
* @route '/agents/{agent}/logs'
*/
export const logs = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: logs.url(args, options),
    method: 'get',
})

logs.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/logs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\ChatLog\AgentChatLogController::__invoke
* @see app/Http/Controllers/Agents/ChatLog/AgentChatLogController.php:15
* @route '/agents/{agent}/logs'
*/
logs.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return logs.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\ChatLog\AgentChatLogController::__invoke
* @see app/Http/Controllers/Agents/ChatLog/AgentChatLogController.php:15
* @route '/agents/{agent}/logs'
*/
logs.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: logs.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\ChatLog\AgentChatLogController::__invoke
* @see app/Http/Controllers/Agents/ChatLog/AgentChatLogController.php:15
* @route '/agents/{agent}/logs'
*/
logs.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: logs.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Appearance\AgentAppearanceController::__invoke
* @see app/Http/Controllers/Agents/Appearance/AgentAppearanceController.php:11
* @route '/agents/{agent}/appearance'
*/
export const appearance = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: appearance.url(args, options),
    method: 'get',
})

appearance.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/appearance',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Appearance\AgentAppearanceController::__invoke
* @see app/Http/Controllers/Agents/Appearance/AgentAppearanceController.php:11
* @route '/agents/{agent}/appearance'
*/
appearance.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return appearance.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Appearance\AgentAppearanceController::__invoke
* @see app/Http/Controllers/Agents/Appearance/AgentAppearanceController.php:11
* @route '/agents/{agent}/appearance'
*/
appearance.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: appearance.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Appearance\AgentAppearanceController::__invoke
* @see app/Http/Controllers/Agents/Appearance/AgentAppearanceController.php:11
* @route '/agents/{agent}/appearance'
*/
appearance.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: appearance.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Widget\WidgetController::__invoke
* @see app/Http/Controllers/Agents/Widget/WidgetController.php:11
* @route '/agents/{agent}/widget'
*/
export const widget = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: widget.url(args, options),
    method: 'get',
})

widget.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/widget',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Widget\WidgetController::__invoke
* @see app/Http/Controllers/Agents/Widget/WidgetController.php:11
* @route '/agents/{agent}/widget'
*/
widget.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return widget.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Widget\WidgetController::__invoke
* @see app/Http/Controllers/Agents/Widget/WidgetController.php:11
* @route '/agents/{agent}/widget'
*/
widget.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: widget.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Widget\WidgetController::__invoke
* @see app/Http/Controllers/Agents/Widget/WidgetController.php:11
* @route '/agents/{agent}/widget'
*/
widget.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: widget.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Embed\EmbedController::__invoke
* @see app/Http/Controllers/Agents/Embed/EmbedController.php:12
* @route '/agents/{agent}/embed'
*/
export const embed = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: embed.url(args, options),
    method: 'get',
})

embed.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/embed',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Embed\EmbedController::__invoke
* @see app/Http/Controllers/Agents/Embed/EmbedController.php:12
* @route '/agents/{agent}/embed'
*/
embed.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return embed.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Embed\EmbedController::__invoke
* @see app/Http/Controllers/Agents/Embed/EmbedController.php:12
* @route '/agents/{agent}/embed'
*/
embed.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: embed.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Embed\EmbedController::__invoke
* @see app/Http/Controllers/Agents/Embed/EmbedController.php:12
* @route '/agents/{agent}/embed'
*/
embed.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: embed.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsShowController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsShowController.php:12
* @route '/agents/{agent}/models'
*/
export const models = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: models.url(args, options),
    method: 'get',
})

models.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/models',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsShowController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsShowController.php:12
* @route '/agents/{agent}/models'
*/
models.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return models.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsShowController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsShowController.php:12
* @route '/agents/{agent}/models'
*/
models.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: models.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsShowController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsShowController.php:12
* @route '/agents/{agent}/models'
*/
models.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: models.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Team\AgentTeamController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentTeamController.php:12
* @route '/agents/{agent}/team'
*/
export const team = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: team.url(args, options),
    method: 'get',
})

team.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/team',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Team\AgentTeamController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentTeamController.php:12
* @route '/agents/{agent}/team'
*/
team.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return team.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Team\AgentTeamController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentTeamController.php:12
* @route '/agents/{agent}/team'
*/
team.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: team.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Team\AgentTeamController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentTeamController.php:12
* @route '/agents/{agent}/team'
*/
team.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: team.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Workflow\AgentWorkflowController::__invoke
* @see app/Http/Controllers/Agents/Workflow/AgentWorkflowController.php:12
* @route '/agents/{agent}/workflow'
*/
export const workflow = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: workflow.url(args, options),
    method: 'get',
})

workflow.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/workflow',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Workflow\AgentWorkflowController::__invoke
* @see app/Http/Controllers/Agents/Workflow/AgentWorkflowController.php:12
* @route '/agents/{agent}/workflow'
*/
workflow.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return workflow.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Workflow\AgentWorkflowController::__invoke
* @see app/Http/Controllers/Agents/Workflow/AgentWorkflowController.php:12
* @route '/agents/{agent}/workflow'
*/
workflow.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: workflow.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Workflow\AgentWorkflowController::__invoke
* @see app/Http/Controllers/Agents/Workflow/AgentWorkflowController.php:12
* @route '/agents/{agent}/workflow'
*/
workflow.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: workflow.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Actions\AgentActionsController::__invoke
* @see app/Http/Controllers/Agents/Actions/AgentActionsController.php:12
* @route '/agents/{agent}/actions'
*/
export const actions = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: actions.url(args, options),
    method: 'get',
})

actions.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/actions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Actions\AgentActionsController::__invoke
* @see app/Http/Controllers/Agents/Actions/AgentActionsController.php:12
* @route '/agents/{agent}/actions'
*/
actions.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return actions.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Actions\AgentActionsController::__invoke
* @see app/Http/Controllers/Agents/Actions/AgentActionsController.php:12
* @route '/agents/{agent}/actions'
*/
actions.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: actions.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Actions\AgentActionsController::__invoke
* @see app/Http/Controllers/Agents/Actions/AgentActionsController.php:12
* @route '/agents/{agent}/actions'
*/
actions.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: actions.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentRenameController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentRenameController.php:11
* @route '/agents/{agent}/rename'
*/
export const rename = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: rename.url(args, options),
    method: 'patch',
})

rename.definition = {
    methods: ["patch"],
    url: '/agents/{agent}/rename',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentRenameController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentRenameController.php:11
* @route '/agents/{agent}/rename'
*/
rename.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return rename.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentRenameController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentRenameController.php:11
* @route '/agents/{agent}/rename'
*/
rename.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: rename.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentDeleteController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentDeleteController.php:10
* @route '/agents/{agent}'
*/
export const deleteMethod = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

deleteMethod.definition = {
    methods: ["delete"],
    url: '/agents/{agent}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentDeleteController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentDeleteController.php:10
* @route '/agents/{agent}'
*/
deleteMethod.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return deleteMethod.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentDeleteController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentDeleteController.php:10
* @route '/agents/{agent}'
*/
deleteMethod.delete = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersInvitationCreateAccountController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersInvitationCreateAccountController.php:11
* @route '/agents/{agent}/invitations/{token}'
*/
export const invitations = (args: { agent: number | { id: number }, token: string | number } | [agent: number | { id: number }, token: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: invitations.url(args, options),
    method: 'get',
})

invitations.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/invitations/{token}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersInvitationCreateAccountController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersInvitationCreateAccountController.php:11
* @route '/agents/{agent}/invitations/{token}'
*/
invitations.url = (args: { agent: number | { id: number }, token: string | number } | [agent: number | { id: number }, token: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            agent: args[0],
            token: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agent: typeof args.agent === 'object'
        ? args.agent.id
        : args.agent,
        token: args.token,
    }

    return invitations.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersInvitationCreateAccountController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersInvitationCreateAccountController.php:11
* @route '/agents/{agent}/invitations/{token}'
*/
invitations.get = (args: { agent: number | { id: number }, token: string | number } | [agent: number | { id: number }, token: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: invitations.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Team\AgentMembersInvitationCreateAccountController::__invoke
* @see app/Http/Controllers/Agents/Team/AgentMembersInvitationCreateAccountController.php:11
* @route '/agents/{agent}/invitations/{token}'
*/
invitations.head = (args: { agent: number | { id: number }, token: string | number } | [agent: number | { id: number }, token: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: invitations.url(args, options),
    method: 'head',
})

const agents = {
    index: Object.assign(index, index),
    list: Object.assign(list, list),
    store: Object.assign(store, store),
    stats: Object.assign(stats, stats),
    update: Object.assign(update, update),
    widget: Object.assign(widget, widget),
    preview: Object.assign(preview, preview),
    persona: Object.assign(persona, persona),
    security: Object.assign(security, security176da1),
    sources: Object.assign(sources, sources4cea9d),
    agent: Object.assign(agent, agent),
    starters: Object.assign(starters, starters659bf6),
    history: Object.assign(history, history),
    leads: Object.assign(leads, leads2ef265),
    logs: Object.assign(logs, logs),
    appearance: Object.assign(appearance, appearance),
    embed: Object.assign(embed, embed),
    settings: Object.assign(settings, settings),
    models: Object.assign(models, modelsEff558),
    team: Object.assign(team, team4b9005),
    workflow: Object.assign(workflow, workflow),
    actions: Object.assign(actions, actions),
    rename: Object.assign(rename, rename),
    delete: Object.assign(deleteMethod, deleteMethod),
    knowledge: Object.assign(knowledge, knowledge),
    invitations: Object.assign(invitations, invitations25ebbd),
}

export default agents