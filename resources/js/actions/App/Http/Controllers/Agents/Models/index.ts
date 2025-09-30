import AgentModelsListController from './AgentModelsListController'
import AgentModelsShowController from './AgentModelsShowController'
import AgentModelsStoreSettings from './AgentModelsStoreSettings'
import AgentModelsToggleController from './AgentModelsToggleController'

const Models = {
    AgentModelsListController: Object.assign(AgentModelsListController, AgentModelsListController),
    AgentModelsShowController: Object.assign(AgentModelsShowController, AgentModelsShowController),
    AgentModelsStoreSettings: Object.assign(AgentModelsStoreSettings, AgentModelsStoreSettings),
    AgentModelsToggleController: Object.assign(AgentModelsToggleController, AgentModelsToggleController),
}

export default Models