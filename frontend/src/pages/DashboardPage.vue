<script setup>
import {computed,onMounted,ref,watch} from 'vue'
import {api} from '../services/api'
import {useAuthStore} from '../stores/auth'
import {useEventStore} from '../stores/event'
import StatCard from '../components/StatCard.vue'
import LineSpark from '../components/LineSpark.vue'

const auth=useAuthStore(),events=useEventStore(),data=ref(null),error=ref('')
const totalCategory=computed(()=>data.value?.categories?.reduce((n,x)=>n+Number(x.count||0),0)||0)

async function load(){
  error.value=''
  if(!events.loaded) await events.load()
  if(!events.currentId){data.value=null;return}
  try{data.value=await api.dashboard(events.currentId)}catch(e){error.value=e.message}
}
onMounted(load)
watch(()=>events.currentId,load)
</script>
<template><div class="space-y-6">
<section class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end"><div><p class="eyebrow">Command Center</p><h1 class="page-title">Welcome back, {{auth.user?.name||'Administrator'}}</h1><p class="page-subtitle">Live data for the currently selected event.</p></div><RouterLink to="/events/create" class="btn-primary">+ Create Event</RouterLink></section>
<p v-if="error" class="rounded-xl bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{error}}</p>
<section v-if="!events.currentId" class="panel p-10 text-center"><h2 class="text-xl font-black">Create your first event</h2><p class="mt-2 text-sm text-slate-500">Dashboard metrics appear after a real event has been created.</p><RouterLink to="/events/create" class="btn-primary mt-5">Create Event</RouterLink></section>
<template v-else-if="data">
<section class="panel overflow-hidden p-0"><div class="grid gap-0 lg:grid-cols-[1.6fr_.8fr]"><div class="p-5 sm:p-6"><div class="flex items-center justify-between gap-4"><div><div class="mb-1 inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">{{data.event.status}}</div><h2 class="text-lg font-bold">{{data.event.name}}</h2><p class="mt-1 text-sm text-slate-500">{{data.event.date}} · {{data.event.location||'Location not set'}}</p></div><RouterLink :to="'/events/'+data.event.id" class="btn-secondary">Manage</RouterLink></div></div><div class="border-t border-slate-200 bg-slate-50/60 p-5 lg:border-l lg:border-t-0"><p class="text-xs font-bold uppercase tracking-widest text-slate-400">Setup readiness</p><div class="mt-3 flex items-center gap-3"><div class="h-2 flex-1 rounded-full bg-slate-200"><div class="h-full rounded-full bg-emerald-500" :style="{width:(data.event.progress||0)+'%'}"></div></div><b class="text-sm">{{data.event.progress||0}}%</b></div></div></div></section>
<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><StatCard v-for="s in data.stats" :key="s.label" v-bind="s"/></section>
<section class="grid gap-5 xl:grid-cols-[1.55fr_.75fr]"><article class="panel p-5 sm:p-6"><p class="panel-kicker">Registrations</p><h2 class="panel-title">Last 14 days</h2><LineSpark :values="data.trend"/></article><article class="panel p-5 sm:p-6"><p class="panel-kicker">Audience</p><h2 class="panel-title">Attendee categories</h2><div v-if="data.categories.length" class="mt-5 space-y-3"><div v-for="c in data.categories" :key="c.label"><div class="mb-1 flex justify-between text-xs"><span>{{c.label}}</span><b>{{c.count}} · {{c.value}}%</b></div><div class="h-2 rounded-full bg-slate-100"><div class="h-full rounded-full" :style="{width:c.value+'%',background:c.color}"></div></div></div></div><p v-else class="mt-6 text-sm text-slate-400">No attendee registrations yet.</p></article></section>
</template>
</div></template>
