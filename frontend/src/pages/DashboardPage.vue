<script setup>
import {computed,onMounted,ref,watch} from 'vue'
import {api} from '../services/api'
import {useAuthStore} from '../stores/auth'
import {useEventStore} from '../stores/event'
import StatCard from '../components/StatCard.vue'
import DsButton from '../components/ui/DsButton.vue'
import LineSpark from '../components/LineSpark.vue'

const auth=useAuthStore(),events=useEventStore(),data=ref(null),error=ref('')
const totalCategory=computed(()=>data.value?.categories?.reduce((n,x)=>n+Number(x.count||0),0)||0)
let requestGeneration=0

async function load(){
  const generation=++requestGeneration
  error.value=''
  data.value=null
  try {
    if(!events.loaded) await events.load()
    if(generation!==requestGeneration) return
    const eventId=events.currentId
    if(!eventId) return
    const response=await api.dashboard(eventId)
    if(generation===requestGeneration && eventId===events.currentId) data.value=response
  }catch(e){
    if(generation===requestGeneration) error.value=e.message
  }
}
onMounted(load)
watch(()=>events.currentId,load)
</script>

<template>
  <div class="space-y-7">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="eyebrow">DIGISANGAM / ORGANIZER STUDIO</p>
        <h1 class="page-title">Good to see you, {{auth.user?.name?.split(' ')[0]||'Organizer'}}.</h1>
        <p class="page-subtitle">Your command center for ticketing, audience growth and unforgettable experiences.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <RouterLink to="/events" class="btn-secondary">All events</RouterLink>
        <RouterLink to="/discover" class="btn-secondary">Explore public site ↗</RouterLink>
        <DsButton to="/events/create"><span aria-hidden="true">＋</span> Create event</DsButton>
      </div>
    </section>

    <div v-if="error" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{error}}</div>

    <section v-if="!events.currentId" class="panel premium-empty">
      <div>
        <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-indigo-50 text-2xl text-indigo-600" aria-hidden="true">✦</div>
        <h2 class="mt-5 text-xl font-extrabold tracking-tight">Bring your first event to life</h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Set up the event, open registrations, and follow every milestone from this workspace.</p>
        <RouterLink to="/events/create" class="btn-primary mt-6">Create your first event</RouterLink>
      </div>
    </section>

    <section v-else-if="!data&&!error" aria-label="Loading event overview" class="space-y-4">
      <div class="h-52 animate-pulse rounded-3xl bg-slate-200/70"></div>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div v-for="item in 4" :key="item" class="h-32 animate-pulse rounded-2xl bg-slate-200/60"></div>
      </div>
    </section>

    <template v-else-if="data">
      <section class="premium-hero p-6 sm:p-8 lg:p-9">
        <div class="relative grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
          <div class="min-w-0 max-w-[780px]">
            <div class="mb-4 flex flex-wrap items-center gap-2">
              <span class="premium-tag"><span class="h-1.5 w-1.5 rounded-full" :class="data.event.status==='Live'?'bg-emerald-300':'bg-indigo-300'"></span>{{data.event.status}}</span>
              <span class="premium-tag">{{data.event.date||'Date to be confirmed'}}</span>
            </div>
            <p class="text-[11px] font-bold uppercase tracking-[.16em] text-indigo-100/65">Your active event</p>
            <h2 class="mt-2 max-w-2xl text-[clamp(1.6rem,3vw,2.65rem)] font-extrabold leading-[1.1] tracking-[-.045em] text-white">{{data.event.name}}</h2>
            <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-indigo-100/80">
              <span>{{data.event.location||'Venue not set'}}</span>
              <span class="text-indigo-300/50" aria-hidden="true">•</span>
              <span>Managed in DigiSangam</span>
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
              <RouterLink :to="'/events/'+data.event.id" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-xs font-extrabold text-indigo-950 transition hover:bg-indigo-50">Manage event <span class="ml-2" aria-hidden="true">↗</span></RouterLink>
              <RouterLink to="/attendees" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-white/20">View attendees</RouterLink>
            </div>
          </div>
          <div class="flex items-center gap-4 rounded-2xl border border-white/15 bg-white/[.065] p-4 backdrop-blur-sm lg:min-w-[225px] lg:flex-col lg:gap-3 lg:p-6">
            <div class="grid h-20 w-20 shrink-0 place-items-center rounded-full p-2 sm:h-24 sm:w-24" :style="{background:'conic-gradient(#b3adff '+Math.min(100,Math.max(0,Number(data.event.progress||0)))+'%, rgba(255,255,255,.14) 0)'}">
              <div class="grid h-full w-full place-items-center rounded-full bg-[#222a50] text-lg font-extrabold text-white sm:text-xl">{{Math.min(100,Math.max(0,Number(data.event.progress||0)))}}%</div>
            </div>
            <div class="text-left lg:text-center">
              <p class="text-sm font-bold text-white">Setup readiness</p>
              <p class="mt-1 text-xs leading-5 text-indigo-200/75">Complete event configuration before you go live.</p>
            </div>
          </div>
        </div>
      </section>

      <section aria-label="Key event metrics" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard v-for="stat in data.stats||[]" :key="stat.label" v-bind="stat" />
      </section>

      <section class="grid gap-5 xl:grid-cols-[minmax(0,1.65fr)_minmax(300px,.85fr)]">
        <article class="panel overflow-hidden p-5 sm:p-6 lg:p-7">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="panel-kicker">Audience growth</p>
              <h2 class="mt-1 text-xl font-extrabold tracking-[-.03em]">Registration momentum</h2>
              <p class="mt-1 premium-section-caption">Recorded registrations during the last 14 days</p>
            </div>
            <RouterLink to="/analytics" class="btn-secondary">Explore analytics</RouterLink>
          </div>
          <div class="mt-5 rounded-xl bg-slate-50/60 p-2 sm:p-4"><LineSpark :values="data.trend||[]"/></div>
        </article>

        <article class="panel p-5 sm:p-6 lg:p-7">
          <p class="panel-kicker">Attendee mix</p>
          <div class="mt-1 flex items-baseline justify-between gap-3">
            <h2 class="text-xl font-extrabold tracking-[-.03em]">Audience categories</h2>
            <span class="text-xs font-bold text-slate-400">{{totalCategory}} attendees</span>
          </div>
          <p class="mt-1 premium-section-caption">Live composition of registered audiences</p>
          <div v-if="data.categories?.length" class="mt-7 space-y-5">
            <div v-for="category in data.categories" :key="category.label">
              <div class="mb-2 flex items-center justify-between gap-3 text-xs">
                <span class="font-semibold text-slate-700">{{category.label}}</span>
                <span class="font-bold text-slate-700">{{category.count}} <span class="font-medium text-slate-400">/ {{category.value}}%</span></span>
              </div>
              <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full transition-[width]" :style="{width:Math.min(100,Math.max(0,Number(category.value||0)))+'%',backgroundColor:category.color||'#6356dc'}"></div>
              </div>
            </div>
          </div>
          <div v-else class="premium-empty min-h-48">
            <p class="text-sm leading-6 text-slate-500">Category distribution appears once attendees register.</p>
          </div>
        </article>
      </section>

      <section>
        <div class="mb-4">
          <p class="panel-kicker">Event operations</p>
          <h2 class="mt-1 text-xl font-extrabold tracking-[-.025em]">Keep everything moving</h2>
          <p class="mt-1 premium-section-caption">Go straight to the tools your team uses most.</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <RouterLink v-for="action in [
            {to:'/registrations',number:'01',title:'Registration',caption:'Forms, approvals and invitations'},
            {to:'/tickets',number:'02',title:'Ticketing',caption:'Inventory, pricing and availability'},
            {to:'/onground',number:'03',title:'OnGround',caption:'Access control and offline scans'},
            {to:'/communication',number:'04',title:'Communications',caption:'Campaigns and attendee updates'}
          ]" :key="action.to" :to="action.to" class="premium-action group">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-indigo-50 text-xs font-black text-indigo-600">{{action.number}}</span>
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-extrabold text-slate-800">{{action.title}}</span>
              <span class="mt-1 block text-xs leading-4 text-slate-500">{{action.caption}}</span>
            </span>
            <span class="text-base text-slate-400 group-hover:text-indigo-600" aria-hidden="true">↗</span>
          </RouterLink>
        </div>
      </section>
    </template>
  </div>
</template>
