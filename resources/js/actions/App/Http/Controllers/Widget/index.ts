import WidgetSessionStoreController from './WidgetSessionStoreController'
import WidgetThreadsController from './WidgetThreadsController'
import WidgetThreadsStoreController from './WidgetThreadsStoreController'
import WidgetThreadsDeleteController from './WidgetThreadsDeleteController'
import WidgetThreadMessagesController from './WidgetThreadMessagesController'
import WidgetAgentVerifyController from './WidgetAgentVerifyController'
import WidgetStreamController from './WidgetStreamController'
import WidgetSettingsController from './WidgetSettingsController'
import WidgetLeadFormController from './WidgetLeadFormController'
import WidgetLeadCollectController from './WidgetLeadCollectController'

const Widget = {
    WidgetSessionStoreController: Object.assign(WidgetSessionStoreController, WidgetSessionStoreController),
    WidgetThreadsController: Object.assign(WidgetThreadsController, WidgetThreadsController),
    WidgetThreadsStoreController: Object.assign(WidgetThreadsStoreController, WidgetThreadsStoreController),
    WidgetThreadsDeleteController: Object.assign(WidgetThreadsDeleteController, WidgetThreadsDeleteController),
    WidgetThreadMessagesController: Object.assign(WidgetThreadMessagesController, WidgetThreadMessagesController),
    WidgetAgentVerifyController: Object.assign(WidgetAgentVerifyController, WidgetAgentVerifyController),
    WidgetStreamController: Object.assign(WidgetStreamController, WidgetStreamController),
    WidgetSettingsController: Object.assign(WidgetSettingsController, WidgetSettingsController),
    WidgetLeadFormController: Object.assign(WidgetLeadFormController, WidgetLeadFormController),
    WidgetLeadCollectController: Object.assign(WidgetLeadCollectController, WidgetLeadCollectController),
}

export default Widget