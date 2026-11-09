import { createRouter, createWebHistory } from 'vue-router'
import AppShell from '../layouts/AppShell.vue'
const AccessPage = () => import('../pages/AccessPage.vue')
const PublicEventPage = () => import('../pages/PublicEventPage.vue')
const PublicDiscoveryPage = () => import('../pages/PublicDiscoveryPage.vue')
const PublicConfirmationPage = () => import('../pages/PublicConfirmationPage.vue')
const DashboardPage = () => import('../pages/DashboardPage.vue')
const EventsPage = () => import('../pages/EventsPage.vue')
const EventCreatePage = () => import('../pages/EventCreatePage.vue')
const EventDetailPage = () => import('../pages/EventDetailPage.vue')
const RegistrationBuilderPage = () => import('../pages/RegistrationBuilderPage.vue')
const AttendeesPage = () => import('../pages/AttendeesPage.vue')
const TicketsPage = () => import('../pages/TicketsPage.vue')
const CommercePage = () => import('../pages/CommercePage.vue')
const AnalyticsPage = () => import('../pages/AnalyticsPage.vue')
const IntelligencePage = () => import('../pages/IntelligencePage.vue')
import OnGroundPage from '../pages/OnGroundPage.vue'
const EntryMonitorPage = () => import('../pages/EntryMonitorPage.vue')
const CommunicationPage = () => import('../pages/CommunicationPage.vue')
const AutomationPage = () => import('../pages/AutomationPage.vue')
const BadgesPage = () => import('../pages/BadgesPage.vue')
const AgendaPage = () => import('../pages/AgendaPage.vue')
const ExhibitorsPage = () => import('../pages/ExhibitorsPage.vue')
const VenuePage = () => import('../pages/VenuePage.vue')
const SettingsPage = () => import('../pages/SettingsPage.vue')
const DeveloperPage = () => import('../pages/DeveloperPage.vue')
const EventBuilderPage = () => import('../pages/EventBuilderPage.vue')
const ReportsPage = () => import('../pages/ReportsPage.vue')
const AccreditationPage = () => import('../pages/AccreditationPage.vue')

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
      {path:'entry-monitor',name:'entry-monitor',component:EntryMonitorPage},
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
