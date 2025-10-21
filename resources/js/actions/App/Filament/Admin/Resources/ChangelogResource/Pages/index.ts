import ListChangelogs from './ListChangelogs'
import CreateChangelog from './CreateChangelog'
import ViewChangelog from './ViewChangelog'
import EditChangelog from './EditChangelog'

const Pages = {
    ListChangelogs: Object.assign(ListChangelogs, ListChangelogs),
    CreateChangelog: Object.assign(CreateChangelog, CreateChangelog),
    ViewChangelog: Object.assign(ViewChangelog, ViewChangelog),
    EditChangelog: Object.assign(EditChangelog, EditChangelog),
}

export default Pages