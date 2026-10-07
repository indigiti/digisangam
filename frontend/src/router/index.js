import { createRouter, createWebHistory } from 'vue-router'
import AppShell from '../layouts/AppShell.vue'
import AccessPage from '../pages/AccessPage.vue'
import PublicEventPage from '../pages/PublicEventPage.vue'
import PublicDiscoveryPage from '../pages/PublicDiscoveryPage.vue'
import PublicConfirmationPage from '../pages/PublicConfirmationPage.vue'
import DashboardPage from '../pages/DashboardPage.vue'
import EventsPage from '../pages/EventsPage.vue'
import EventCreatePage from '../pages/EventCreatePage.vue'
import EventDetailPage from '../pages/EventDetailPage.vue'
import RegistrationBuilderPage from '../pages/RegistrationBuilderPage.vue'
import AttendeesPage from '../pages/AttendeesPage.vue'
import TicketsPage from '../pages/TicketsPage.vue'
import CommercePage from '../pages/CommercePage.vue'
import AnalyticsPage from '../pages/AnalyticsPage.vue'
import IntelligencePage from '../pages/IntelligencePage.vue'
import OnGroundPage from '../pages/OnGroundPage.vue'
import CommunicationPage from '../pages/CommunicationPage.vue'
import AutomationPage from '../pages/AutomationPage.vue'
import BadgesPage from '../pages/BadgesPage.vue'
import AgendaPage from '../pages/AgendaPage.vue'
import ExhibitorsPage from '../pages/ExhibitorsPage.vue'
import VenuePage from '../pages/VenuePage.vue'
import SettingsPage from '../pages/SettingsPage.vue'
import DeveloperPage from '../pages/DeveloperPage.vue'
import EventBuilderPage from '../pages/EventBuilderPage.vue'
import ReportsPage from '../pages/ReportsPage.vue'
import AccreditationPage from '../pages/AccreditationPage.vue'

export default createRouter({
  history:createWebHistory(import.meta.env.BASE_URL),
  routes:[
    {path:'/access',name:'access',component:AccessPage,meta:{public:true}},
    {path:'/discover',name:'public-discover',component:PublicDiscoveryPage,meta:{public:true}},
    {path:'/e/:id',name:'public-event',component:PublicEventPage,meta:{public:true}},
    {path:'/events/:id/preview',name:'event-preview',component:PublicEventPage},
    {path:'/e/:id/confirmation/:token',name:'public-confirmation',component:PublicConfirmationPage,meta:{public:true}},
    {path:'/',component:AppShell,children:[
      {path:'',name:'dashboard',component:DashboardPage},
      {path:'events',name:'events',component:EventsPage},
      {path:'events/create',name:'event-create',component:EventCreatePage},
      {path:'events/:id',name:'event-detail',component:EventDetailPage},
      {path:'registrations',name:'registrations',component:RegistrationBuilderPage},
      {path:'attendees',name:'attendees',component:AttendeesPage},
      {path:'tickets',name:'tickets',component:TicketsPage},
      {path:'communication',name:'communication',component:CommunicationPage},
      {path:'automation',name:'automation',component:AutomationPage},
      {path:'badges',name:'badges',component:BadgesPage},
      {path:'agenda',name:'agenda',component:AgendaPage},
      {path:'exhibitors',name:'exhibitors',component:ExhibitorsPage},
      {path:'venue',name:'venue',component:VenuePage},
      {path:'onground',name:'onground',component:OnGroundPage},
      {path:'accreditation',name:'accreditation',component:AccreditationPage},
      {path:'commerce',name:'commerce',component:CommercePage},
      {path:'analytics',name:'analytics',component:AnalyticsPage},
      {path:'reports',name:'reports',component:ReportsPage},
      {path:'intelligence',name:'intelligence',component:IntelligencePage},
      {path:'event-builder',name:'event-builder',component:EventBuilderPage},
      {path:'developer',name:'developer',component:DeveloperPage},
      {path:'settings',name:'settings',component:SettingsPage},
    ]}
  ]
})
