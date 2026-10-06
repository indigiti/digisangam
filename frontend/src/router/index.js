import { createRouter, createWebHistory } from 'vue-router'
import AppShell from '../layouts/AppShell.vue'
import AccessPage from '../pages/AccessPage.vue'
import DashboardPage from '../pages/DashboardPage.vue'
import EventsPage from '../pages/EventsPage.vue'
import EventCreatePage from '../pages/EventCreatePage.vue'
import RegistrationBuilderPage from '../pages/RegistrationBuilderPage.vue'
import AttendeesPage from '../pages/AttendeesPage.vue'
import TicketsPage from '../pages/TicketsPage.vue'
import CommercePage from '../pages/CommercePage.vue'
import AnalyticsPage from '../pages/AnalyticsPage.vue'
import SettingsPage from '../pages/SettingsPage.vue'
import PlaceholderPage from '../pages/PlaceholderPage.vue'

export default createRouter({
  history:createWebHistory(),
  routes:[
    {path:'/access',name:'access',component:AccessPage,meta:{public:true}},
    {path:'/',component:AppShell,children:[
      {path:'',name:'dashboard',component:DashboardPage},
      {path:'events',name:'events',component:EventsPage},
      {path:'events/create',name:'event-create',component:EventCreatePage},
      {path:'registrations',name:'registrations',component:RegistrationBuilderPage},
      {path:'attendees',name:'attendees',component:AttendeesPage},
      {path:'tickets',name:'tickets',component:TicketsPage},
      {path:'commerce',name:'commerce',component:CommercePage},
      {path:'analytics',name:'analytics',component:AnalyticsPage},
      {path:'onground',name:'onground',component:PlaceholderPage,props:{title:'OnGround',description:'Offline-first onsite operations are scheduled for Phase 2.'}},
      {path:'communication',name:'communication',component:PlaceholderPage,props:{title:'Communication',description:'Campaigns, templates and provider adapters are scheduled for Phase 2.'}},
      {path:'settings',name:'settings',component:SettingsPage},
    ]}
  ]
})
