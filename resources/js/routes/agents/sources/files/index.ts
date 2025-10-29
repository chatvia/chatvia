import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFileUploadController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFileUploadController.php:11
* @route '/agents/{agent}/sources/files/upload'
*/
export const upload = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: upload.url(args, options),
    method: 'post',
})

upload.definition = {
    methods: ["post"],
    url: '/agents/{agent}/sources/files/upload',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFileUploadController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFileUploadController.php:11
* @route '/agents/{agent}/sources/files/upload'
*/
upload.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return upload.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFileUploadController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFileUploadController.php:11
* @route '/agents/{agent}/sources/files/upload'
*/
upload.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: upload.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFileDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFileDeleteController.php:11
* @route '/agents/{agent}/sources/files/{file}'
*/
export const deleteMethod = (args: { agent: number | { id: number }, file: number | { id: number } } | [agent: number | { id: number }, file: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

deleteMethod.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/sources/files/{file}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFileDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFileDeleteController.php:11
* @route '/agents/{agent}/sources/files/{file}'
*/
deleteMethod.url = (args: { agent: number | { id: number }, file: number | { id: number } } | [agent: number | { id: number }, file: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            agent: args[0],
            file: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agent: typeof args.agent === 'object'
        ? args.agent.id
        : args.agent,
        file: typeof args.file === 'object'
        ? args.file.id
        : args.file,
    }

    return deleteMethod.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{file}', parsedArgs.file.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFileDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFileDeleteController.php:11
* @route '/agents/{agent}/sources/files/{file}'
*/
deleteMethod.delete = (args: { agent: number | { id: number }, file: number | { id: number } } | [agent: number | { id: number }, file: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesProcessController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFilesProcessController.php:12
* @route '/agents/{agent}/sources/files/process'
*/
export const process = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: process.url(args, options),
    method: 'post',
})

process.definition = {
    methods: ["post"],
    url: '/agents/{agent}/sources/files/process',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesProcessController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFilesProcessController.php:12
* @route '/agents/{agent}/sources/files/process'
*/
process.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return process.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesProcessController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFilesProcessController.php:12
* @route '/agents/{agent}/sources/files/process'
*/
process.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: process.url(args, options),
    method: 'post',
})

const files = {
    upload: Object.assign(upload, upload),
    delete: Object.assign(deleteMethod, deleteMethod),
    process: Object.assign(process, process),
}

export default files