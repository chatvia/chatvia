import ListLlms from './ListLlms'
import CreateLlm from './CreateLlm'
import ViewLlm from './ViewLlm'
import EditLlm from './EditLlm'

const Pages = {
    ListLlms: Object.assign(ListLlms, ListLlms),
    CreateLlm: Object.assign(CreateLlm, CreateLlm),
    ViewLlm: Object.assign(ViewLlm, ViewLlm),
    EditLlm: Object.assign(EditLlm, EditLlm),
}

export default Pages