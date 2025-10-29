import AgentKnowledgeSourcesController from './AgentKnowledgeSourcesController'
import AgentKnowledgeSourcesStatsController from './AgentKnowledgeSourcesStatsController'
import AgentKnowledgePdfController from './AgentKnowledgePdfController'
import AgentKnowledgeWordController from './AgentKnowledgeWordController'
import AgentKnowledgeMarkdownController from './AgentKnowledgeMarkdownController'
import AgentKnowledgeAudioController from './AgentKnowledgeAudioController'
import AgentKnowledgeVideoController from './AgentKnowledgeVideoController'
import AgentKnowledgeWebController from './AgentKnowledgeWebController'
import Text from './Text'
import AgentKnowledgeFilesController from './AgentKnowledgeFilesController'
import AgentKnowledgeFileUploadController from './AgentKnowledgeFileUploadController'
import AgentKnowledgeFileDeleteController from './AgentKnowledgeFileDeleteController'
import AgentKnowledgeUploadedFilesDeleteController from './AgentKnowledgeUploadedFilesDeleteController'
import AgentKnowledgeFilesProcessController from './AgentKnowledgeFilesProcessController'
import Crawler from './Crawler'

const Sources = {
    AgentKnowledgeSourcesController: Object.assign(AgentKnowledgeSourcesController, AgentKnowledgeSourcesController),
    AgentKnowledgeSourcesStatsController: Object.assign(AgentKnowledgeSourcesStatsController, AgentKnowledgeSourcesStatsController),
    AgentKnowledgePdfController: Object.assign(AgentKnowledgePdfController, AgentKnowledgePdfController),
    AgentKnowledgeWordController: Object.assign(AgentKnowledgeWordController, AgentKnowledgeWordController),
    AgentKnowledgeMarkdownController: Object.assign(AgentKnowledgeMarkdownController, AgentKnowledgeMarkdownController),
    AgentKnowledgeAudioController: Object.assign(AgentKnowledgeAudioController, AgentKnowledgeAudioController),
    AgentKnowledgeVideoController: Object.assign(AgentKnowledgeVideoController, AgentKnowledgeVideoController),
    AgentKnowledgeWebController: Object.assign(AgentKnowledgeWebController, AgentKnowledgeWebController),
    Text: Object.assign(Text, Text),
    AgentKnowledgeFilesController: Object.assign(AgentKnowledgeFilesController, AgentKnowledgeFilesController),
    AgentKnowledgeFileUploadController: Object.assign(AgentKnowledgeFileUploadController, AgentKnowledgeFileUploadController),
    AgentKnowledgeFileDeleteController: Object.assign(AgentKnowledgeFileDeleteController, AgentKnowledgeFileDeleteController),
    AgentKnowledgeUploadedFilesDeleteController: Object.assign(AgentKnowledgeUploadedFilesDeleteController, AgentKnowledgeUploadedFilesDeleteController),
    AgentKnowledgeFilesProcessController: Object.assign(AgentKnowledgeFilesProcessController, AgentKnowledgeFilesProcessController),
    Crawler: Object.assign(Crawler, Crawler),
}

export default Sources