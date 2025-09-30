import DashboardController from './DashboardController'
import DashboardStatsController from './DashboardStatsController'

const Dashboard = {
    DashboardController: Object.assign(DashboardController, DashboardController),
    DashboardStatsController: Object.assign(DashboardStatsController, DashboardStatsController),
}

export default Dashboard