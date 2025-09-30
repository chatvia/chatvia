import AgentKnowledgeTextController from './AgentKnowledgeTextController'
import AgentKnowledgeTextShowController from './AgentKnowledgeTextShowController'
import AgentKnowledgeTextStoreController from './AgentKnowledgeTextStoreController'
import AgentKnowledgeTextListController from './AgentKnowledgeTextListController'
import AgentKnowledgeTextDestroyController from './AgentKnowledgeTextDestroyController'

const Text = {
    AgentKnowledgeTextController: Object.assign(AgentKnowledgeTextController, AgentKnowledgeTextController),
    AgentKnowledgeTextShowController: Object.assign(AgentKnowledgeTextShowController, AgentKnowledgeTextShowController),
    AgentKnowledgeTextStoreController: Object.assign(AgentKnowledgeTextStoreController, AgentKnowledgeTextStoreController),
    AgentKnowledgeTextListController: Object.assign(AgentKnowledgeTextListController, AgentKnowledgeTextListController),
    AgentKnowledgeTextDestroyController: Object.assign(AgentKnowledgeTextDestroyController, AgentKnowledgeTextDestroyController),
}

export default Text