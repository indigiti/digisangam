<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useEventStore } from '../stores/event'
import { api } from '../services/api'

const events=useEventStore(),rows=ref([]),show=ref(false),busy=ref(false),scanSession=ref(null),scanPayload=ref(''),scanResult=ref(null),attendance=ref([]),error=ref(''),speakersText=ref('')
const eventId=computed(()=>events.currentId)
const form=reactive({event_id:'',title:'',track:'Main',room:'',start_at:'',end_at:'',capacity:0,speakers:[],status:'published'})
const tracks=computed(()=>[...new Set(rows.value.map(x=>x.track).filter(Boolean))])

function seedTimes(){
  const day=events.current?.start_date||''
  if(day){form.start_at=day+'T09:00';form.end_at=day+'T10:00'}else{form.start_at='';form.end_at=''}
}
async function load(){
  error.value=''
  if(!events.loaded)await events.load()
  rows.value=eventId.value?await api.sessions(eventId.value):[]
  seedTimes()
}
onMounted(load);watch(eventId,load)

async function create(){
  busy.value=true;error.value=''
  try{
    form.event_id=eventId.value
    form.speakers=speakersText.value.split(',').map(x=>x.trim()).filter(Boolean)
    const row=await api.createSession(form)
    rows.value.push(row);rows.value.sort((a,b)=>String(a.start_at).localeCompare(String(b.start_at)))
    show.value=false;speakersText.value='';Object.assign(form,{title:'',track:'Main',room:'',capacity:0,status:'published'});seedTimes()
  }catch(e){error.value=e.message}
  finally{busy.value=false}
}
async function openScanner(session){
  error.value='';scanSession.value=session;scanResult.value=null;scanPayload.value=''
  try{attendance.value=await api.sessionAttendance(session.id)}catch(e){error.value=e.message;attendance.value=[]}
}
async function enter(){
  if(!scanPayload.value.trim())return
  busy.value=true
  try{scanResult.value=await api.sessionEnter(scanSession.value.id,scanPayload.value.trim());attendance.value=await api.sessionAttendance(scanSession.value.id);scanPayload.value=''}
  catch(e){scanResult.value={allowed:false,reason:e.message}}
  finally{busy.value=false}
}
async function setStatus(row,status){
  error.value=''
  try{Object.assign(row,await api.updateSession(row.id,{status}))}catch(e){error.value=e.message}
}
</script>
<template><div class="space-y-6">
<section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="eyebrow">Agenda</p><h1 class="page-title">Sessions & Agenda</h1><p class="page-subtitle">{{events.current?.name||'Select an event'}} · tracks, rooms, speakers, capacity and entry scanning.</p></div><button class="btn-primary" :disabled="!eventId" @click="show=true">+ Add Session</button></section>
<p v-if="error" class="rounded-xl bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{error}}</p>
<section class="grid gap-4 sm:grid-cols-3"><article class="panel p-5"><p class="panel-kicker">Sessions</p><p class="mt-2 text-2xl font-black">{{rows.length}}</p></article><article class="panel p-5"><p class="panel-kicker">Tracks</p><p class="mt-2 text-2xl font-black">{{tracks.length}}</p></article><article class="panel p-5"><p class="panel-kicker">Total capacity</p><p class="mt-2 text-2xl font-black">{{rows.reduce((n,x)=>n+Number(x.capacity||0),0)}}</p></article></section>
<section class="space-y-3"><div v-if="!rows.length" class="panel p-10 text-center text-sm text-slate-400">No sessions yet.</div><article v-for="r in rows" :key="r.id" class="panel grid gap-4 p-5 md:grid-cols-[100px_1fr_170px_110px_130px] md:items-center"><div><p class="text-xs font-black text-indigo-600">{{r.start_at?.slice(11,16)||'—'}}</p><p class="mt-1 text-[11px] text-slate-400">{{r.end_at?.slice(11,16)||'—'}}</p></div><div><div class="flex flex-wrap items-center gap-2"><h3 class="font-black">{{r.title}}</h3><span class="soft-pill">{{r.track}}</span></div><p class="mt-1 text-xs text-slate-500">{{r.speakers?.join(', ')||'Speaker TBA'}}</p></div><div><p class="text-xs font-bold">{{r.room||'Room TBA'}}</p><p class="mt-1 text-[11px] text-slate-400">Capacity {{r.capacity}}</p></div><div class="flex flex-col gap-1"><span class="status-badge" :class="r.status==='published'?'badge-published':r.status==='cancelled'?'bg-rose-100 text-rose-700':'badge-draft'">{{r.status}}</span><button v-if="r.status!=='published'" class="text-[10px] font-bold text-emerald-700" @click="setStatus(r,'published')">Publish</button><button v-if="r.status!=='cancelled'" class="text-[10px] font-bold text-rose-600" @click="setStatus(r,'cancelled')">Cancel</button></div><button class="btn-secondary py-1.5" :disabled="r.status==='cancelled'" @click="openScanner(r)">Entry Scanner</button></article></section>
<div v-if="show" class="modal-backdrop" @click.self="show=false"><form class="modal-card max-h-[90vh] overflow-y-auto" @submit.prevent="create"><h2 class="text-lg font-bold">Add session</h2><div class="mt-5 grid gap-4"><label class="field"><span>Title</span><input v-model="form.title" required/></label><div class="grid grid-cols-2 gap-3"><label class="field"><span>Track</span><input v-model="form.track"/></label><label class="field"><span>Room</span><input v-model="form.room"/></label></div><label class="field"><span>Speakers</span><input v-model="speakersText" placeholder="Speaker One, Speaker Two"/></label><div class="grid grid-cols-2 gap-3"><label class="field"><span>Starts</span><input v-model="form.start_at" type="datetime-local" required/></label><label class="field"><span>Ends</span><input v-model="form.end_at" type="datetime-local" required/></label></div><label class="field"><span>Capacity</span><input v-model.number="form.capacity" type="number" min="0"/></label></div><div class="mt-6 flex justify-end gap-2"><button type="button" class="btn-secondary" @click="show=false">Cancel</button><button class="btn-primary" :disabled="busy">Create</button></div></form></div>
<div v-if="scanSession" class="modal-backdrop" @click.self="scanSession=null"><div class="modal-card max-w-xl"><div class="flex justify-between gap-4"><div><p class="panel-kicker">Session entry</p><h2 class="mt-1 text-lg font-black">{{scanSession.title}}</h2><p class="mt-1 text-xs text-slate-400">{{attendance.length}} / {{scanSession.capacity}} entered</p></div><button class="icon-btn" @click="scanSession=null">×</button></div><textarea v-model="scanPayload" class="control mt-5 min-h-32 font-mono text-xs" placeholder="Scan attendee QR credential…"></textarea><button class="btn-primary mt-3 w-full" :disabled="busy" @click="enter">Verify & Record Session Entry</button><div v-if="scanResult" class="mt-4 rounded-xl p-3 text-sm font-bold" :class="scanResult.allowed?'bg-emerald-50 text-emerald-800':'bg-rose-50 text-rose-700'">{{scanResult.reason}}<span v-if="scanResult.attendee"> · {{scanResult.attendee.name}}</span></div></div></div>
</div></template>
