<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '../services/api'

const eventId=ref('evt_001'),data=ref(null),loading=ref(true),error=ref('')
const question=ref('What needs my attention right now?'),asking=ref(false),messages=ref([])
const analysis=computed(()=>data.value?.analysis||{})
const metrics=computed(()=>data.value?.metrics||{})
const hotLeads=computed(()=>analysis.value.lead_scores?.filter(x=>x.ai_band==='hot')||[])
const money=n=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:0}).format(Number(n||0))

async function load(){
  loading.value=true;error.value=''
  try{data.value=await api.intelligenceOverview(eventId.value)}
  catch(e){error.value=e.message}
  finally{loading.value=false}
}
async function ask(text=question.value){
  const q=String(text||'').trim()
  if(!q||asking.value)return
  messages.value.push({role:'user',text:q});question.value='';asking.value=true
  try{
    const r=await api.copilot({event_id:eventId.value,question:q})
    messages.value.push({role:'assistant',text:r.answer,actions:r.suggested_actions||[]})
  }catch(e){messages.value.push({role:'assistant',text:e.message,actions:[]})}
  finally{asking.value=false}
}
onMounted(load)
</script>

<template><div class="space-y-6">
<section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
  <div><p class="eyebrow">Phase 3 · Intelligence</p><h1 class="page-title">Event Intelligence Center</h1><p class="page-subtitle">Forecasting, anomaly detection, crowd intelligence, lead scoring, recommendations and Organizer Copilot.</p></div>
  <div class="flex gap-2"><input v-model="eventId" class="control w-40" placeholder="Event ID"/><button class="btn-secondary" @click="load">Refresh</button></div>
</section>

<div v-if="loading" class="grid grid-cols-2 gap-4 lg:grid-cols-4"><div v-for="i in 8" :key="i" class="panel h-28 animate-pulse"></div></div>
<p v-else-if="error" class="rounded-2xl bg-rose-50 p-5 text-sm font-bold text-rose-700">{{error}}</p>

<template v-else-if="data">
<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <article class="stat-card stat-blue"><p class="panel-kicker">Registrations</p><p class="mt-2 text-3xl font-black">{{metrics.registrations||0}}</p><p class="mt-1 text-xs text-slate-500">{{metrics.confirmed||0}} confirmed</p></article>
  <article class="stat-card stat-mint"><p class="panel-kicker">7-day projection</p><p class="mt-2 text-3xl font-black">{{analysis.forecast?.registrations_7d||0}}</p><p class="mt-1 text-xs text-slate-500">Velocity {{analysis.forecast?.daily_velocity||0}} / day · {{analysis.forecast?.confidence}} confidence</p></article>
  <article class="stat-card stat-cyan"><p class="panel-kicker">Expected footfall</p><p class="mt-2 text-3xl font-black">{{analysis.forecast?.expected_footfall||0}}</p><p class="mt-1 text-xs text-slate-500">{{metrics.checked_in||0}} already checked in</p></article>
  <article class="stat-card stat-rose"><p class="panel-kicker">Paid revenue</p><p class="mt-2 text-3xl font-black">{{money(metrics.paid_revenue)}}</p><p class="mt-1 text-xs text-slate-500">{{((metrics.payment_failure_rate||0)*100).toFixed(1)}}% payment failure rate</p></article>
</section>

<section class="grid gap-5 xl:grid-cols-[1.1fr_.9fr]">
  <article class="panel p-6">
    <div class="flex items-start justify-between gap-4"><div><p class="panel-kicker">Decision feed</p><h2 class="panel-title">AI operational insights</h2></div><span class="soft-pill">{{analysis.insights?.length||0}} signals</span></div>
    <div class="mt-5 space-y-3">
      <div v-for="(item,i) in analysis.insights" :key="i" class="rounded-2xl border p-4" :class="item.priority==='critical'?'border-rose-200 bg-rose-50':item.priority==='high'?'border-amber-200 bg-amber-50':'border-slate-200 bg-slate-50'">
        <div class="flex items-center justify-between gap-4"><b class="text-sm">{{item.title}}</b><span class="status-badge" :class="item.priority==='critical'?'bg-rose-100 text-rose-700':item.priority==='high'?'bg-amber-100 text-amber-700':'bg-white text-slate-500'">{{item.priority}}</span></div>
        <p class="mt-2 text-sm leading-6 text-slate-600">{{item.message}}</p>
      </div>
    </div>
  </article>

  <article class="panel p-6">
    <div class="flex items-start justify-between gap-4"><div><p class="panel-kicker">Event Graph</p><h2 class="panel-title">Connected operating model</h2></div><span class="soft-pill">{{data.graph.edges}} edges</span></div>
    <div class="mt-5 grid grid-cols-2 gap-3">
      <div v-for="(count,name) in data.graph.nodes" :key="name" class="rounded-2xl bg-slate-50 p-4"><p class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{name}}</p><p class="mt-1 text-2xl font-black">{{count}}</p></div>
    </div>
    <p class="mt-4 text-xs text-slate-400">Generated {{data.graph.generated_at}}</p>
  </article>
</section>

