import ListUsers from './ListUsers'
import ViewUser from './ViewUser'
import EditUser from './EditUser'

const Pages = {
    ListUsers: Object.assign(ListUsers, ListUsers),
    ViewUser: Object.assign(ViewUser, ViewUser),
    EditUser: Object.assign(EditUser, EditUser),
}

export default Pages