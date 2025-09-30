import AgentSecurityController from './AgentSecurityController'
import AgentSecurityUpdateController from './AgentSecurityUpdateController'

const Security = {
    AgentSecurityController: Object.assign(AgentSecurityController, AgentSecurityController),
    AgentSecurityUpdateController: Object.assign(AgentSecurityUpdateController, AgentSecurityUpdateController),
}

export default Security