import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Agents\AgentKnowledgeFilesAnalyzeController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentKnowledgeFilesAnalyzeController.php:10
* @route '/agents/knowledge/files/analyze'
*/
const AgentKnowledgeFilesAnalyzeController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentKnowledgeFilesAnalyzeController.url(options),
    method: 'post',
})

AgentKnowledgeFilesAnalyzeController.definition = {
    methods: ["post"],
    url: '/agents/knowledge/files/analyze',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentKnowledgeFilesAnalyzeController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentKnowledgeFilesAnalyzeController.php:10
* @route '/agents/knowledge/files/analyze'
*/
AgentKnowledgeFilesAnalyzeController.url = (options?: RouteQueryOptions) => {
    return AgentKnowledgeFilesAnalyzeController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentKnowledgeFilesAnalyzeController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentKnowledgeFilesAnalyzeController.php:10
* @route '/agents/knowledge/files/analyze'
*/
AgentKnowledgeFilesAnalyzeController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentKnowledgeFilesAnalyzeController.url(options),
    method: 'post',
})

export default AgentKnowledgeFilesAnalyzeController