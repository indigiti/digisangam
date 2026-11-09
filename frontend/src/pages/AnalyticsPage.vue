<script setup>
import {computed,onMounted,ref,watch} from 'vue'
import {api} from '../services/api'
import {useEventStore} from '../stores/event'
import StatCard from '../components/StatCard.vue'
import LineSpark from '../components/LineSpark.vue'

const events=useEventStore(),data=ref(null),error=ref('')
const metrics=computed(()=>data.value?.metrics||{})
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
onMounted(load);watch(()=>events.currentId,load)
</script>

<template>
  <div class="space-y-7">
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
      <div>
        <p class="eyebrow">Intelligence / Event Insights</p>
        <h1 class="page-title">Event analytics</h1>
        <p class="page-subtitle">Decision-ready performance, calculated from real registrations, payments and check-ins.</p>
      </div>
      <RouterLink to="/reports" class="btn-secondary">Explore reports ↗</RouterLink>
    </header>
    <p v-if="error" role="alert" class="rounded-xl border border-rose-100 bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{error}}</p>
    <section v-if="!events.currentId" class="panel premium-empty">
      <div><h2 class="text-lg font-extrabold">Select an event to see its analytics</h2><p class="mt-2 text-sm leading-6 text-slate-500">Analysis always stays scoped to your active event.</p><RouterLink to="/events" class="btn-primary mt-5">Choose an event</RouterLink></div>
    </section>
    <section v-else-if="!data&&!error" class="space-y-4" aria-label="Loading analytics">
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><div v-for="item in 4" :key="item" class="h-28 animate-pulse rounded-2xl bg-slate-200/60"></div></div>
      <div class="h-72 animate-pulse rounded-2xl bg-slate-200/50"></div>
    </section>
    <template v-else-if="data">
      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Event key performance indicators"><StatCard v-for="metric in data.stats||[]" :key="metric.label" v-bind="metric"/></section>
      <section class="grid gap-5 xl:grid-cols-[minmax(0,1.65fr)_minmax(300px,.85fr)]">
        <article class="panel p-5 sm:p-7">
          <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div><p class="panel-kicker">Audience acquisition</p><h2 class="mt-1 text-xl font-extrabold tracking-tight">Registration activity</h2><p class="mt-1 premium-section-caption">14-day registration trend from your event records</p></div>
            <RouterLink to="/registrations" class="btn-secondary">Registration setup</RouterLink>
          </div>
          <div class="mt-5 rounded-xl bg-slate-50/60 p-3 sm:p-5"><LineSpark :values="data.trend||[]"/></div>
        </article>
        <article class="panel p-5 sm:p-7">
          <p class="panel-kicker">Registration funnel</p><h2 class="mt-1 text-xl font-extrabold tracking-tight">Operational conversion</h2>
          <p class="mt-1 premium-section-caption">Rates from confirmed records and successful check-ins</p>
          <div class="mt-7 space-y-6">
            <div v-for="metric in [
              {label:'Confirmation rate',value:Number(metrics.confirmation_rate||0),color:'#6255df'},
              {label:'Check-in rate',value:Number(metrics.checkin_rate||0),color:'#16a485'}
            ]" :key="metric.label">
              <div class="mb-2 flex items-baseline justify-between gap-4 text-xs">
                <span class="font-semibold text-slate-600">{{metric.label}}</span>
                <span class="text-base font-extrabold tracking-tight">{{metric.value}}%</span>
              </div>
              <div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full transition-[width]" :style="{width:Math.max(0,Math.min(100,metric.value))+'%',backgroundColor:metric.color}"></div></div>
            </div>
          </div>
        </article>
      </section>
      <section class="panel overflow-hidden">
        <div class="flex flex-col justify-between gap-2 border-b border-slate-100 p-5 sm:flex-row sm:items-end sm:p-6">
          <div><p class="panel-kicker">Audience intelligence</p><h2 class="mt-1 text-xl font-extrabold tracking-tight">Category distribution</h2><p class="mt-1 premium-section-caption">Who is registering for this event</p></div>
          <RouterLink to="/attendees" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">Open attendee directory →</RouterLink>
        </div>
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead><tr><th scope="col">Attendee category</th><th scope="col">Registrations</th><th scope="col">Audience share</th></tr></thead>
            <tbody>
              <tr v-if="!data.categories?.length"><td colspan="3" class="py-12 text-center text-sm text-slate-500">No registrations yet. Categories will appear when attendees register.</td></tr>
              <tr v-for="category in data.categories||[]" :key="category.label">
                <td><b>{{category.label}}</b></td>
                <td>{{category.count}}</td>
                <td><div class="flex items-center gap-3"><div class="h-2 w-24 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-indigo-500" :style="{width:Math.max(0,Math.min(100,Number(category.value||0)))+'%'}"></div></div><span>{{category.value}}%</span></div></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>
