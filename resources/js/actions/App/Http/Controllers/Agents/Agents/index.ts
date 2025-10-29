import AgentsIndexController from './AgentsIndexController'
import AgentsListController from './AgentsListController'
import AgentStoreController from './AgentStoreController'
import AgentUpdateController from './AgentUpdateController'
import AgentPlaygroundController from './AgentPlaygroundController'
import AgentFileSummaryController from './AgentFileSummaryController'
import AgentUpdateSettingsController from './AgentUpdateSettingsController'
import AgentDeleteSettingsController from './AgentDeleteSettingsController'
import AgentRenameController from './AgentRenameController'
import AgentDeleteController from './AgentDeleteController'
import AgentKnowledgeFilesAnalyzeController from './AgentKnowledgeFilesAnalyzeController'
import AgentsGenerateRandomSubDomainController from './AgentsGenerateRandomSubDomainController'

const Agents = {
    AgentsIndexController: Object.assign(AgentsIndexController, AgentsIndexController),
    AgentsListController: Object.assign(AgentsListController, AgentsListController),
    AgentStoreController: Object.assign(AgentStoreController, AgentStoreController),
    AgentUpdateController: Object.assign(AgentUpdateController, AgentUpdateController),
    AgentPlaygroundController: Object.assign(AgentPlaygroundController, AgentPlaygroundController),
    AgentFileSummaryController: Object.assign(AgentFileSummaryController, AgentFileSummaryController),
    AgentUpdateSettingsController: Object.assign(AgentUpdateSettingsController, AgentUpdateSettingsController),
    AgentDeleteSettingsController: Object.assign(AgentDeleteSettingsController, AgentDeleteSettingsController),
    AgentRenameController: Object.assign(AgentRenameController, AgentRenameController),
    AgentDeleteController: Object.assign(AgentDeleteController, AgentDeleteController),
    AgentKnowledgeFilesAnalyzeController: Object.assign(AgentKnowledgeFilesAnalyzeController, AgentKnowledgeFilesAnalyzeController),
    AgentsGenerateRandomSubDomainController: Object.assign(AgentsGenerateRandomSubDomainController, AgentsGenerateRandomSubDomainController),
}

export default Agents