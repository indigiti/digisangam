<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { api } from '../services/api'
import { useEventStore } from '../stores/event'
import DsButton from '../components/ui/DsButton.vue'
import DsStatusBadge from '../components/ui/DsStatusBadge.vue'
import DsEmptyState from '../components/ui/DsEmptyState.vue'

const eventStore=useEventStore()
const rows=ref([]),active=ref('All'),search=ref(''),order=ref('upcoming'),limit=ref(24),error=ref(''),loading=ref(true)
const tabs=['All','Draft','Published','Live','Completed','Archived']
const countFor=status=>status==='All'?rows.value.length:rows.value.filter(row=>row.status===status).length
const filtered=computed(()=>{
  const needle=search.value.trim().toLowerCase()
  const list=rows.value.filter(row=>{
    if(active.value!=='All'&&row.status!==active.value)return false
    if(!needle)return true
    return [row.name,row.category,row.location,row.type].some(value=>String(value||'').toLowerCase().includes(needle))
  })
  return [...list].sort((a,b)=>{
    if(order.value==='name')return String(a.name).localeCompare(String(b.name))
    if(order.value==='newest')return String(b.created_at||b.start_date||'').localeCompare(String(a.created_at||a.start_date||''))
    return String(a.start_date||'9999-99-99').localeCompare(String(b.start_date||'9999-99-99'))
  })
})
const visible=computed(()=>filtered.value.slice(0,limit.value))
watch([active,search,order],()=>{limit.value=24})
onMounted(async()=>{
  try{
    rows.value=await api.events()
    eventStore.events=rows.value
  }catch(e){error.value=e.message||'Events could not be loaded.'}
  finally{loading.value=false}
})
</script>

