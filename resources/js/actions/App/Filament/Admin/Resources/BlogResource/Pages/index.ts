import ListBlogs from './ListBlogs'
import CreateBlog from './CreateBlog'
import ViewBlog from './ViewBlog'
import EditBlog from './EditBlog'

const Pages = {
    ListBlogs: Object.assign(ListBlogs, ListBlogs),
    CreateBlog: Object.assign(CreateBlog, CreateBlog),
    ViewBlog: Object.assign(ViewBlog, ViewBlog),
    EditBlog: Object.assign(EditBlog, EditBlog),
}

export default Pages