import AgentKnowledgeTextController from './AgentKnowledgeTextController'
import AgentKnowledgeTextStoreController from './AgentKnowledgeTextStoreController'
import AgentKnowledgeTextListController from './AgentKnowledgeTextListController'
import AgentKnowledgeTextShowController from './AgentKnowledgeTextShowController'
import AgentKnowledgeTextUpdateController from './AgentKnowledgeTextUpdateController'
import AgentKnowledgeTextDestroyController from './AgentKnowledgeTextDestroyController'

const Text = {
    AgentKnowledgeTextController: Object.assign(AgentKnowledgeTextController, AgentKnowledgeTextController),
    AgentKnowledgeTextStoreController: Object.assign(AgentKnowledgeTextStoreController, AgentKnowledgeTextStoreController),
    AgentKnowledgeTextListController: Object.assign(AgentKnowledgeTextListController, AgentKnowledgeTextListController),
    AgentKnowledgeTextShowController: Object.assign(AgentKnowledgeTextShowController, AgentKnowledgeTextShowController),
    AgentKnowledgeTextUpdateController: Object.assign(AgentKnowledgeTextUpdateController, AgentKnowledgeTextUpdateController),
    AgentKnowledgeTextDestroyController: Object.assign(AgentKnowledgeTextDestroyController, AgentKnowledgeTextDestroyController),
}

export default Text