import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
import textC5a64e from './text'
import filesDccab9 from './files'
import website from './website'
import websites from './websites'
/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgePdfController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgePdfController.php:12
* @route '/agents/{agent}/sources/pdf'
*/
export const pdf = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pdf.url(args, options),
    method: 'get',
})

pdf.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgePdfController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgePdfController.php:12
* @route '/agents/{agent}/sources/pdf'
*/
pdf.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return pdf.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgePdfController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgePdfController.php:12
* @route '/agents/{agent}/sources/pdf'
*/
pdf.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgePdfController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgePdfController.php:12
* @route '/agents/{agent}/sources/pdf'
*/
pdf.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pdf.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeWordController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeWordController.php:12
* @route '/agents/{agent}/sources/word'
*/
export const word = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: word.url(args, options),
    method: 'get',
})

word.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/word',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeWordController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeWordController.php:12
* @route '/agents/{agent}/sources/word'
*/
word.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return word.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeWordController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeWordController.php:12
* @route '/agents/{agent}/sources/word'
*/
word.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: word.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeWordController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeWordController.php:12
* @route '/agents/{agent}/sources/word'
*/
word.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: word.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeWebController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeWebController.php:12
* @route '/agents/{agent}/sources/web'
*/
export const web = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: web.url(args, options),
    method: 'get',
})

web.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/web',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeWebController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeWebController.php:12
* @route '/agents/{agent}/sources/web'
*/
web.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return web.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeWebController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeWebController.php:12
* @route '/agents/{agent}/sources/web'
*/
web.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: web.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeWebController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeWebController.php:12
* @route '/agents/{agent}/sources/web'
*/
web.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: web.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextController.php:11
* @route '/agents/{agent}/sources/text'
*/
export const text = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: text.url(args, options),
    method: 'get',
})

text.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/text',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextController.php:11
* @route '/agents/{agent}/sources/text'
*/
text.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return text.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextController.php:11
* @route '/agents/{agent}/sources/text'
*/
text.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: text.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextController.php:11
* @route '/agents/{agent}/sources/text'
*/
text.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: text.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFilesController.php:12
* @route '/agents/{agent}/sources/files'
*/
export const files = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: files.url(args, options),
    method: 'get',
})

files.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/files',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFilesController.php:12
* @route '/agents/{agent}/sources/files'
*/
files.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return files.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFilesController.php:12
* @route '/agents/{agent}/sources/files'
*/
files.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: files.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFilesController.php:12
* @route '/agents/{agent}/sources/files'
*/
files.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: files.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeUploadedFilesDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeUploadedFilesDeleteController.php:10
* @route '/agents/{agent}/sources/files/uploaded/all'
*/
export const uploadedFiles = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: uploadedFiles.url(args, options),
    method: 'delete',
})

uploadedFiles.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/sources/files/uploaded/all',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeUploadedFilesDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeUploadedFilesDeleteController.php:10
* @route '/agents/{agent}/sources/files/uploaded/all'
*/
uploadedFiles.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return uploadedFiles.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeUploadedFilesDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeUploadedFilesDeleteController.php:10
* @route '/agents/{agent}/sources/files/uploaded/all'
*/
uploadedFiles.delete = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: uploadedFiles.url(args, options),
    method: 'delete',
})

const sources = {
    pdf: Object.assign(pdf, pdf),
    word: Object.assign(word, word),
    web: Object.assign(web, web),
    text: Object.assign(text, textC5a64e),
    files: Object.assign(files, filesDccab9),
    uploadedFiles: Object.assign(uploadedFiles, uploadedFiles),
    website: Object.assign(website, website),
    websites: Object.assign(websites, websites),
}

export default sources