<template>
  <div class="space-y-7">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="eyebrow">Workspace / Event portfolio</p>
        <h1 class="page-title">Every event. One workspace.</h1>
        <p class="page-subtitle">Create, manage and monitor your real events across every stage.</p>
      </div>
      <DsButton to="/events/create">＋ Create event</DsButton>
    </section>

    <div v-if="error" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{error}}</div>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Event portfolio statistics">
      <article v-for="item in [
        {label:'All events',value:countFor('All'),tone:'bg-indigo-500'},
        {label:'Live now',value:countFor('Live'),tone:'bg-emerald-500'},
        {label:'Published',value:countFor('Published'),tone:'bg-sky-500'},
        {label:'In preparation',value:countFor('Draft'),tone:'bg-amber-500'},
      ]" :key="item.label" class="panel flex items-center gap-4 p-4 sm:p-5">
        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-50">
          <span class="h-2.5 w-2.5 rounded-full" :class="item.tone"></span>
        </div>
        <div>
          <p class="text-[11px] font-semibold text-slate-500">{{item.label}}</p>
          <p class="mt-1 text-2xl font-extrabold tracking-[-.04em]">{{item.value}}</p>
        </div>
      </article>
    </section>

    <section class="panel overflow-hidden">
      <div class="space-y-4 border-b border-slate-100 p-4 sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="min-w-0">
            <h2 class="text-lg font-extrabold tracking-tight">Your event portfolio</h2>
            <p class="premium-section-caption mt-1">Showing {{filtered.length}} matching event{{filtered.length===1?'':'s'}}</p>
          </div>
          <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
            <label class="relative min-w-0 flex-1 sm:w-[240px]">
              <span class="sr-only">Search events</span>
              <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
              <input v-model="search" type="search" aria-label="Search events" placeholder="Search your events" class="control w-full pl-10"/>
            </label>
            <label class="min-w-0">
              <span class="sr-only">Sort events</span>
              <select v-model="order" class="control w-full sm:w-[150px]" aria-label="Sort events">
                <option value="upcoming">By event date</option>
                <option value="newest">Newest first</option>
                <option value="name">Event name</option>
              </select>
            </label>
          </div>
        </div>
        <nav class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1" aria-label="Filter by event status">
          <button v-for="tab in tabs" :key="tab" type="button" class="shrink-0 rounded-xl px-3.5 py-2 text-xs font-bold transition" :class="active===tab?'bg-indigo-600 text-white':'bg-slate-50 text-slate-500 hover:bg-indigo-50 hover:text-indigo-700'" :aria-pressed="active===tab" @click="active=tab">{{tab}} <span class="ml-1 opacity-70">{{countFor(tab)}}</span></button>
        </nav>
      </div>

      <div v-if="loading" class="grid gap-4 p-5 md:grid-cols-2">
        <div v-for="item in 4" :key="item" class="h-64 animate-pulse rounded-2xl bg-slate-100"></div>
      </div>
      <DsEmptyState v-else-if="!filtered.length" :title="rows.length?'No matching events':'Your event portfolio is empty'" :description="rows.length?'Try changing the search or status filter.':'Create your first event to configure registrations, tickets and on-ground operations.'">
        <template #actions>
          <DsButton v-if="rows.length" variant="secondary" @click="search='';active='All'">Clear filters</DsButton>
          <DsButton v-else to="/events/create">Create event</DsButton>
        </template>
      </DsEmptyState>
      <div v-else class="grid gap-4 p-4 md:grid-cols-2 lg:p-5">
        <article v-for="event in visible" :key="event.id" class="group overflow-hidden rounded-[18px] border border-slate-200/80 bg-white transition hover:border-indigo-200 hover:shadow-lg hover:shadow-indigo-900/5">
          <div class="flex gap-4 p-4 sm:p-5">
            <RouterLink :to="'/events/'+event.id" class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-slate-100 sm:h-28 sm:w-28">
              <img v-if="event.branding?.poster_url||event.branding?.cover_url" :src="event.branding?.poster_url||event.branding?.cover_url" :alt="event.name+' poster'" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-300 group-hover:scale-105"/>
              <div v-else class="flex h-full w-full items-end p-3 text-xs font-extrabold text-white" :style="{background:'linear-gradient(140deg,'+(event.branding?.primary_color||'#5c53d8')+','+(event.branding?.secondary_color||'#34415c')+')'}">{{event.category||'Event'}}</div>
            </RouterLink>
            <div class="min-w-0 flex-1">
              <div class="flex items-center justify-between gap-2"><DsStatusBadge :status="event.status"/><span class="text-[10px] font-bold text-slate-400">{{event.category||event.type||'Event'}}</span></div>
              <RouterLink :to="'/events/'+event.id" class="mt-3 block line-clamp-2 text-[16px] font-extrabold leading-5 tracking-tight text-slate-900 group-hover:text-indigo-600">{{event.name}}</RouterLink>
              <p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-500">{{event.start_date||'Date to be confirmed'}} · {{event.location||'Venue not set'}}</p>
            </div>
          </div>
          <div class="grid grid-cols-[1fr_1fr] gap-4 border-t border-slate-100 bg-slate-50/60 px-4 py-3 sm:px-5">
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Registrations</p>
              <p class="mt-1 text-sm font-extrabold text-slate-800">{{event.registrations||0}}</p>
            </div>
            <div>
              <div class="mb-2 flex justify-between gap-2 text-[10px] font-semibold text-slate-500"><span>Setup</span><span>{{Math.min(100,Math.max(0,Number(event.progress||0)))}}%</span></div>
              <div class="h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-indigo-500" :style="{width:Math.min(100,Math.max(0,Number(event.progress||0)))+'%'}"></div></div>
            </div>
          </div>
          <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 sm:px-5">
            <RouterLink :to="'/events/'+event.id" class="text-xs font-extrabold text-indigo-600 hover:text-indigo-800">Open workspace →</RouterLink>
            <RouterLink :to="'/events/'+event.id+'/preview'" class="text-xs font-semibold text-slate-500 hover:text-indigo-600">Preview event ↗</RouterLink>
          </div>
        </article>
      </div>
      <div v-if="visible.length<filtered.length" class="border-t border-slate-100 p-5 text-center">
        <button type="button" class="btn-secondary" @click="limit+=24">Load more events ({{filtered.length-visible.length}} remaining)</button>
      </div>
    </section>
  </div>
</template>
