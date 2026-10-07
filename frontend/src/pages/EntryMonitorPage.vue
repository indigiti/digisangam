<script setup>
import {computed,onMounted,onUnmounted,ref,watch} from 'vue'
import {api} from '../services/api'
import {useEventStore} from '../stores/event'

const events=useEventStore()
const data=ref(null),loading=ref(false),error=ref(''),query=ref(''),action=ref('All'),zone=ref('All'),autoRefresh=ref(true)
const eventId=computed(()=>events.currentId)
let timer=null

const recent=computed(()=>{
  const rows=data.value?.recent||[]
  return rows.filter(row=>{
    const hay=[row.attendee?.name,row.attendee?.email,row.attendee?.company,row.attendee?.category,row.zone_id,row.operator?.name].join(' ').toLowerCase()
    if(query.value&&!hay.includes(query.value.toLowerCase()))return false
    if(action.value!=='All'&&(row.action||'enter')!==action.value.toLowerCase())return false
    if(zone.value!=='All'&&(row.zone_id||'venue')!==zone.value)return false
    return true
  })
})
const zones=computed(()=>['All',...Object.keys(data.value?.occupancy||{})])
const entryCount=computed(()=>recent.value.filter(x=>(x.action||'enter')==='enter').length)
const exitCount=computed(()=>recent.value.filter(x=>x.action==='exit').length)

async function load(silent=false){
  if(!eventId.value){data.value=null;return}
  if(!silent)loading.value=true
  error.value=''
  try{data.value=await api.ongroundLive(eventId.value,200)}
  catch(e){error.value=e.message}
  finally{loading.value=false}
}
function startTimer(){
  clearInterval(timer)
  if(autoRefresh.value)timer=setInterval(()=>load(true),5000)
}
watch(eventId,()=>{load();startTimer()})
watch(autoRefresh,startTimer)
onMounted(async()=>{if(!events.loaded)await events.load();await load();startTimer()})
onUnmounted(()=>clearInterval(timer))
function time(value){
  if(!value)return '—'
  return new Intl.DateTimeFormat('en-IN',{hour:'2-digit',minute:'2-digit',second:'2-digit'}).format(new Date(value))
}
</script>

<template><div class="space-y-6">
<section class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
  <div><p class="eyebrow">OnGround</p><h1 class="page-title">Live Visitor Entry Monitor</h1><p class="page-subtitle">{{events.current?.name||'Select an event'}} · live venue entry, exit and current occupancy.</p></div>
  <div class="flex flex-wrap items-center gap-2"><label class="soft-pill flex items-center gap-2"><input v-model="autoRefresh" type="checkbox" class="accent-emerald-600"/> Auto refresh 5s</label><button class="btn-secondary" :disabled="loading||!eventId" @click="load()">{{loading?'Refreshing…':'Refresh now'}}</button><RouterLink to="/onground" class="btn-primary">Open Scanner</RouterLink></div>
</section>

<p v-if="error" class="rounded-xl bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{error}}</p>

<section v-if="!eventId" class="panel p-10 text-center"><p class="text-sm text-slate-500">Select an event to monitor venue entry.</p></section>

<template v-else>
<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <article class="panel p-5"><p class="panel-kicker">Currently inside</p><p class="mt-2 text-3xl font-black text-emerald-600">{{data?.currently_inside||0}}</p><p class="mt-1 text-xs text-slate-400">Latest authoritative access state</p></article>
  <article class="panel p-5"><p class="panel-kicker">Recent entries</p><p class="mt-2 text-3xl font-black">{{entryCount}}</p><p class="mt-1 text-xs text-slate-400">Within loaded activity window</p></article>
  <article class="panel p-5"><p class="panel-kicker">Recent exits</p><p class="mt-2 text-3xl font-black">{{exitCount}}</p><p class="mt-1 text-xs text-slate-400">Within loaded activity window</p></article>
  <article class="panel p-5"><p class="panel-kicker">Last update</p><p class="mt-2 text-lg font-black">{{time(data?.generated_at)}}</p><p class="mt-1 text-xs text-slate-400">Server time</p></article>
</section>

<section class="grid gap-4 lg:grid-cols-[1fr_1.4fr]">
  <article class="panel p-5">
    <p class="panel-kicker">Zone occupancy</p><h2 class="panel-title">Where visitors are now</h2>
    <div class="mt-5 space-y-3">
      <div v-for="(count,name) in data?.occupancy||{}" :key="name" class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3"><span class="text-sm font-semibold">{{name==='venue'?'Main venue':name}}</span><b class="text-lg">{{count}}</b></div>
      <p v-if="!Object.keys(data?.occupancy||{}).length" class="py-6 text-center text-sm text-slate-400">No visitors currently inside.</p>
    </div>
  </article>
  <article class="panel overflow-hidden">
    <div class="border-b border-slate-100 p-5"><p class="panel-kicker">Currently inside</p><h2 class="panel-title">Active visitors</h2></div>
    <div class="max-h-[360px] overflow-auto divide-y divide-slate-100">
      <div v-for="row in data?.inside||[]" :key="row.id" class="flex items-center justify-between gap-4 p-4"><div class="min-w-0"><b class="text-sm">{{row.attendee?.name||row.attendee_id}}</b><p class="mt-1 truncate text-xs text-slate-500">{{row.attendee?.company||'—'}} · {{row.attendee?.category||'—'}}</p></div><div class="text-right"><span class="soft-pill">{{row.zone_id==='venue'?'Main venue':row.zone_id}}</span><p class="mt-1 text-[10px] text-slate-400">since {{time(row.occurred_at||row.entered_at)}}</p></div></div>
      <p v-if="!(data?.inside||[]).length" class="p-8 text-center text-sm text-slate-400">No active visitors inside.</p>
    </div>
  </article>
</section>

<section class="panel overflow-hidden">
  <div class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-[1fr_150px_180px]"><input v-model="query" class="control" placeholder="Search visitor, company, email, category, operator…"/><select v-model="action" class="control"><option>All</option><option>Enter</option><option>Exit</option></select><select v-model="zone" class="control"><option v-for="z in zones" :key="z">{{z}}</option></select></div>
  <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Time</th><th>Visitor</th><th>Category</th><th>Company</th><th>Action</th><th>Zone / Gate</th><th>Operator</th></tr></thead><tbody>
    <tr v-if="!recent.length"><td colspan="7" class="text-center">No access activity matches the filters.</td></tr>
    <tr v-for="row in recent" :key="row.id"><td class="font-mono text-xs">{{time(row.occurred_at||row.entered_at||row.exited_at)}}</td><td><b>{{row.attendee?.name||row.attendee_id}}</b><p>{{row.attendee?.email||''}}</p></td><td><span class="soft-pill">{{row.attendee?.category||'—'}}</span></td><td>{{row.attendee?.company||'—'}}</td><td><span class="status-badge" :class="(row.action||'enter')==='exit'?'bg-slate-100 text-slate-600':'badge-published'">{{(row.action||'enter').toUpperCase()}}</span></td><td>{{row.zone_id==='venue'?'Main venue':row.zone_id}}</td><td>{{row.operator?.name||row.operator_id||'—'}}</td></tr>
  </tbody></table></div>
</section>
</template>
</div></template>
