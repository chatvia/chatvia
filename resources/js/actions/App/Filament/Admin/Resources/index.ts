import AgentResource from './AgentResource'
import BlogResource from './BlogResource'
import LlmResource from './LlmResource'
import MessageResource from './MessageResource'
import SubscriptionResource from './SubscriptionResource'
import TicketResource from './TicketResource'
import UserResource from './UserResource'

const Resources = {
    AgentResource: Object.assign(AgentResource, AgentResource),
    BlogResource: Object.assign(BlogResource, BlogResource),
    LlmResource: Object.assign(LlmResource, LlmResource),
    MessageResource: Object.assign(MessageResource, MessageResource),
    SubscriptionResource: Object.assign(SubscriptionResource, SubscriptionResource),
    TicketResource: Object.assign(TicketResource, TicketResource),
    UserResource: Object.assign(UserResource, UserResource),
}

export default Resources