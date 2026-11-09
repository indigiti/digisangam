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
<template><div class="space-y-6"><section><p class="eyebrow">Analytics</p><h1 class="page-title">Analytics & Reports</h1><p class="page-subtitle">Calculated from the selected event’s real registration, payment and check-in records.</p></section>
<p v-if="error" class="rounded-xl bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{error}}</p>
<section v-if="!events.currentId" class="panel p-10 text-center"><p class="text-sm text-slate-500">Select or create an event to view analytics.</p></section>
<template v-else-if="data">
<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><StatCard v-for="s in data.stats" :key="s.label" v-bind="s"/></section>
<section class="grid gap-5 xl:grid-cols-[1.5fr_.8fr]"><article class="panel p-5"><p class="panel-kicker">Growth</p><h2 class="panel-title">Registrations · last 14 days</h2><LineSpark :values="data.trend"/></article><article class="panel p-5"><p class="panel-kicker">Conversion</p><h2 class="panel-title">Operational rates</h2><div class="mt-6 space-y-5"><div><div class="flex justify-between text-xs"><span>Confirmation rate</span><b>{{metrics.confirmation_rate||0}}%</b></div><div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" :style="{width:(metrics.confirmation_rate||0)+'%'}"></div></div></div><div><div class="flex justify-between text-xs"><span>Check-in rate</span><b>{{metrics.checkin_rate||0}}%</b></div><div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-full rounded-full bg-cyan-500" :style="{width:(metrics.checkin_rate||0)+'%'}"></div></div></div></div></article></section>
<section class="panel overflow-hidden"><div class="border-b p-5"><p class="panel-kicker">Audience</p><h2 class="panel-title">Category distribution</h2></div><div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Category</th><th>Attendees</th><th>Share</th></tr></thead><tbody><tr v-if="!data.categories.length"><td colspan="3" class="text-center">No registrations yet.</td></tr><tr v-for="c in data.categories" :key="c.label"><td><b>{{c.label}}</b></td><td>{{c.count}}</td><td>{{c.value}}%</td></tr></tbody></table></div></section>
</template></div></template>
