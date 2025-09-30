import ListSubscriptions from './ListSubscriptions'
import ViewSubscription from './ViewSubscription'
import EditSubscription from './EditSubscription'

const Pages = {
    ListSubscriptions: Object.assign(ListSubscriptions, ListSubscriptions),
    ViewSubscription: Object.assign(ViewSubscription, ViewSubscription),
    EditSubscription: Object.assign(EditSubscription, EditSubscription),
}

export default Pages