import AgentKnowledgeTextController from './AgentKnowledgeTextController'
import AgentKnowledgeTextStoreController from './AgentKnowledgeTextStoreController'
import AgentKnowledgeTextListController from './AgentKnowledgeTextListController'
import AgentKnowledgeTextShowController from './AgentKnowledgeTextShowController'
import AgentKnowledgeTextDestroyController from './AgentKnowledgeTextDestroyController'

const Text = {
    AgentKnowledgeTextController: Object.assign(AgentKnowledgeTextController, AgentKnowledgeTextController),
    AgentKnowledgeTextStoreController: Object.assign(AgentKnowledgeTextStoreController, AgentKnowledgeTextStoreController),
    AgentKnowledgeTextListController: Object.assign(AgentKnowledgeTextListController, AgentKnowledgeTextListController),
    AgentKnowledgeTextShowController: Object.assign(AgentKnowledgeTextShowController, AgentKnowledgeTextShowController),
    AgentKnowledgeTextDestroyController: Object.assign(AgentKnowledgeTextDestroyController, AgentKnowledgeTextDestroyController),
}

export default Text