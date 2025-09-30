import AgentLeadsController from './AgentLeadsController'
import AgentLeadsDeleteController from './AgentLeadsDeleteController'
import AgentLeadsUpdateSettingsController from './AgentLeadsUpdateSettingsController'
import AgentLeadsExportController from './AgentLeadsExportController'

const Leads = {
    AgentLeadsController: Object.assign(AgentLeadsController, AgentLeadsController),
    AgentLeadsDeleteController: Object.assign(AgentLeadsDeleteController, AgentLeadsDeleteController),
    AgentLeadsUpdateSettingsController: Object.assign(AgentLeadsUpdateSettingsController, AgentLeadsUpdateSettingsController),
    AgentLeadsExportController: Object.assign(AgentLeadsExportController, AgentLeadsExportController),
}

export default Leads