<section class="panel p-6">
  <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><p class="panel-kicker">Venue intelligence</p><h2 class="panel-title">Live Digital Twin · zones</h2></div><span class="soft-pill">capacity + occupancy + action</span></div>
  <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <article v-for="zone in analysis.crowd" :key="zone.zone_id" class="rounded-2xl border border-slate-200 p-5">
      <div class="flex items-start justify-between gap-3"><div><h3 class="font-black">{{zone.name}}</h3><p class="mt-1 text-xs text-slate-400">{{zone.occupancy}} / {{zone.capacity}} people</p></div><span class="status-badge" :class="zone.risk==='critical'?'bg-rose-100 text-rose-700':zone.risk==='high'?'bg-amber-100 text-amber-700':zone.risk==='moderate'?'bg-cyan-100 text-cyan-700':'badge-published'">{{zone.risk}}</span></div>
      <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-indigo-600" :style="{width:Math.min(100,zone.occupancy_pct)+'%'}"></div></div>
      <div class="mt-2 flex justify-between text-xs"><span class="text-slate-400">Occupancy</span><b>{{zone.occupancy_pct}}%</b></div>
      <p class="mt-4 text-xs leading-5 text-slate-500">{{zone.action}}</p>
    </article>
    <div v-if="!analysis.crowd?.length" class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-400">No venue zones configured.</div>
  </div>
</section>

<section class="grid gap-5 xl:grid-cols-[1fr_420px]">
  <article class="panel overflow-hidden">
    <div class="border-b p-5"><div class="flex justify-between gap-3"><div><p class="panel-kicker">Exhibitor intelligence</p><h2 class="panel-title">AI-ranked leads</h2></div><span class="soft-pill">{{hotLeads.length}} hot</span></div></div>
    <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Lead</th><th>Exhibitor</th><th>Attendee</th><th>Intent</th><th>AI score</th><th>Band</th></tr></thead><tbody>
      <tr v-if="!analysis.lead_scores?.length"><td colspan="6" class="text-center">No lead signals yet.</td></tr>
      <tr v-for="lead in analysis.lead_scores" :key="lead.id"><td class="font-mono">{{lead.id}}</td><td>{{lead.exhibitor_id}}</td><td>{{lead.attendee_id}}</td><td>{{lead.intent}}</td><td><b>{{lead.ai_score}}</b>/100</td><td><span class="status-badge" :class="lead.ai_band==='hot'?'bg-rose-100 text-rose-700':lead.ai_band==='warm'?'bg-amber-100 text-amber-700':'bg-slate-100 text-slate-600'">{{lead.ai_band}}</span></td></tr>
    </tbody></table></div>
  </article>

  <aside class="panel flex min-h-[520px] flex-col overflow-hidden">
    <div class="border-b bg-slate-950 p-5 text-white"><p class="text-[10px] font-black uppercase tracking-[.18em] text-cyan-300">Organizer Copilot</p><h2 class="mt-1 text-lg font-black">Ask DigiSangam</h2><p class="mt-1 text-xs text-slate-400">Grounded only in this event’s current Event Graph.</p></div>
    <div class="flex-1 space-y-3 overflow-y-auto p-4">
      <div v-if="!messages.length" class="space-y-2"><button v-for="prompt in ['What needs my attention right now?','How are registrations doing?','Are any zones getting crowded?','Which exhibitor leads should we prioritize?','What is our payment health?']" :key="prompt" class="w-full rounded-xl border border-slate-200 p-3 text-left text-xs font-semibold hover:bg-slate-50" @click="ask(prompt)">{{prompt}}</button></div>
      <div v-for="(m,i) in messages" :key="i" class="rounded-2xl p-3 text-sm leading-6" :class="m.role==='user'?'ml-8 bg-indigo-600 text-white':'mr-5 bg-slate-50 text-slate-700'">
        {{m.text}}
        <ul v-if="m.actions?.length" class="mt-2 list-disc pl-5 text-xs opacity-80"><li v-for="a in m.actions" :key="a">{{a}}</li></ul>
      </div>
      <div v-if="asking" class="mr-10 animate-pulse rounded-2xl bg-slate-50 p-3 text-sm text-slate-400">Analyzing Event Graph…</div>
    </div>
    <form class="border-t p-3" @submit.prevent="ask()"><div class="flex gap-2"><input v-model="question" class="control" placeholder="Ask about registrations, crowd, revenue, leads…"/><button class="btn-primary" :disabled="asking">Ask</button></div></form>
  </aside>
</section>

<section v-if="analysis.anomalies?.length" class="panel p-6"><p class="panel-kicker">Anomaly detection</p><h2 class="panel-title">Detected deviations</h2><div class="mt-4 grid gap-3 md:grid-cols-2"><div v-for="(a,i) in analysis.anomalies" :key="i" class="rounded-2xl border border-amber-200 bg-amber-50 p-4"><div class="flex justify-between gap-3"><b class="text-sm">{{a.type.replaceAll('_',' ')}}</b><span class="status-badge bg-white text-amber-700">{{a.severity}}</span></div><p class="mt-2 text-xs leading-5 text-amber-900/70">{{a.message}}</p></div></div></section>
</template>
</div></template>
