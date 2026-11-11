<script setup>
import {computed,onMounted,ref,watch} from 'vue'
import {useEventStore} from '../stores/event'
import {api} from '../services/api'
import EventTicket from '../components/EventTicket.vue'
import {TICKET_DESIGNS,getTicketDesign} from '../services/ticketDesigns'

const events=useEventStore()
const eventId=computed(()=>events.currentId)
const record=ref(null),selected=ref('editorial-mono'),busy=ref(false),loading=ref(true),error=ref(''),saved=ref(false)
const current=computed(()=>getTicketDesign(selected.value))
const sampleEvent=computed(()=>record.value||{
  name:'The Art of Gathering',category:'Live experience',start_date:'2026-12-12',
  venue_name:'Your venue',branding:{brand_name:'Your organization'}
})
const sampleTicket={name:'Premium Admission'}
const sampleAttendee={name:'Sample Attendee'}
let generation=0
async function load(){
  const seq=++generation
  loading.value=true;error.value='';saved.value=false
  if(!eventId.value){record.value=null;loading.value=false;return}
  try{
    const result=await api.event(eventId.value)
    if(seq!==generation)return
    record.value=result;selected.value=getTicketDesign(result.ticket_design).id
  }catch(e){if(seq===generation)error.value=e.message}
  finally{if(seq===generation)loading.value=false}
}
onMounted(async()=>{if(!events.loaded)await events.load();await load()})
watch(eventId,load)
async function apply(){
  if(!record.value||busy.value)return
  busy.value=true;saved.value=false;error.value=''
  try{
    record.value=await api.updateEvent(record.value.id,{ticket_design:selected.value})
    saved.value=true
    await events.load()
  }catch(e){error.value=e.message}
  finally{busy.value=false}
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div><p class="eyebrow">Tickets / Design Studio</p><h1 class="page-title">Ticket Design Studio</h1>
        <p class="page-subtitle">Five premium, mobile-first designs. One secure admission credential and checkout engine.</p></div>
      <div class="flex flex-wrap gap-2"><RouterLink to="/tickets" class="btn-secondary">Ticket types</RouterLink><RouterLink v-if="record" :to="'/events/'+record.id+'/preview'" class="btn-secondary">Public preview ↗</RouterLink></div>
    </div>
    <p v-if="error" role="alert" class="rounded-xl bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{error}}</p>
    <div v-if="!eventId" class="panel p-8 text-sm">Select an event before choosing its ticket design.</div>
    <div v-else class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_460px]">
      <div class="space-y-5">
        <section class="panel p-5 sm:p-7">
          <div class="mb-5 flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-bold">Choose a template</h2><span class="text-xs text-slate-500">Applies to public checkout and e-tickets</span></div>
          <div role="radiogroup" aria-label="Ticket design" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <button v-for="theme in TICKET_DESIGNS" :key="theme.id" type="button" role="radio" :aria-checked="selected===theme.id"
              class="group overflow-hidden rounded-2xl border-2 text-left transition hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-500"
              :class="selected===theme.id?'border-indigo-500 shadow-lg':'border-slate-200'"
              @click="selected=theme.id;saved=false">
              <div class="flex h-40 flex-col justify-between p-5" :style="{background:theme.hero,color:theme.heroText}">
                <span class="text-[10px] font-bold uppercase tracking-[.18em] opacity-80">DigiSangam · {{theme.number}}</span>
                <div><span class="text-[11px] font-semibold opacity-80">Event Pass</span><div class="mt-1 text-2xl font-bold leading-tight" :style="{fontFamily:theme.serif?'Georgia,serif':'Inter,system-ui,sans-serif'}">The Art of Gathering</div></div>
              </div>
              <div class="p-4"><div class="flex items-center justify-between gap-2"><b class="text-sm">{{theme.name}}</b><span class="grid h-6 w-6 place-items-center rounded-full border" :class="selected===theme.id?'border-indigo-600 bg-indigo-600 text-white':'border-slate-300 text-transparent'">✓</span></div>
                <p class="mt-2 min-h-12 text-xs leading-5 text-slate-500">{{theme.description}}</p>
                <p class="mt-3 text-[11px] font-semibold text-indigo-600">{{theme.recommended}}</p></div>
            </button>
          </div>
        </section>
        <section class="panel p-5 sm:p-6">
          <h2 class="text-lg font-bold">Publishing behavior</h2>
          <p class="mt-2 text-sm leading-6 text-slate-600">Changing a design updates the display of existing and newly issued tickets, not the underlying signed credential. Unconfirmed registrations never receive an admission QR.</p>
          <p class="mt-2 text-sm leading-6 text-slate-600">Preview QR codes are deliberately invalid. Real check-in continues to verify the server-issued credential and organizer access policies.</p>
          <div class="mt-5 flex flex-wrap items-center gap-3"><button class="btn-primary" :disabled="busy||loading||!record" @click="apply">{{busy?'Applying…':'Apply to this event'}}</button><span v-if="saved" role="status" class="text-sm font-semibold text-emerald-700">Design saved for {{record?.name}}</span></div>
        </section>
      </div>
      <aside class="panel h-fit p-5 2xl:sticky 2xl:top-24">
        <div class="mb-4 flex items-center justify-between gap-3"><div><p class="panel-kicker">Live design preview</p><h2 class="mt-1 text-lg font-bold">{{current.name}}</h2></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">Template {{current.number}}</span></div>
        <div class="flex justify-center rounded-[27px] bg-slate-100 p-3 sm:p-5">
          <EventTicket :event="sampleEvent" :ticket="sampleTicket" :attendee="sampleAttendee" :theme-id="selected" preview/>
        </div>
        <p class="mt-4 text-xs leading-5 text-slate-500">Preview content is illustrative. Actual tickets show verified registration data and the original signed QR payload.</p>
      </aside>
    </div>
  </div>
</template>
