import agents from './agents'
import blogs from './blogs'
import changelogs from './changelogs'
import llms from './llms'
import messages from './messages'
import subscriptions from './subscriptions'
import tickets from './tickets'
import users from './users'

const resources = {
    agents: Object.assign(agents, agents),
    blogs: Object.assign(blogs, blogs),
    changelogs: Object.assign(changelogs, changelogs),
    llms: Object.assign(llms, llms),
    messages: Object.assign(messages, messages),
    subscriptions: Object.assign(subscriptions, subscriptions),
    tickets: Object.assign(tickets, tickets),
    users: Object.assign(users, users),
}

export default resources