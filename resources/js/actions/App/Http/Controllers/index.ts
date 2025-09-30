import Widget from './Widget'
import HomeController from './HomeController'
import ConsentController from './ConsentController'
import Landing from './Landing'
import BlogController from './BlogController'
import PostController from './PostController'
import RequestDemoController from './RequestDemoController'
import DocsController from './DocsController'
import Auth from './Auth'
import UseCasesController from './UseCasesController'
import Dashboard from './Dashboard'
import FeaturesController from './FeaturesController'
import KnowledgeFiles from './KnowledgeFiles'
import Members from './Members'
import UsageController from './UsageController'
import SupportController from './SupportController'
import Tickets from './Tickets'
import Settings from './Settings'
import SettingsUsageController from './SettingsUsageController'
import Chat from './Chat'
import Agents from './Agents'
import Billing from './Billing'
import PlansController from './PlansController'
import Ingestion from './Ingestion'

const Controllers = {
    Widget: Object.assign(Widget, Widget),
    HomeController: Object.assign(HomeController, HomeController),
    ConsentController: Object.assign(ConsentController, ConsentController),
    Landing: Object.assign(Landing, Landing),
    BlogController: Object.assign(BlogController, BlogController),
    PostController: Object.assign(PostController, PostController),
    RequestDemoController: Object.assign(RequestDemoController, RequestDemoController),
    DocsController: Object.assign(DocsController, DocsController),
    Auth: Object.assign(Auth, Auth),
    UseCasesController: Object.assign(UseCasesController, UseCasesController),
    Dashboard: Object.assign(Dashboard, Dashboard),
    FeaturesController: Object.assign(FeaturesController, FeaturesController),
    KnowledgeFiles: Object.assign(KnowledgeFiles, KnowledgeFiles),
    Members: Object.assign(Members, Members),
    UsageController: Object.assign(UsageController, UsageController),
    SupportController: Object.assign(SupportController, SupportController),
    Tickets: Object.assign(Tickets, Tickets),
    Settings: Object.assign(Settings, Settings),
    SettingsUsageController: Object.assign(SettingsUsageController, SettingsUsageController),
    Chat: Object.assign(Chat, Chat),
    Agents: Object.assign(Agents, Agents),
    Billing: Object.assign(Billing, Billing),
    PlansController: Object.assign(PlansController, PlansController),
    Ingestion: Object.assign(Ingestion, Ingestion),
}

export default Controllers